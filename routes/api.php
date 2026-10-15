<?php

use App\Http\Controllers\Api\PublicoBlogController;
use App\Http\Controllers\ErroTelaController;
use App\Http\Controllers\SaudeController;
use Illuminate\Support\Facades\Route;

// Todas as rotas deste arquivo ganham o prefixo /api (ver bootstrap/app.php).
// A saúde é pública e consulta o banco: o limite (30 por minuto por IP) evita
// que alguém a use para sobrecarregar o servidor compartilhado.
Route::get('saude', SaudeController::class)
    ->middleware('throttle:30,1')
    ->name('api.saude');

// Erros que acontecem NA TELA (#58): o navegador avisa o servidor, que grava no
// log com o código ERRO_TELA. Limite de 10 por minuto por IP, para ninguém
// conseguir encher o log de propósito.
Route::post('erros-tela', ErroTelaController::class)
    ->middleware('throttle:10,1')
    ->name('api.erros-tela');

// API PÚBLICA DO BLOG (#61, bloco B3): os posts mais recentes já no site, em
// JSON, para o bloco "Últimas do IBTO" da landpage. Só leitura; só o site do
// IBTO lê pelo navegador (config/cors.php). Limite de 60 por minuto por IP.
Route::get('publico/blog', PublicoBlogController::class)
    ->middleware('throttle:60,1')
    ->name('api.publico.blog');
