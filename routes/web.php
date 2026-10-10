<?php

use App\Http\Controllers\Admin\PermissaoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\AreaAtuacaoImagemController;
use App\Http\Controllers\Cadastros\AreaAtuacaoController;
use App\Http\Controllers\Cadastros\EquipeController;
use App\Http\Controllers\Cadastros\FiliadoAnuidadeController;
use App\Http\Controllers\Cadastros\FiliadoController;
use App\Http\Controllers\Cadastros\ImportacaoFiliadosController;
use App\Http\Controllers\Cadastros\NivelController;
use App\Http\Controllers\ComandoGeralController;
use App\Http\Controllers\ConviteController;
use App\Http\Controllers\CursoImagemController;
use App\Http\Controllers\Cursos\CursoController;
use App\Http\Controllers\Cursos\TrilhaController;
use App\Http\Controllers\EquipeLogoController;
use App\Http\Controllers\Financeiro\CategoriaController;
use App\Http\Controllers\Financeiro\FormaPagamentoController;
use App\Http\Controllers\Financeiro\HistoricoController;
use App\Http\Controllers\Financeiro\LancamentoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

// Página inicial depois do login (Gleyson, 10/10/2026: "apenas Comando Geral"):
// quem é da equipe do IBTO (comando_geral.ver) vai direto para o Comando Geral;
// aluno, instrutor e admin de equipe ficam na própria página inicial ("Início").
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        // Equipe do IBTO: não existe "Dashboard" separado; o painel é o Comando Geral.
        if ($request->user()?->can('comando_geral.ver')) {
            return to_route('comando-geral');
        }

        // Demais pessoas: a página inicial delas (vira "Meus cursos e trilhas", #38/#39).
        return Inertia::render('dashboard');
    })->name('dashboard');
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

        // Registrar o pagamento da anuidade (#37 + #59), na ficha do filiado:
        // gera também a receita no financeiro, por isso pede "financeiro.lancar".
        Route::post('filiados/{filiado}/anuidade', [FiliadoAnuidadeController::class, 'store'])
            ->middleware(['can:filiados.acessar', 'can:financeiro.lancar'])
            ->name('filiados.anuidade');

        // Reenviar o convite por e-mail (#37, F3), no formulário de edição.
        Route::post('filiados/{filiado}/convite', [FiliadoController::class, 'reenviarConvite'])
            ->middleware(['can:filiados.acessar', 'throttle:6,1'])
            ->name('filiados.convite');
    });

// Painel "Comando Geral" (#59 bloco 4 / #45): indicadores do IBTO.
Route::get('comando-geral', ComandoGeralController::class)
    ->middleware(['auth', 'verified', 'can:comando_geral.ver'])
    ->name('comando-geral');

// Cursos e trilhas no Comando Geral (#39 / #38): só com "cursos.gerenciar".
// Sem "destroy": apagar = encerrar (curso) ou desativar (trilha).
Route::middleware(['auth', 'verified', 'can:cursos.gerenciar'])
    ->prefix('comando-geral')
    ->name('comando-geral.')
    ->group(function () {
        Route::resource('cursos', CursoController::class)->except(['show', 'destroy']);
        Route::resource('trilhas', TrilhaController::class)->except(['show', 'destroy']);
    });

// Capa de um curso (#39): pública (catálogo e landpage), com limite por IP.
Route::get('imagens/cursos/{curso}', CursoImagemController::class)
    ->middleware('throttle:120,1')
    ->name('cursos.imagem');

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
            // Cadastros de apoio: categorias e formas de pagamento (sem "destroy":
            // apagar = desativar).
            Route::resource('categorias', CategoriaController::class)
                ->except(['show', 'destroy'])
                ->parameters(['categorias' => 'categoria']);
            Route::resource('formas-pagamento', FormaPagamentoController::class)
                ->except(['show', 'destroy'])
                ->parameters(['formas-pagamento' => 'forma'])
                ->names('formas');
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
