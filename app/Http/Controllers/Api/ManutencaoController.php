<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manutencao\EncerrarManutencaoRequest;
use App\Http\Requests\Manutencao\IniciarManutencaoRequest;
use App\Http\Resources\VeiculoResource;
use App\Models\Checkin;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\ManutencaoService;

/**
 * Entrada/saída de manutenção sinalizada pelo próprio motorista (ou pela gestão).
 */
class ManutencaoController extends Controller
{
    public function __construct(private ManutencaoService $service) {}

    public function iniciar(IniciarManutencaoRequest $request, Veiculo $veiculo)
    {
        $user = $request->user();
        $gestao = $this->ehGestao($user);

        if (! $gestao && ! $this->temCheckinNoVeiculo($user, $veiculo)) {
            return response()->json(['message' => 'Só é possível sinalizar manutenção do veículo do seu check-in.'], 403);
        }

        $this->service->iniciar($veiculo, $user, $request->validated(), $gestao ? 'gestor' : 'motorista');

        return new VeiculoResource($veiculo->fresh()->load('manutencaoAberta.abertaPor'));
    }

    public function encerrar(EncerrarManutencaoRequest $request, Veiculo $veiculo)
    {
        $user = $request->user();
        $abriu = $veiculo->manutencaoAberta?->aberta_por_id === $user->id;

        if (! $this->ehGestao($user) && ! $abriu && ! $this->temCheckinNoVeiculo($user, $veiculo)) {
            return response()->json(['message' => 'Você não pode retirar este veículo da manutenção.'], 403);
        }

        $this->service->encerrar($veiculo, $user, $request->validated());

        return new VeiculoResource($veiculo->fresh());
    }

    private function ehGestao(Usuario $user): bool
    {
        return in_array($user->perfil, ['admin', 'gestor']);
    }

    private function temCheckinNoVeiculo(Usuario $user, Veiculo $veiculo): bool
    {
        return $user->motorista_id !== null && Checkin::where('motorista_id', $user->motorista_id)
            ->where('veiculo_id', $veiculo->id)
            ->where('status', 'ativo')
            ->exists();
    }
}
