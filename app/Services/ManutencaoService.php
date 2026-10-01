<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\KmRegistro;
use App\Models\Solicitacao;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Models\VeiculoManutencao;
use App\Models\Viagem;
use App\Notifications\VeiculoManutencaoNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Fonte única para entrada/saída de manutenção, usada tanto pelo motorista
 * (a partir do check-in) quanto pelo gestor (flag na lista de veículos).
 */
class ManutencaoService
{
    public function __construct(
        private ViagemService $viagemService,
        private SolicitacaoService $solicitacaoService,
    ) {}

    /**
     * @param  array{tipo?: ?string, motivo?: ?string, km_entrada?: ?int, km_chegada?: ?int, local?: ?string}  $dados
     * @param  bool  $finalizarViagem  false mantém o comportamento antigo do flag do gestor
     */
    public function iniciar(Veiculo $veiculo, Usuario $usuario, array $dados, string $origem = 'motorista', bool $finalizarViagem = true): VeiculoManutencao
    {
        $manutencao = DB::transaction(function () use ($veiculo, $usuario, $dados, $origem, $finalizarViagem) {
            $veiculo = Veiculo::whereKey($veiculo->id)->lockForUpdate()->firstOrFail();

            if ($veiculo->status === 'manutencao') {
                throw ValidationException::withMessages(['veiculo' => 'Veículo já está em manutenção.']);
            }
            if ($veiculo->status === 'inativo') {
                throw ValidationException::withMessages(['veiculo' => 'Veículo inativo.']);
            }

            $viagem = $finalizarViagem
                ? Viagem::where('veiculo_id', $veiculo->id)->where('status', 'em_andamento')->first()
                : null;

            $kmChegada = $dados['km_chegada'] ?? null;
            if ($viagem && $kmChegada === null) {
                throw ValidationException::withMessages([
                    'km_chegada' => 'Há uma viagem em andamento com este veículo. Informe o KM de chegada para finalizá-la.',
                ]);
            }

            $kmEntrada = $dados['km_entrada'] ?? $kmChegada;
            if ($kmEntrada !== null && $kmEntrada < max((int) $veiculo->km_atual, (int) ($viagem?->km_saida ?? 0), (int) ($kmChegada ?? 0))) {
                throw ValidationException::withMessages(['km_entrada' => 'KM informado menor que o KM atual do veículo.']);
            }

            // O status muda antes de devolver a fila, para que o roteamento já
            // não ofereça a solicitação de volta a este mesmo veículo.
            $inicio = now();
            $veiculo->update(['status' => 'manutencao', 'manutencao_inicio' => $inicio]);

            // Primeiro devolve a fila, para que a finalização da viagem não
            // chame o motorista para a próxima solicitação deste veículo.
            Solicitacao::whereIn('status', ['pendente_motorista', 'aguardando_finalizacao_trajeto'])
                ->where('veiculo_pendente_id', $veiculo->id)
                ->get()
                ->each(fn (Solicitacao $s) => $this->solicitacaoService->devolverParaFila($s));

            if ($viagem) {
                $this->viagemService->chegada($viagem, [
                    'km_chegada' => $kmChegada,
                    'observacoes' => trim(($viagem->observacoes ? $viagem->observacoes.' ' : '').'[Finalizada ao entrar em manutenção]'),
                ]);
            }

            $this->registrarKm($veiculo, $usuario, $kmEntrada, 'Entrada em manutenção');

            $manutencao = $veiculo->manutencoes()->create([
                'inicio' => $inicio,
                'tipo' => $dados['tipo'] ?? null,
                'motivo' => $dados['motivo'] ?? null,
                'origem' => $origem,
                'aberta_por_id' => $usuario->id,
                'km_entrada' => $kmEntrada,
                'local' => $dados['local'] ?? null,
            ]);

            return $manutencao;
        });

        if ($origem === 'motorista') {
            $this->notificar($manutencao, 'entrada', $usuario);
        }

        return $manutencao;
    }

    /**
     * @param  array{km_saida?: ?int, observacao_saida?: ?string}  $dados
     */
    public function encerrar(Veiculo $veiculo, Usuario $usuario, array $dados = [], ?string $novoStatus = null): ?VeiculoManutencao
    {
        $manutencao = DB::transaction(function () use ($veiculo, $usuario, $dados, $novoStatus) {
            $veiculo = Veiculo::whereKey($veiculo->id)->lockForUpdate()->firstOrFail();

            if ($veiculo->status !== 'manutencao') {
                throw ValidationException::withMessages(['veiculo' => 'Veículo não está em manutenção.']);
            }

            $manutencao = $veiculo->manutencoes()->whereNull('fim')->latest('inicio')->first();
            $kmSaida = $dados['km_saida'] ?? null;

            if ($kmSaida !== null && $kmSaida < max((int) $veiculo->km_atual, (int) ($manutencao?->km_entrada ?? 0))) {
                throw ValidationException::withMessages(['km_saida' => 'KM de saída menor que o KM de entrada na manutenção.']);
            }

            $manutencao?->update([
                'fechada_por_id' => $usuario->id,
                'km_saida' => $kmSaida,
                'observacao_saida' => $dados['observacao_saida'] ?? null,
            ]);
            // Fecha também eventuais registros abertos duplicados (dados legados).
            $veiculo->manutencoes()->whereNull('fim')->update(['fim' => now()]);

            $this->registrarKm($veiculo, $usuario, $kmSaida, 'Saída de manutenção');

            $emUso = Checkin::where('veiculo_id', $veiculo->id)->where('status', 'ativo')->exists();
            $veiculo->update([
                'status' => $novoStatus ?? ($emUso ? 'em_uso' : 'disponivel'),
                'manutencao_inicio' => null,
            ]);

            return $manutencao?->fresh();
        });

        if ($manutencao && ! in_array($usuario->perfil, ['admin', 'gestor'])) {
            $this->notificar($manutencao, 'saida', $usuario);
        }

        return $manutencao;
    }

    private function registrarKm(Veiculo $veiculo, Usuario $usuario, ?int $km, string $observacao): void
    {
        if ($km === null || $km === (int) $veiculo->km_atual) {
            return;
        }

        $kmAnterior = $veiculo->km_atual;
        $veiculo->update(['km_atual' => $km]);

        KmRegistro::create([
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $usuario->motorista_id,
            'km_anterior' => $kmAnterior,
            'km_atual' => $km,
            'observacao' => $observacao,
            'registrado_at' => now(),
        ]);
    }

    private function notificar(VeiculoManutencao $manutencao, string $evento, Usuario $usuario): void
    {
        $veiculo = $manutencao->veiculo()->with('unidades')->first();
        $unidadeIds = $veiculo->unidades->pluck('id');

        $destinatarios = Usuario::where('perfil', 'admin')
            ->orWhere(function ($q) use ($unidadeIds) {
                $q->where('perfil', 'gestor')
                    ->where(fn ($q) => $q->whereIn('unidade_id', $unidadeIds)
                        ->orWhereHas('unidade', fn ($u) => $u->where('tipo', 'matriz')));
            })
            ->get();

        if ($destinatarios->isNotEmpty()) {
            Notification::send($destinatarios, new VeiculoManutencaoNotification($manutencao, $veiculo, $evento, $usuario->nome));
        }
    }
}
