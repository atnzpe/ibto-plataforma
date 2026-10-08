<?php

use App\Http\Controllers\Admin\PermissaoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\AreaAtuacaoImagemController;
use App\Http\Controllers\Cadastros\AreaAtuacaoController;
use App\Http\Controllers\Cadastros\EquipeController;
use App\Http\Controllers\Cadastros\FiliadoController;
use App\Http\Controllers\Cadastros\NivelController;
use App\Http\Controllers\ConviteController;
use App\Http\Controllers\EquipeLogoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Convite do filiado (#37, F3): link ASSINADO do e-mail ("signed": endereço
// alterado ou vencido dá 403). Mesmo endereço para abrir (GET) e salvar (POST).
// Limite de 10 por minuto, contra quem tenta adivinhar senhas.
Route::middleware(['signed', 'throttle:10,1'])->group(function () {
    Route::get('convite/{usuario}', [ConviteController::class, 'mostrar'])->name('convite.mostrar');
    Route::post('convite/{usuario}', [ConviteController::class, 'salvar'])->name('convite.salvar');
});

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

// Cadastros de apoio (#37). Cada tela pede a SUA permissão (#36): ex.
// "can:cadastros.equipes" só deixa entrar quem tem essa permissão por algum
// papel (ou a chave de emergência); quem não tem recebe "403 Proibido".
// Endereços: /cadastros/areas-atuacao, .../create, .../{area}/edit. Sem "show"
// (a lista basta) e sem "destroy" (apagar = desativar).
Route::middleware(['auth', 'verified'])
    ->prefix('cadastros')
    ->name('cadastros.')
    ->group(function () {
        Route::resource('areas-atuacao', AreaAtuacaoController::class)
            ->except(['show', 'destroy'])
            ->parameters(['areas-atuacao' => 'area'])
            ->middleware('can:cadastros.areas');

        // Níveis (Praticante, Monitor I–II, Instrutor I–V, Instrutor Expert I–V).
        Route::resource('niveis', NivelController::class)
            ->except(['show', 'destroy'])
            ->parameters(['niveis' => 'nivel'])
            ->middleware('can:cadastros.niveis');

        // Equipes filiadas (situação: em homologação, ativa ou inativa).
        Route::resource('equipes', EquipeController::class)
            ->except(['show', 'destroy'])
            ->middleware('can:cadastros.equipes');

        // Filiados (registro da carteirinha + conta do app), #37.
        Route::resource('filiados', FiliadoController::class)
            ->except(['show', 'destroy'])
            ->middleware('can:filiados.gerenciar');

        // Reenviar o convite por e-mail (#37, F3), no formulário de edição.
        Route::post('filiados/{filiado}/convite', [FiliadoController::class, 'reenviarConvite'])
            ->middleware(['can:filiados.gerenciar', 'throttle:6,1'])
            ->name('filiados.convite');
    });

// Administração (#36): grade de papéis × permissões, só com "permissoes.gerenciar".
Route::middleware(['auth', 'verified', 'can:permissoes.gerenciar'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('permissoes', [PermissaoController::class, 'edit'])->name('permissoes.edit');
        Route::put('permissoes', [PermissaoController::class, 'update'])->name('permissoes.update');
    });

// Usuários (#36): buscar uma pessoa e marcar os papéis dela, só com "usuarios.gerenciar".
Route::middleware(['auth', 'verified', 'can:usuarios.gerenciar'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('usuarios', UsuarioController::class)
            ->only(['index', 'edit', 'update'])
            ->parameters(['usuarios' => 'usuario']);
    });

require __DIR__.'/settings.php';
