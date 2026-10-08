<?php

use App\Http\Controllers\AreaAtuacaoImagemController;
use App\Http\Controllers\Cadastros\AreaAtuacaoController;
use App\Http\Controllers\Cadastros\EquipeController;
use App\Http\Controllers\Cadastros\NivelController;
use App\Http\Controllers\EquipeLogoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Imagem de uma área de atuação (#37, #57): pública, porque o site também a
// mostra. Limite de 120 pedidos por minuto por IP, para ninguém sobrecarregar.
Route::get('imagens/areas-atuacao/{area}', AreaAtuacaoImagemController::class)
    ->middleware('throttle:120,1')
    ->name('areas-atuacao.imagem');

// Logo de uma equipe (#37): pública pelo mesmo motivo (rede nacional do site).
Route::get('imagens/equipes/{equipe}/logo', EquipeLogoController::class)
    ->middleware('throttle:120,1')
    ->name('equipes.logo');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// Cadastros de apoio (#37): só para administradores. "can:administrar" usa o
// Gate provisório (lista ADMIN_EMAILS + e-mail confirmado) até a #36; quem não
// passa recebe "403 Proibido". Endereços: /cadastros/areas-atuacao, .../create,
// .../{area}/edit. Sem "show" (a lista basta) e sem "destroy" (apagar = desativar).
Route::middleware(['auth', 'verified', 'can:administrar'])
    ->prefix('cadastros')
    ->name('cadastros.')
    ->group(function () {
        Route::resource('areas-atuacao', AreaAtuacaoController::class)
            ->except(['show', 'destroy'])
            ->parameters(['areas-atuacao' => 'area']);

        // Níveis (Praticante, Monitor I–II, Instrutor I–V, Instrutor Expert I–V).
        Route::resource('niveis', NivelController::class)
            ->except(['show', 'destroy'])
            ->parameters(['niveis' => 'nivel']);

        // Equipes filiadas (situação: em homologação, ativa ou inativa).
        Route::resource('equipes', EquipeController::class)
            ->except(['show', 'destroy']);
    });

require __DIR__.'/settings.php';
