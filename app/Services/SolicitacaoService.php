<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Colaborador;
use App\Models\Motorista;
use App\Models\Solicitacao;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Notifications\NovaSolicitacaoDisponivel;
use App\Notifications\NovaSolicitacaoTransporte;
use App\Notifications\NovaViagemDesignada;
use App\Notifications\SolicitacaoRecusadaPelaGestao;
use App\Notifications\SolicitacaoRecusadaPeloMotorista;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SolicitacaoService
{
    public function __construct(
        private CheckinService $checkinService,
        private RoteamentoSolicitacaoService $roteamento,
    ) {}

    public function store(array $data, Usuario $usuario): Solicitacao
    {
        $data['usuario_id'] = $usuario->id;
        $data['unidade_id'] = $usuario->unidade_id;
        $data['status'] = 'aberto';

        $solicitacao = Solicitacao::create($data);

        Notification::send($this->destinatariosGestao($solicitacao), new NovaSolicitacaoTransporte($solicitacao));

        $usuariosMotoristas = $this->roteamento->motoristasElegiveis($solicitacao)
            ->pluck('usuario')
            ->filter();
        if ($usuariosMotoristas->isNotEmpty()) {
            Notification::send($usuariosMotoristas, new NovaSolicitacaoDisponivel($solicitacao));
        }

        return $solicitacao;
    }

    /**
     * Gestor designa motorista/veículo. Não cria mais a Viagem aqui — apenas
     * marca a solicitação como pendente da confirmação do motorista.
     */
    public function aceitar(Solicitacao $solicitacao, string $motoristaId, string $veiculoId): Solicitacao
    {
        Veiculo::findOrFail($veiculoId)->garantirForaDeManutencao();

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
            /** @var Solicitacao $solicitacao */
            $solicitacao = Solicitacao::whereKey($solicitacao->id)->lockForUpdate()->firstOrFail();

            if (! in_array($solicitacao->status, ['pendente_motorista', 'aguardando_finalizacao_trajeto'])) {
                throw ValidationException::withMessages([
                    'status' => 'Esta solicitação já foi tratada.',
                ]);
            }

            $emViagem = Viagem::where('motorista_id', $motoristaId)
                ->where('status', 'em_andamento')
                ->exists();

            if ($emViagem) {
                // Nunca pode existir uma segunda Viagem em_andamento para o mesmo
                // motorista — mesmo que o client tenha informado km_saida (estado
                // desatualizado ou aceites concorrentes), a solicitação vai para a fila.
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

    /**
     * Motorista em atividade assume uma solicitação ainda aberta, com o veículo
     * do seu check-in. Segue o mesmo fluxo do aceite (cria a Viagem ou entra na fila).
     */
    public function assumir(Solicitacao $solicitacao, Motorista $motorista, ?int $kmSaida = null): Solicitacao
    {
        return DB::transaction(function () use ($solicitacao, $motorista, $kmSaida) {
            /** @var Solicitacao $solicitacao */
            $solicitacao = Solicitacao::whereKey($solicitacao->id)->lockForUpdate()->firstOrFail();

            if ($solicitacao->status !== 'aberto') {
                throw ValidationException::withMessages([
                    'status' => 'Esta solicitação já foi assumida ou tratada.',
                ]);
            }

            $checkin = $this->roteamento->checkinQueAtende($solicitacao, $motorista);
            if (! $checkin) {
                throw ValidationException::withMessages([
                    'motorista' => 'Você não está apto a assumir esta solicitação com o veículo do seu check-in.',
                ]);
            }

            $solicitacao->update([
                'status' => 'pendente_motorista',
                'motorista_pendente_id' => $motorista->id,
                'veiculo_pendente_id' => $checkin->veiculo_id,
                'motivo_recusa' => null,
            ]);

            return $this->motoristaAceitar($solicitacao, $motorista->id, $kmSaida);
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

        Notification::send($this->destinatariosGestao($solicitacao), new SolicitacaoRecusadaPeloMotorista($solicitacao->fresh(), $motorista->nome, $motivo));

        return $solicitacao->fresh();
    }

    /**
     * Gestor/admin recusa a solicitação antes do despacho. Status terminal;
     * o solicitante é avisado com o motivo.
     */
    public function recusarPelaGestao(Solicitacao $solicitacao, Usuario $gestor, string $motivo): Solicitacao
    {
        $solicitacao->update([
            'status' => 'recusada_gestao',
            'motivo_recusa' => $motivo,
            'motorista_pendente_id' => null,
            'veiculo_pendente_id' => null,
        ]);
        $solicitacao = $solicitacao->fresh();

        if ($solicitante = Usuario::find($solicitacao->usuario_id)) {
            Notification::send($solicitante, new SolicitacaoRecusadaPelaGestao($solicitacao, $gestor->nome, $motivo));
        }

        return $solicitacao;
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

    /**
     * Veículo designado entrou em manutenção: a solicitação volta a ficar aberta
     * para outro motorista/veículo e a gestão e os motoristas elegíveis são avisados.
     */
    public function devolverParaFila(Solicitacao $solicitacao): Solicitacao
    {
        $solicitacao->update([
            'status' => 'aberto',
            'motorista_pendente_id' => null,
            'veiculo_pendente_id' => null,
        ]);
        $solicitacao = $solicitacao->fresh();

        Notification::send($this->destinatariosGestao($solicitacao), new NovaSolicitacaoTransporte($solicitacao));

        $usuariosMotoristas = $this->roteamento->motoristasElegiveis($solicitacao)->pluck('usuario')->filter();
        if ($usuariosMotoristas->isNotEmpty()) {
            Notification::send($usuariosMotoristas, new NovaSolicitacaoDisponivel($solicitacao));
        }

        return $solicitacao;
    }

    public function cancelar(Solicitacao $solicitacao): Solicitacao
    {
        $solicitacao->update(['status' => 'cancelado']);

        return $solicitacao->fresh();
    }

    /**
     * @return Collection<int, Usuario>
     */
    private function destinatariosGestao(Solicitacao $solicitacao): Collection
    {
        return Usuario::where('perfil', 'admin')
            ->orWhere(function ($q) use ($solicitacao) {
                $q->where('perfil', 'gestor')
                    ->where(function ($q) use ($solicitacao) {
                        $q->where('unidade_id', $solicitacao->unidade_id)
                            ->orWhereHas('unidade', fn ($q) => $q->where('tipo', 'matriz'));
                    });
            })
            ->get();
    }

    private function efetivarAceite(Solicitacao $solicitacao, string $motoristaId, string $veiculoId, int $kmSaida): Solicitacao
    {
        Veiculo::findOrFail($veiculoId)->garantirForaDeManutencao();

        $motorista = Motorista::with('checkinsAtivos')->findOrFail($motoristaId);
        // Com check-in duplo, usa o check-in que já está no veículo da viagem.
        $checkin = $motorista->checkinsAtivos->firstWhere('veiculo_id', $veiculoId)
            ?? $motorista->checkinsAtivos->first();

        if ($checkin && $checkin->veiculo_id !== $veiculoId) {
            // KM de retorno vem da última viagem feita com o veículo deste
            // check-in; sem viagem, o odômetro não andou.
            $kmRetornoTrajetoAnterior = $checkin->kmRetornoSemViagem()
                ?? Viagem::where('veiculo_id', $checkin->veiculo_id)
                    ->where('status', 'concluida')
                    ->whereNotNull('km_chegada')
                    ->latest('chegada_at')
                    ->value('km_chegada');

            $checkin = $this->trocarVeiculoDoCheckin($checkin, $veiculoId, $kmRetornoTrajetoAnterior);
        }

        $viagem = Viagem::create([
            'veiculo_id' => $veiculoId,
            'motorista_id' => $motoristaId,
            'checkin_id' => $checkin?->id,
            'origem' => $solicitacao->origem()?->nome ?? $solicitacao->cidade ?? $solicitacao->hospital_destino ?? '',
            'destino' => $solicitacao->destino()?->nome ?? $solicitacao->hospital_destino ?? '',
            'motivo_viagem' => $solicitacao->motivo,
            'numero_atendimento' => $solicitacao->numero_atendimento,
            'km_saida' => $kmSaida,
            'saida_at' => now(),
            'status' => 'em_andamento',
        ]);

        if ($solicitacao->motivo === 'transporte_colaborador' && $colaborador = $this->colaboradorDoSolicitante($solicitacao)) {
            $viagem->colaboradores()->sync([$colaborador->id]);
        }

        $solicitacao->update([
            'viagem_id' => $viagem->id,
            'status' => 'em_trajeto',
            'motorista_pendente_id' => null,
            'veiculo_pendente_id' => null,
        ]);

        return $solicitacao->fresh();
    }

    /**
     * Em transporte de colaborador, o próprio solicitante é o colaborador
     * transportado. Ele entrou via AD, então é casado pelo objectGUID; se a
     * sincronização ainda não o trouxe, é criado a partir do cadastro do usuário.
     */
    private function colaboradorDoSolicitante(Solicitacao $solicitacao): ?Colaborador
    {
        $usuario = Usuario::find($solicitacao->usuario_id);

        if (! $usuario?->ldap_guid) {
            return null;
        }

        $existente = Colaborador::where('ldap_guid', $usuario->ldap_guid)->first();
        $unidadeId = $usuario->unidade_id ?? $solicitacao->unidade_id;

        // Sem unidade não há como cadastrá-lo; a viagem segue sem o vínculo
        // em vez de impedir o aceite.
        if ($existente || ! $unidadeId) {
            return $existente;
        }

        return Colaborador::create([
            'ldap_guid' => $usuario->ldap_guid,
            'unidade_id' => $unidadeId,
            'nome' => mb_strtoupper($usuario->nome),
            'email' => $usuario->email,
            'ativo' => true,
        ]);
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
