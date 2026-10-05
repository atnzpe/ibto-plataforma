<?php

use App\Http\Controllers\SaudeController;
use Illuminate\Support\Facades\Route;

// Todas as rotas deste arquivo ganham o prefixo /api (ver bootstrap/app.php).
// A saúde é pública e consulta o banco: o limite (30 por minuto por IP) evita
// que alguém a use para sobrecarregar o servidor compartilhado.
Route::get('saude', SaudeController::class)
    ->middleware('throttle:30,1')
    ->name('api.saude');
