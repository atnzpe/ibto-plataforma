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
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ComandoGeralController;
use App\Http\Controllers\ConviteController;
use App\Http\Controllers\CursoImagemController;
use App\Http\Controllers\Cursos\AssistirController;
use App\Http\Controllers\Cursos\AulaArquivoController;
use App\Http\Controllers\Cursos\ChamadaController;
use App\Http\Controllers\Cursos\ConteudoController;
use App\Http\Controllers\Cursos\CursoController;
use App\Http\Controllers\Cursos\DetalhesCursoController;
use App\Http\Controllers\Cursos\MatriculaPainelController;
use App\Http\Controllers\Cursos\PagamentoConfigController;
use App\Http\Controllers\Cursos\TrilhaController;
use App\Http\Controllers\EquipeLogoController;
use App\Http\Controllers\Financeiro\CategoriaController;
use App\Http\Controllers\Financeiro\FormaPagamentoController;
use App\Http\Controllers\Financeiro\HistoricoController;
use App\Http\Controllers\Financeiro\LancamentoController;
use App\Http\Controllers\MatriculaController;
use App\Http\Controllers\MeusCursosController;
use Illuminate\Http\Request;
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

// Página inicial depois do login (Gleyson, 10/10/2026: "apenas Comando Geral"):
// quem é da equipe do IBTO (comando_geral.ver) vai direto para o Comando Geral;
// aluno, instrutor e admin de equipe ficam na própria página inicial ("Início").
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        // Equipe do IBTO: não existe "Dashboard" separado; o painel é o Comando Geral.
        if ($request->user()?->can('comando_geral.ver')) {
            return to_route('comando-geral');
        }

        // Demais pessoas: a página inicial delas é "Meus cursos" (desenho aprovado em 10/10/2026).
        return to_route('meus-cursos');
    })->name('dashboard');

    // Meus cursos: trilha de formação, cursos matriculados e o próximo passo de cada um.
    Route::get('meus-cursos', MeusCursosController::class)->name('meus-cursos');
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

// Cursos (#39 / #38 / #40): o Comando Geral (cursos.gerenciar) e quem PROPÕE
// (cursos.propor: instrutor, admin de equipe) — "can:cursos.acessar" deixa os
// dois entrarem; cada curso confere quem pode mexer nele (Gate curso.editar).
// Sem "destroy": apagar = cancelar ou encerrar, pelo fluxo de chancela.
Route::middleware(['auth', 'verified', 'can:cursos.acessar'])
    ->prefix('comando-geral')
    ->name('comando-geral.')
    ->group(function () {
        Route::resource('cursos', CursoController::class)->except(['show', 'destroy']);
        // Ações do fluxo de chancela: enviar, chancelar, rejeitar, cancelar, encerrar.
        Route::post('cursos/{curso}/situacao', [CursoController::class, 'situacao'])->name('cursos.situacao');
        // Detalhes do curso (#41): resumo e inscritos (só registro). Quem vê: Gate curso.inscritos.
        Route::get('cursos/{curso}/inscritos', DetalhesCursoController::class)->name('cursos.inscritos');
        // Chamada do encontro (#44). Quem faz: Gate curso.chamada (conferido no controlador).
        Route::get('cursos/{curso}/aulas/{aula}/chamada', [ChamadaController::class, 'edit'])->name('cursos.chamada');
        Route::put('cursos/{curso}/aulas/{aula}/chamada', [ChamadaController::class, 'update'])->name('cursos.chamada.update');

        // Conteúdo do curso (#39, bloco C2): módulos e aulas, em ordem.
        Route::controller(ConteudoController::class)->group(function () {
            Route::get('cursos/{curso}/conteudo', 'show')->name('cursos.conteudo');
            Route::post('cursos/{curso}/modulos', 'storeModulo')->name('modulos.store');
            Route::put('modulos/{modulo}', 'updateModulo')->name('modulos.update');
            Route::post('modulos/{modulo}/mover', 'moverModulo')->name('modulos.mover');
            Route::post('modulos/{modulo}/aulas', 'storeAula')->name('aulas.store');
            Route::put('aulas/{aula}', 'updateAula')->name('aulas.update');
            Route::post('aulas/{aula}/mover', 'moverAula')->name('aulas.mover');
        });
    });

// Trilhas (#39): só o Comando Geral monta (cursos.gerenciar).
Route::middleware(['auth', 'verified', 'can:cursos.gerenciar'])
    ->prefix('comando-geral')
    ->name('comando-geral.')
    ->group(function () {
        Route::resource('trilhas', TrilhaController::class)->except(['show', 'destroy']);
    });

// Dados para pagamento (#41, 10/10/2026): os do IBTO, a chave liga/desliga
// "por curso" e os dados próprios de um curso. Só "pagamentos.configurar".
// password.confirm:...,900 = pede a SENHA DE NOVO, que vale por 15 minutos
// (900 segundos), mesmo para quem já está logado. Limite de 10 pedidos por minuto.
Route::middleware(['auth', 'verified', 'can:pagamentos.configurar', 'password.confirm:password.confirm,900', 'throttle:10,1'])
    ->prefix('comando-geral')
    ->name('comando-geral.')
    ->controller(PagamentoConfigController::class)
    ->group(function () {
        Route::get('pagamento', 'edit')->name('pagamento.edit');
        Route::put('pagamento', 'update')->name('pagamento.update');
        Route::put('cursos/{curso}/pagamento', 'curso')->name('cursos.pagamento');
    });

// Matrículas (#41, bloco C5c): a secretaria/tesouraria confere o comprovante,
// confirma o pagamento ou cancela (permissão pagamentos.confirmar).
Route::middleware(['auth', 'verified', 'can:pagamentos.confirmar'])
    ->prefix('comando-geral/matriculas')
    ->name('comando-geral.matriculas.')
    ->controller(MatriculaPainelController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        // A ficha (nome e e-mail), com o registro de acesso na auditoria (LGPD).
        Route::get('{matricula}', 'ficha')->whereNumber('matricula')->name('ficha');
        Route::post('{matricula}/confirmar', 'confirmar')->name('confirmar');
        Route::post('{matricula}/cancelar', 'cancelar')->name('cancelar');
    });

// Arquivos das aulas (#39): apostila e vídeo enviado. Com login; quem pode ver
// é conferido pelo Gate "curso.conteudo" (sem acesso: 403, mesmo com o link).
Route::middleware(['auth', 'verified'])
    ->prefix('cursos/aulas/{aula}')
    ->name('cursos.aulas.')
    ->controller(AulaArquivoController::class)
    ->group(function () {
        Route::get('apostila', 'apostila')->name('apostila');
        Route::get('video', 'video')->name('video');
    });

// Matrícula pelo lado do aluno (#41, bloco C5b): garantir a vaga, enviar e ver
// o comprovante do PIX, desistir. Com login; a dona de cada matrícula é conferida
// no controlador. Limite de 20 pedidos por minuto (envio de arquivo).
Route::middleware(['auth', 'verified', 'throttle:20,1'])
    ->controller(MatriculaController::class)
    ->group(function () {
        Route::post('cursos/{curso}/matricula', 'store')->whereNumber('curso')->name('matriculas.store');
        Route::post('matriculas/{matricula}/comprovante', 'comprovante')->name('matriculas.comprovante');
        Route::get('matriculas/{matricula}/comprovante', 'verComprovante')->name('matriculas.comprovante.ver');
        Route::post('matriculas/{matricula}/desistir', 'desistir')->name('matriculas.desistir');
    });

// Sala de aula (#39, bloco C4c): as aulas em sequência (Anterior / Próxima).
// Com login; quem pode ver é conferido pelo Gate "curso.conteudo" no controlador.
// whereNumber: só números, para não confundir com /cursos/aulas/... acima.
Route::middleware(['auth', 'verified'])
    ->get('cursos/{curso}/assistir/{aula}', [AssistirController::class, 'show'])
    ->whereNumber(['curso', 'aula'])
    ->name('cursos.assistir');

// CATÁLOGO PÚBLICO (#38, bloco C4a): qualquer pessoa, mesmo sem login, vê os
// cursos publicados. Limite de 60 pedidos por minuto por IP.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('cursos', [CatalogoController::class, 'index'])->name('catalogo.index');
    // whereNumber: só números no {curso} (não confunde com /cursos/aulas/...).
    Route::get('cursos/{curso}', [CatalogoController::class, 'show'])->whereNumber('curso')->name('catalogo.curso');
    // Trilha em fluxograma (#38, bloco C4b).
    Route::get('trilhas/{trilha}', [CatalogoController::class, 'trilha'])->whereNumber('trilha')->name('catalogo.trilha');
    // Lembrete do encontro (#38, bloco C4d): arquivo .ics para a agenda.
    Route::get('cursos/{curso}/aulas/{aula}/lembrete', [CatalogoController::class, 'lembrete'])
        ->whereNumber(['curso', 'aula'])
        ->name('catalogo.lembrete');
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
