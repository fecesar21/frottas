<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SPA de Solicitação de Transporte — servida a partir do build do Vite
// (entrypoint solicitacao.html -> public/solicitacao.html). O catch-all
// garante que o roteamento client-side (react-router) funcione em qualquer
// sub-rota (ex: /solicitar/minhas-solicitacoes) após um refresh de página.
Route::get('/solicitar/{any?}', function () {
    return response(file_get_contents(public_path('solicitacao.html')))
        ->header('Content-Type', 'text/html');
})->where('any', '.*');
