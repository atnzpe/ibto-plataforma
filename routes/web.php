<?php

use App\Http\Controllers\AreaAtuacaoImagemController;
use App\Http\Controllers\Cadastros\AreaAtuacaoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Imagem de uma área de atuação (#37, #57): pública, porque o site também a
// mostra. Limite de 120 pedidos por minuto por IP, para ninguém sobrecarregar.
Route::get('imagens/areas-atuacao/{area}', AreaAtuacaoImagemController::class)
    ->middleware('throttle:120,1')
    ->name('areas-atuacao.imagem');

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
    });

require __DIR__.'/settings.php';
