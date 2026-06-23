<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VeiculoController;
use App\Http\Controllers\Api\MotoristaController;
use App\Http\Controllers\Api\EscalaController;
use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\PlantaoController;
use App\Http\Controllers\Api\ViagemController;
use App\Http\Controllers\Api\AbastecimentoController;
use App\Http\Controllers\Api\KmController;
use App\Http\Controllers\Api\RelatorioController;

/*
|--------------------------------------------------------------------------
|  FleetCore — Rotas da API  (routes/api.php)
|--------------------------------------------------------------------------
*/

// ── Health check público ──────────────────────────────────
Route::get('/health', fn () => response()->json(['status' => 'ok', 'ts' => now()]));

// ── Autenticação ──────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login',  [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get ('me',     [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// ── Rotas protegidas por token Sanctum ────────────────────
    Route::middleware('auth:sanctum')->group(function () {

    // Veículos
    Route::apiResource('veiculos', VeiculoController::class);

    // Motoristas
    Route::get('motoristas/disponiveis',        [MotoristaController::class, 'disponiveis']);
    Route::get('motoristas/alertas/cnh',        [MotoristaController::class, 'alertasCnh']);
    Route::apiResource('motoristas', MotoristaController::class);

    // Escalas
    Route::post('escalas/semana',              [EscalaController::class, 'gerarSemana']);
    Route::apiResource('escalas', EscalaController::class)->except(['show', 'update']);

    // Check-in / Check-out
    Route::patch('checkins/{checkin}/checkout', [CheckinController::class, 'checkout']);
    Route::apiResource('checkins', CheckinController::class)->only(['index', 'show', 'store']);

    // Passagem de Plantão
    Route::get ('plantao/modelo/itens',            [PlantaoController::class, 'modeloItens']);
    Route::patch('plantao/{plantao}/item',         [PlantaoController::class, 'atualizarItem']);
    Route::patch('plantao/{plantao}/finalizar',    [PlantaoController::class, 'finalizar']);
    Route::apiResource('plantao', PlantaoController::class)->only(['index', 'show', 'store']);
    Route::patch('plantao/{plantao}/encerrar', [PlantaoController::class, 'encerrar']);
    Route::patch('plantao/{plantao}/encerrar', [\App\Http\Controllers\Api\PlantaoController::class, 'encerrar']);

    // Viagens
    Route::patch('viagens/{viagem}/chegada',   [ViagemController::class, 'chegada']);
    Route::apiResource('viagens', ViagemController::class)->only(['index', 'show', 'store']);
    Route::put('viagens/{viagem}', [\App\Http\Controllers\Api\ViagemController::class, 'update']);

    // Abastecimentos
    Route::get('abastecimentos/resumo',        [AbastecimentoController::class, 'resumo']);
    Route::apiResource('abastecimentos', AbastecimentoController::class)->only(['index', 'show', 'store']);
    Route::delete('abastecimentos/{abastecimento}', [\App\Http\Controllers\Api\AbastecimentoController::class, 'destroy']);

    // KM / Hodômetro
    Route::apiResource('km', KmController::class)->only(['index', 'store']);

    // Relatórios
/*    Route::prefix('relatorios')->group(function () {
        Route::get('dashboard',  [RelatorioController::class, 'dashboard']);
        Route::get('consumo',    [RelatorioController::class, 'consumo']);
        Route::get('checkins',   [RelatorioController::class, 'checkins']);
        Route::get('plantao',    [RelatorioController::class, 'plantao']);
        Route::get('motoristas', [RelatorioController::class, 'motoristas']);

    });*/

    // Relatórios
	Route::prefix('relatorios')->group(function () {
		Route::get('dashboard',       [\App\Http\Controllers\Api\RelatorioController::class, 'dashboard']);
		Route::get('abastecimentos',  [\App\Http\Controllers\Api\RelatorioController::class, 'abastecimentos']);
		Route::get('viagens',         [\App\Http\Controllers\Api\RelatorioController::class, 'viagens']);
		Route::get('plantao',         [\App\Http\Controllers\Api\RelatorioController::class, 'plantao']);
		Route::get('motoristas',      [\App\Http\Controllers\Api\RelatorioController::class, 'motoristas']);
		Route::get('checkins',        [\App\Http\Controllers\Api\RelatorioController::class, 'checkins']);
	});

    // Usuários — somente admin
        Route::middleware('admin')->apiResource('usuarios', \App\Http\Controllers\Api\UsuarioController::class)
		->only(['index', 'store', 'update', 'destroy']);
    });

