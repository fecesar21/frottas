<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Solicitacao;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Notifications\NovaSolicitacaoTransporte;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SolicitacaoService
{
    public function __construct(private CheckinService $checkinService) {}

    public function store(array $data, Usuario $usuario): Solicitacao
    {
        $data['usuario_id'] = $usuario->id;
        $data['unidade_id'] = $usuario->unidade_id;
        $data['status'] = 'aberto';

        $solicitacao = Solicitacao::create($data);

        $destinatarios = Usuario::where('perfil', 'admin')
            ->orWhere(fn ($q) => $q->where('perfil', 'gestor')->where('unidade_id', $solicitacao->unidade_id))
            ->get();

        Notification::send($destinatarios, new NovaSolicitacaoTransporte($solicitacao));

        return $solicitacao;
    }

    public function aceitar(Solicitacao $solicitacao, string $motoristaId, string $veiculoId): Solicitacao
    {
        return DB::transaction(function () use ($solicitacao, $motoristaId, $veiculoId) {
            $emViagem = Viagem::where('motorista_id', $motoristaId)
                ->where('status', 'em_andamento')
                ->exists();

            if ($emViagem) {
                $solicitacao->update([
                    'status' => 'aguardando_finalizacao_trajeto',
                    'motorista_pendente_id' => $motoristaId,
                    'veiculo_pendente_id' => $veiculoId,
                ]);

                return $solicitacao->fresh();
            }

            $kmRetornoTrajetoAnterior = Viagem::where('motorista_id', $motoristaId)
                ->where('status', 'concluida')
                ->whereNotNull('km_chegada')
                ->latest('chegada_at')
                ->value('km_chegada');

            return $this->efetivarAceite($solicitacao, $motoristaId, $veiculoId, $kmRetornoTrajetoAnterior);
        });
    }

    /**
     * Processa a solicitação que ficava aguardando o motorista finalizar o trajeto em curso.
     * Chamado após a conclusão de uma viagem, permitindo então o checkout/check-in do motorista.
     */
    public function processarPendentePara(string $motoristaId, ?int $kmRetornoTrajetoAnterior = null): ?Solicitacao
    {
        $solicitacao = Solicitacao::where('status', 'aguardando_finalizacao_trajeto')
            ->where('motorista_pendente_id', $motoristaId)
            ->oldest()
            ->first();

        if (! $solicitacao) {
            return null;
        }

        return DB::transaction(fn () => $this->efetivarAceite(
            $solicitacao,
            $solicitacao->motorista_pendente_id,
            $solicitacao->veiculo_pendente_id,
            $kmRetornoTrajetoAnterior
        ));
    }

    public function cancelar(Solicitacao $solicitacao): Solicitacao
    {
        $solicitacao->update(['status' => 'cancelado']);

        return $solicitacao->fresh();
    }

    private function efetivarAceite(Solicitacao $solicitacao, string $motoristaId, string $veiculoId, ?int $kmRetornoTrajetoAnterior = null): Solicitacao
    {
        $motorista = Motorista::with('checkinAtivo')->findOrFail($motoristaId);
        $checkin = $motorista->checkinAtivo;

        if ($checkin && $checkin->veiculo_id !== $veiculoId) {
            $checkin = $this->trocarVeiculoDoCheckin($checkin, $veiculoId, $kmRetornoTrajetoAnterior);
        }

        $viagem = Viagem::create([
            'veiculo_id' => $veiculoId,
            'motorista_id' => $motoristaId,
            'checkin_id' => $checkin?->id,
            'origem' => $solicitacao->origemUnidade?->nome ?? $solicitacao->cidade ?? $solicitacao->hospital_destino ?? '',
            'destino' => $solicitacao->destinoUnidade?->nome ?? $solicitacao->hospital_destino ?? '',
            'motivo_viagem' => $solicitacao->motivo,
            'numero_atendimento' => $solicitacao->numero_atendimento,
            'km_saida' => 0,
            'saida_at' => now(),
            'status' => 'em_andamento',
        ]);

        $solicitacao->update([
            'viagem_id' => $viagem->id,
            'status' => 'em_trajeto',
            'motorista_pendente_id' => null,
            'veiculo_pendente_id' => null,
        ]);

        return $solicitacao->fresh();
    }

    private function trocarVeiculoDoCheckin(Checkin $checkin, string $veiculoId, ?int $kmRetorno = null): Checkin
    {
        $this->checkinService->checkout($checkin, $kmRetorno !== null ? ['km_retorno' => $kmRetorno] : []);

        $veiculo = Veiculo::findOrFail($veiculoId);

        return $this->checkinService->store([
            'motorista_id' => $checkin->motorista_id,
            'veiculo_id' => $veiculoId,
            'turno' => $checkin->turno,
            'km_saida' => $veiculo->km_atual,
        ]);
    }
}
