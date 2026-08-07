<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Solicitacao;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Notifications\NovaSolicitacaoTransporte;
use App\Notifications\NovaViagemDesignada;
use App\Notifications\SolicitacaoRecusadaPeloMotorista;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

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
            ->orWhere(function ($q) use ($solicitacao) {
                $q->where('perfil', 'gestor')
                    ->where(function ($q) use ($solicitacao) {
                        $q->where('unidade_id', $solicitacao->unidade_id)
                            ->orWhereHas('unidade', fn ($q) => $q->where('tipo', 'matriz'));
                    });
            })
            ->get();

        Notification::send($destinatarios, new NovaSolicitacaoTransporte($solicitacao));

        return $solicitacao;
    }

    /**
     * Gestor designa motorista/veículo. Não cria mais a Viagem aqui — apenas
     * marca a solicitação como pendente da confirmação do motorista.
     */
    public function aceitar(Solicitacao $solicitacao, string $motoristaId, string $veiculoId): Solicitacao
    {
        $solicitacao->update([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motoristaId,
            'veiculo_pendente_id' => $veiculoId,
            'motivo_recusa' => null,
        ]);

        $motorista = Motorista::with('usuario')->findOrFail($motoristaId);
        if ($motorista->usuario) {
            Notification::send($motorista->usuario, new NovaViagemDesignada($solicitacao->fresh()));
        }

        return $solicitacao->fresh();
    }

    /**
     * Motorista aceita a designação. Sem viagem ativa, exige km_saida e cria a Viagem.
     * Com viagem ativa, entra/permanece na fila (aguardando_finalizacao_trajeto).
     */
    public function motoristaAceitar(Solicitacao $solicitacao, string $motoristaId, ?int $kmSaida = null): Solicitacao
    {
        return DB::transaction(function () use ($solicitacao, $motoristaId, $kmSaida) {
            $emViagem = Viagem::where('motorista_id', $motoristaId)
                ->where('status', 'em_andamento')
                ->exists();

            if ($emViagem && $kmSaida === null) {
                $solicitacao->update(['status' => 'aguardando_finalizacao_trajeto']);

                return $solicitacao->fresh();
            }

            if ($kmSaida === null) {
                throw ValidationException::withMessages([
                    'km_saida' => 'Informe o KM de saída para iniciar a viagem.',
                ]);
            }

            return $this->efetivarAceite($solicitacao, $motoristaId, $solicitacao->veiculo_pendente_id, $kmSaida);
        });
    }

    public function motoristaRecusar(Solicitacao $solicitacao, string $motoristaId, string $motivo): Solicitacao
    {
        $motorista = Motorista::findOrFail($motoristaId);

        $solicitacao->update([
            'status' => 'recusada',
            'motivo_recusa' => $motivo,
            'motorista_pendente_id' => null,
            'veiculo_pendente_id' => null,
        ]);

        $destinatarios = Usuario::where('perfil', 'admin')
            ->orWhere(function ($q) use ($solicitacao) {
                $q->where('perfil', 'gestor')
                    ->where(function ($q) use ($solicitacao) {
                        $q->where('unidade_id', $solicitacao->unidade_id)
                            ->orWhereHas('unidade', fn ($q) => $q->where('tipo', 'matriz'));
                    });
            })
            ->get();

        Notification::send($destinatarios, new SolicitacaoRecusadaPeloMotorista($solicitacao->fresh(), $motorista->nome, $motivo));

        return $solicitacao->fresh();
    }

    /**
     * Chamado após a conclusão de uma viagem: avisa o motorista para informar
     * o KM de saída da próxima da fila (FIFO). Não cria a Viagem sozinha.
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

        $motorista = Motorista::with('usuario')->find($motoristaId);
        if ($motorista?->usuario) {
            Notification::send($motorista->usuario, new NovaViagemDesignada($solicitacao, fila: true));
        }

        return $solicitacao;
    }

    public function cancelar(Solicitacao $solicitacao): Solicitacao
    {
        $solicitacao->update(['status' => 'cancelado']);

        return $solicitacao->fresh();
    }

    private function efetivarAceite(Solicitacao $solicitacao, string $motoristaId, string $veiculoId, int $kmSaida): Solicitacao
    {
        $motorista = Motorista::with('checkinAtivo')->findOrFail($motoristaId);
        $checkin = $motorista->checkinAtivo;

        if ($checkin && $checkin->veiculo_id !== $veiculoId) {
            $checkin = $this->trocarVeiculoDoCheckin($checkin, $veiculoId, $kmSaida);
        }

        $viagem = Viagem::create([
            'veiculo_id' => $veiculoId,
            'motorista_id' => $motoristaId,
            'checkin_id' => $checkin?->id,
            'origem' => $solicitacao->origemUnidade?->nome ?? $solicitacao->cidade ?? $solicitacao->hospital_destino ?? '',
            'destino' => $solicitacao->destinoUnidade?->nome ?? $solicitacao->hospital_destino ?? '',
            'motivo_viagem' => $solicitacao->motivo,
            'numero_atendimento' => $solicitacao->numero_atendimento,
            'km_saida' => $kmSaida,
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
