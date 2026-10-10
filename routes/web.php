<?php

use App\Http\Controllers\Admin\PermissaoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\AreaAtuacaoImagemController;
use App\Http\Controllers\Cadastros\AreaAtuacaoController;
use App\Http\Controllers\Cadastros\EquipeController;
use App\Http\Controllers\Cadastros\FiliadoController;
use App\Http\Controllers\Cadastros\ImportacaoFiliadosController;
use App\Http\Controllers\Cadastros\NivelController;
use App\Http\Controllers\ConviteController;
use App\Http\Controllers\EquipeLogoController;
use App\Http\Controllers\Financeiro\HistoricoController;
use App\Http\Controllers\Financeiro\LancamentoController;
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

        // Importação da planilha de filiados (#37, F4): enviar → relacionar colunas
        // → prévia → confirmar. Só quem cuida de TODOS os filiados.
        Route::middleware('can:filiados.gerenciar')
            ->prefix('filiados/importar')
            ->name('filiados.importar.')
            ->controller(ImportacaoFiliadosController::class)
            ->group(function () {
                Route::get('/', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('{codigo}/colunas', 'colunas')->name('colunas');
                Route::post('{codigo}/colunas', 'salvarColunas')->name('colunas.salvar');
                Route::get('{codigo}/valores', 'valores')->name('valores');
                Route::post('{codigo}/valores', 'salvarValores')->name('valores.salvar');
                Route::get('{codigo}/previa', 'previa')->name('previa');
                Route::post('{codigo}/confirmar', 'confirmar')->name('confirmar');
            });

        // Filiados (registro da carteirinha + conta do app), #37.
        Route::resource('filiados', FiliadoController::class)
            ->except(['show', 'destroy'])
            ->middleware('can:filiados.acessar');

        // Reenviar o convite por e-mail (#37, F3), no formulário de edição.
        Route::post('filiados/{filiado}/convite', [FiliadoController::class, 'reenviarConvite'])
            ->middleware(['can:filiados.acessar', 'throttle:6,1'])
            ->name('filiados.convite');
    });

// Financeiro (#59): livro-caixa. Olhar pede "financeiro.ver"; lançar pede
// "financeiro.lancar". Sem editar nem apagar: corrige-se com cancelar/estornar.
Route::middleware(['auth', 'verified'])
    ->prefix('financeiro')
    ->name('financeiro.')
    ->group(function () {
        Route::get('/', [LancamentoController::class, 'index'])
            ->middleware('can:financeiro.ver')
            ->name('lancamentos.index');
        // Histórico de toda movimentação (só leitura: não há rota de apagar).
        Route::get('historico', HistoricoController::class)
            ->middleware('can:financeiro.ver')
            ->name('historico');
        Route::get('lancamentos/novo', [LancamentoController::class, 'create'])
            ->middleware('can:financeiro.lancar')
            ->name('lancamentos.create');
        Route::post('lancamentos', [LancamentoController::class, 'store'])
            ->middleware('can:financeiro.lancar')
            ->name('lancamentos.store');
        // Ações sobre um lançamento: baixa (pendente → pago), cancelar (pendente)
        // e estornar (pago). As regras ficam no LivroCaixa.
        Route::middleware('can:financeiro.lancar')->group(function () {
            // Editar (só pendente; a regra fica no LivroCaixa).
            Route::get('lancamentos/{lancamento}/editar', [LancamentoController::class, 'edit'])->name('lancamentos.edit');
            Route::put('lancamentos/{lancamento}', [LancamentoController::class, 'update'])->name('lancamentos.update');
            Route::post('lancamentos/{lancamento}/pagar', [LancamentoController::class, 'pagar'])->name('lancamentos.pagar');
            Route::post('lancamentos/{lancamento}/cancelar', [LancamentoController::class, 'cancelar'])->name('lancamentos.cancelar');
            Route::post('lancamentos/{lancamento}/estornar', [LancamentoController::class, 'estornar'])->name('lancamentos.estornar');
        });
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
