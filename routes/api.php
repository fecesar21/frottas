<?php

use App\Http\Controllers\Api\AbastecimentoController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\ChecklistVeiculoController;
use App\Http\Controllers\Api\EscalaController;
use App\Http\Controllers\Api\KmController;
use App\Http\Controllers\Api\MotoristaController;
use App\Http\Controllers\Api\NotificacaoController;
use App\Http\Controllers\Api\PlantaoController;
use App\Http\Controllers\Api\RelatorioController;
use App\Http\Controllers\Api\SolicitacaoController;
use App\Http\Controllers\Api\UnidadeController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VeiculoController;
use App\Http\Controllers\Api\ViagemController;
use App\Http\Controllers\Api\ViagemPontoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
|  FleetCore — Rotas da API  (routes/api.php)
|--------------------------------------------------------------------------
*/

// ── Health check público ──────────────────────────────────
Route::get('/health', fn () => response()->json(['status' => 'ok', 'ts' => now()]));

// ── Autenticação ──────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('login-ad', [AuthController::class, 'loginAd'])->middleware('throttle:login-ad');
    Route::post('esqueci-senha', [AuthController::class, 'esqueciSenha'])->middleware('throttle:esqueci-senha');
    Route::post('redefinir-senha', [AuthController::class, 'redefinirSenha'])->middleware('throttle:esqueci-senha');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// ── Rotas protegidas por token Sanctum ────────────────────
Route::middleware(['auth:sanctum', 'escopo.unidade', 'solicitante.restrito'])->group(function () {

    // Veículos
    Route::get('veiculos/posicoes', [VeiculoController::class, 'posicoes']);
    Route::apiResource('veiculos', VeiculoController::class);

    // Motoristas
    Route::get('motoristas/disponiveis', [MotoristaController::class, 'disponiveis']);
    Route::get('motoristas/sem-usuario', [MotoristaController::class, 'semUsuario']);
    Route::get('motoristas/alertas/cnh', [MotoristaController::class, 'alertasCnh']);
    Route::apiResource('motoristas', MotoristaController::class);

    // Escalas
    Route::post('escalas/semana', [EscalaController::class, 'gerarSemana']);
    Route::apiResource('escalas', EscalaController::class)->except(['show', 'update']);

    // Check-in / Check-out
    Route::patch('checkins/{checkin}/checkout', [CheckinController::class, 'checkout']);
    Route::apiResource('checkins', CheckinController::class)->only(['index', 'show', 'store']);

    // Checklist de Veículo
    Route::get('checklist-veiculo/modelo/itens', [ChecklistVeiculoController::class, 'itensModelo']);
    Route::get('checklist-veiculo/pendente', [ChecklistVeiculoController::class, 'pendente']);
    Route::patch('checklist-veiculo/{checklistVeiculo}/item', [ChecklistVeiculoController::class, 'atualizarItem']);
    Route::patch('checklist-veiculo/{checklistVeiculo}/enviar', [ChecklistVeiculoController::class, 'enviar']);
    Route::apiResource('checklist-veiculo', ChecklistVeiculoController::class)->only(['index', 'show']);

    // Passagem de Plantão
    Route::get('plantao/modelo/itens', [PlantaoController::class, 'modeloItens']);
    Route::patch('plantao/{plantao}/item', [PlantaoController::class, 'atualizarItem']);
    Route::patch('plantao/{plantao}/finalizar', [PlantaoController::class, 'finalizar']);
    Route::patch('plantao/{plantao}/encerrar', [PlantaoController::class, 'encerrar']);
    Route::apiResource('plantao', PlantaoController::class)->only(['index', 'show', 'store']);

    // Viagens
    Route::patch('viagens/{viagem}/chegada', [ViagemController::class, 'chegada']);
    Route::post('viagens/{viagem}/pontos', [ViagemPontoController::class, 'store']);
    Route::get('viagens/{viagem}/pontos', [ViagemPontoController::class, 'index']);
    Route::apiResource('viagens', ViagemController::class)->only(['index', 'show', 'store', 'update']);

    // Solicitações de Transporte
    Route::patch('solicitacoes/{solicitacao}/aceitar', [SolicitacaoController::class, 'aceitar'])->name('solicitacoes.aceitar');
    Route::patch('solicitacoes/{solicitacao}/cancelar', [SolicitacaoController::class, 'cancelar'])->name('solicitacoes.cancelar');
    Route::patch('solicitacoes/{solicitacao}/motorista-aceitar', [SolicitacaoController::class, 'motoristaAceitar'])->name('solicitacoes.motorista-aceitar');
    Route::patch('solicitacoes/{solicitacao}/motorista-recusar', [SolicitacaoController::class, 'motoristaRecusar'])->name('solicitacoes.motorista-recusar');
    Route::apiResource('solicitacoes', SolicitacaoController::class)->parameters(['solicitacoes' => 'solicitacao'])->only(['index', 'show', 'store']);

    // Abastecimentos
    Route::get('abastecimentos/resumo', [AbastecimentoController::class, 'resumo']);
    Route::apiResource('abastecimentos', AbastecimentoController::class)->only(['index', 'show', 'store']);
    Route::delete('abastecimentos/{abastecimento}', [AbastecimentoController::class, 'destroy']);

    // KM / Hodômetro
    Route::apiResource('km', KmController::class)->only(['index', 'store']);

    // Relatórios
    Route::prefix('relatorios')->group(function () {
        Route::get('dashboard', [RelatorioController::class, 'dashboard']);
        Route::get('dashboard/graficos', [RelatorioController::class, 'dashboardGraficos']);
        Route::get('abastecimentos', [RelatorioController::class, 'abastecimentos']);
        Route::get('abastecimentos/pdf', [RelatorioController::class, 'abastecimentosPdf']);
        Route::get('viagens', [RelatorioController::class, 'viagens']);
        Route::get('viagens/pdf', [RelatorioController::class, 'viagensPdf']);
        Route::get('plantao', [RelatorioController::class, 'plantao']);
        Route::get('plantao/pdf', [RelatorioController::class, 'plantaoPdf']);
        Route::get('motoristas', [RelatorioController::class, 'motoristas']);
        Route::get('motoristas/pdf', [RelatorioController::class, 'motoristasPdf']);
        Route::get('eficiencia', [RelatorioController::class, 'eficiencia']);
        Route::get('checkins', [RelatorioController::class, 'checkins']);
        Route::get('checklist-veiculo', [RelatorioController::class, 'checklistVeiculo']);
    });

    // Unidades
    Route::get('unidades', [UnidadeController::class, 'index'])->name('unidades.index');
    Route::post('unidades', [UnidadeController::class, 'store']);
    Route::get('unidades/{unidade}', [UnidadeController::class, 'show'])->name('unidades.show');
    Route::patch('unidades/{unidade}', [UnidadeController::class, 'update']);
    Route::delete('unidades/{unidade}', [UnidadeController::class, 'destroy']);
    Route::post('unidades/{unidade}/motoristas', [UnidadeController::class, 'vincularMotoristas']);
    Route::delete('unidades/{unidade}/motoristas/{motorista}', [UnidadeController::class, 'desvincularMotorista']);
    Route::post('unidades/{unidade}/veiculos', [UnidadeController::class, 'vincularVeiculos']);
    Route::delete('unidades/{unidade}/veiculos/{veiculo}', [UnidadeController::class, 'desvincularVeiculo']);

    // Notificações
    Route::get('notificacoes', [NotificacaoController::class, 'index'])->name('notificacoes.index');
    Route::get('notificacoes/nao-lidas', [NotificacaoController::class, 'naoLidas'])->name('notificacoes.nao-lidas');
    Route::post('notificacoes/marcar-lidas', [NotificacaoController::class, 'marcarTodasLidas'])->name('notificacoes.marcar-lidas');
    Route::patch('notificacoes/{id}/lida', [NotificacaoController::class, 'marcarLida'])->name('notificacoes.marcar-lida');

    // Usuários — somente admin
    Route::middleware('admin')->apiResource('usuarios', UsuarioController::class)
        ->only(['index', 'store', 'update', 'destroy']);
});
