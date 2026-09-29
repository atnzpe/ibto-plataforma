<?php

use App\Http\Controllers\SaudeController;
use Illuminate\Support\Facades\Route;

// Todas as rotas deste arquivo ganham o prefixo /api (ver bootstrap/app.php).
Route::get('saude', SaudeController::class)->name('api.saude');
