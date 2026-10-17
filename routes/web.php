<?php

use App\Http\Controllers\Admin\PermissaoController;
use App\Http\Controllers\Admin\SolicitacoesCadastroController;
use App\Http\Controllers\Admin\UsuarioAcaoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\AjudaController;
use App\Http\Controllers\AreaAtuacaoImagemController;
use App\Http\Controllers\Blog\AlbumController;
use App\Http\Controllers\Blog\BlogController;
use App\Http\Controllers\Blog\GaleriaController;
use App\Http\Controllers\Blog\PostCapaController;
use App\Http\Controllers\Blog\PostController;
use App\Http\Controllers\Cadastros\AreaAtuacaoController;
use App\Http\Controllers\Cadastros\EquipeController;
use App\Http\Controllers\Cadastros\FiliadoAnuidadeController;
use App\Http\Controllers\Cadastros\FiliadoController;
use App\Http\Controllers\Cadastros\FiliadoFotoController;
use App\Http\Controllers\Cadastros\ImportacaoFiliadosController;
use App\Http\Controllers\Cadastros\NivelController;
use App\Http\Controllers\CarteirinhaController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CertificadoController;
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
use App\Http\Controllers\Cursos\MatricularPeloIbtoController;
use App\Http\Controllers\Cursos\PagamentoConfigController;
use App\Http\Controllers\Cursos\TrilhaController;
use App\Http\Controllers\EquipeLogoController;
use App\Http\Controllers\Financeiro\CategoriaController;
use App\Http\Controllers\Financeiro\CupomController;
use App\Http\Controllers\Financeiro\FormaPagamentoController;
use App\Http\Controllers\Financeiro\HistoricoController;
use App\Http\Controllers\Financeiro\LancamentoController;
use App\Http\Controllers\Fotos\AlbunsVendaController;
use App\Http\Controllers\Fotos\FotografoController;
use App\Http\Controllers\Fotos\VitrineFotosController;
use App\Http\Controllers\FotoUsuarioController;
use App\Http\Controllers\MatriculaController;
use App\Http\Controllers\MeuDossieController;
use App\Http\Controllers\MeuPainelController;
use App\Http\Controllers\MeusCursosController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\MarcarTelaOffline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// PÁGINA INICIAL da plataforma (14/10/2026): não existe mais a tela "Em
// construção". Quem entrou vai para a página inicial dele (o /dashboard decide:
// Comando Geral ou "Meus cursos"); quem não entrou vai para a vitrine (o
// catálogo de cursos, com "Entrar" e "Criar conta"). A conferência "plataforma
// no ar" continua em /api/saude.
Route::get('/', function (Request $request) {
    // Logado: a página inicial certa para o papel dele.
    if ($request->user() !== null) {
        return to_route('dashboard');
    }

    // Visitante: o catálogo de cursos.
    return to_route('catalogo.index');
})->name('home');

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

    // Meu dossiê: o histórico da pessoa (graduação, certificados, cursos, presenças e
    // o que foi trabalhado). Sem número no endereço: cada um só vê o próprio.
    Route::get('meu-dossie', MeuDossieController::class)->name('meu-dossie');

    // Ajuda (#68, parte 2): o manual de instruções de cada perfil. Cada pessoa vê o
    // manual do seu papel; Administrador e Tecnologia veem todos.
    Route::get('ajuda', AjudaController::class)->name('ajuda');
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
        // "Matricular pessoa" (14/10/2026): o IBTO matricula alguém pelo registro ou e-mail
        // (único jeito de entrar num curso FECHADO). Quem pode: cursos.gerenciar (no controlador).
        Route::post('cursos/{curso}/matricular', MatricularPeloIbtoController::class)->name('cursos.matricular');
        // Chamada do encontro (#44). Quem faz: Gate curso.chamada (conferido no controlador).
        Route::get('cursos/{curso}/aulas/{aula}/chamada', [ChamadaController::class, 'edit'])->name('cursos.chamada');
        Route::put('cursos/{curso}/aulas/{aula}/chamada', [ChamadaController::class, 'update'])->name('cursos.chamada.update');
        // Certificados (#44): emitir um, emitir todos os aptos e cancelar. Só o IBTO (Gate no controlador).
        Route::post('cursos/{curso}/certificados/{matricula}', [CertificadoController::class, 'emitir'])->name('cursos.certificados.emitir');
        Route::post('cursos/{curso}/certificados', [CertificadoController::class, 'emitirAptos'])->name('cursos.certificados.aptos');
        Route::post('certificados/{certificado}/cancelar', [CertificadoController::class, 'cancelar'])->name('certificados.cancelar');

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

// O certificado para imprimir / salvar em PDF (#44): com login; o dono ou quem vê os inscritos.
Route::middleware(['auth', 'verified'])
    ->get('certificados/{codigo}', [CertificadoController::class, 'ver'])
    ->name('certificados.ver');

// Sala de aula (#39, bloco C4c): as aulas em sequência (Anterior / Próxima).
// Com login; quem pode ver é conferido pelo Gate "curso.conteudo" no controlador.
// whereNumber: só números, para não confundir com /cursos/aulas/... acima.
Route::middleware(['auth', 'verified'])
    ->get('cursos/{curso}/assistir/{aula}', [AssistirController::class, 'show'])
    ->whereNumber(['curso', 'aula'])
    ->name('cursos.assistir');
// Progresso (14/10/2026): marcar/desmarcar a aula como assistida. Mesma porta da sala de aula.
Route::middleware(['auth', 'verified'])
    ->put('cursos/{curso}/assistir/{aula}/assistida', [AssistirController::class, 'marcar'])
    ->whereNumber(['curso', 'aula'])
    ->name('cursos.assistida');

// CATÁLOGO PÚBLICO (#38, bloco C4a): qualquer pessoa, mesmo sem login, vê os
// cursos publicados. Limite de 60 pedidos por minuto por IP.
Route::middleware('throttle:60,1')->group(function () {
    // MarcarTelaOffline (#74): a tela do visitante pode ser guardada para usar sem internet.
    Route::get('cursos', [CatalogoController::class, 'index'])->middleware(MarcarTelaOffline::class)->name('catalogo.index');
    // whereNumber: só números no {curso} (não confunde com /cursos/aulas/...).
    Route::get('cursos/{curso}', [CatalogoController::class, 'show'])->whereNumber('curso')->middleware(MarcarTelaOffline::class)->name('catalogo.curso');
    // Trilha em fluxograma (#38, bloco C4b).
    Route::get('trilhas/{trilha}', [CatalogoController::class, 'trilha'])->whereNumber('trilha')->middleware(MarcarTelaOffline::class)->name('catalogo.trilha');
    // BLOG PÚBLICO (#61): a lista (com filtro por categoria) e a página de cada
    // post, pelo slug. Ex.: /blog?categoria=evento e /blog/formacao-no-estande
    Route::get('blog', [BlogController::class, 'index'])->middleware(MarcarTelaOffline::class)->name('blog.index');
    Route::get('blog/{post:slug}', [BlogController::class, 'show'])->middleware(MarcarTelaOffline::class)->name('blog.post');
    // GALERIA DE EVENTOS PÚBLICA (#61, B2): os álbuns publicados e as fotos de cada um.
    Route::get('eventos', [GaleriaController::class, 'index'])->name('galeria.index');
    Route::get('eventos/{album:slug}', [GaleriaController::class, 'show'])->name('galeria.album');
    // Validação PÚBLICA do certificado pelo código (#44). Ex.: /validar/IBTO-7K2M-9QXA
    Route::get('validar/{codigo}', [CertificadoController::class, 'validar'])->name('certificados.validar');
    // Validação PÚBLICA da carteirinha pelo QR code (#46): 30 consultas por minuto por IP,
    // para ninguém varrer os registros. A foto só sai com a autorização de imagem.
    Route::get('carteirinha/{codigo}', [CarteirinhaController::class, 'validar'])->middleware('throttle:30,1')->name('carteirinha.validar');
    Route::get('carteirinha/{codigo}/foto', [CarteirinhaController::class, 'foto'])->middleware('throttle:60,1')->name('carteirinha.foto');
    // Sitemap (#74): a lista das páginas públicas para os robôs de busca.
    Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
    // Lembrete do encontro (#38, bloco C4d): arquivo .ics para a agenda.
    Route::get('cursos/{curso}/aulas/{aula}/lembrete', [CatalogoController::class, 'lembrete'])
        ->whereNumber(['curso', 'aula'])
        ->name('catalogo.lembrete');
});

// BLOG no Comando Geral (#61): lista, cadastro, edição e as ações do caminho
// (revisão, publicar/agendar, arquivar). Quem entra: "blog.publicar".
// Sem "show" (a prévia é a própria edição) e sem "destroy" (apagar = arquivar).
Route::middleware(['auth', 'verified', 'can:blog.publicar'])
    ->prefix('comando-geral')
    ->name('comando-geral.')
    ->group(function () {
        Route::resource('blog', PostController::class)->except(['show', 'destroy']);
        Route::post('blog/{blog}/situacao', [PostController::class, 'situacao'])->name('blog.situacao');
        // Galeria de eventos (#61, bloco B2): álbuns, envio de fotos, descrições e o caminho.
        Route::resource('galeria', AlbumController::class)->parameters(['galeria' => 'album'])->except(['show', 'destroy']);
        Route::post('galeria/{album}/situacao', [AlbumController::class, 'situacao'])->name('galeria.situacao');
        Route::post('galeria/{album}/fotos', [AlbumController::class, 'enviarFotos'])->name('galeria.fotos.enviar');
        Route::put('galeria/{album}/fotos', [AlbumController::class, 'salvarFotos'])->name('galeria.fotos.salvar');
        Route::delete('galeria/fotos/{foto}', [AlbumController::class, 'apagarFoto'])->name('galeria.fotos.apagar');
    });

// Foto da galeria (#61, B2): pública só de álbum publicado e foto descrita.
Route::get('imagens/galeria/{foto}', [GaleriaController::class, 'foto'])
    ->middleware('throttle:300,1')
    ->name('galeria.foto');

// Capa de um post do blog (#61): pública só quando o post já está no site.
Route::get('imagens/blog/{post}', PostCapaController::class)
    ->middleware('throttle:120,1')
    ->name('blog.capa');

// Capa de um curso (#39): pública (catálogo e landpage), com limite por IP.
Route::get('imagens/cursos/{curso}', CursoImagemController::class)
    ->middleware('throttle:120,1')
    ->name('cursos.imagem');

// Foto de perfil de uma pessoa (#78): PRIVADA. Só com login e só para quem o
// portão "usuario.foto" libera; os outros recebem 404.
Route::get('fotos/usuarios/{usuario}', FotoUsuarioController::class)
    ->middleware(['auth', 'throttle:240,1'])
    ->whereNumber('usuario')
    ->name('usuarios.foto');

// Foto 3x4 do filiado e autorização de imagem (#78): a própria pessoa e quem pode
// editar aquele filiado (portão "filiado.foto"; os outros recebem 404 ou 403).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('fotos/filiados/{filiado}', [FiliadoFotoController::class, 'show'])
        ->middleware('throttle:240,1')
        ->whereNumber('filiado')
        ->name('filiados.foto');
    Route::post('filiados/{filiado}/foto', [FiliadoFotoController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('filiados.foto.update');
    Route::delete('filiados/{filiado}/foto', [FiliadoFotoController::class, 'destroy'])
        ->middleware('throttle:10,1')
        ->name('filiados.foto.destroy');
    Route::put('filiados/{filiado}/autorizacao-imagem', [FiliadoFotoController::class, 'autorizacao'])
        ->middleware('throttle:10,1')
        ->name('filiados.autorizacao-imagem');
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
        // Cupons de desconto (#89): só quem tem "cupons.gerenciar" (sem "destroy": apagar = desativar).
        Route::resource('cupons', CupomController::class)
            ->except(['show', 'destroy'])
            ->parameters(['cupons' => 'cupom'])
            ->middleware('can:cupons.gerenciar');
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

        // Ações na conta (#36, 10/10/2026): desativar/reativar (soft delete),
        // link de nova senha e convite. POST: mudam algo, então não vão por link comum.
        Route::controller(UsuarioAcaoController::class)
            ->prefix('usuarios/{usuario}')
            ->name('usuarios.')
            ->group(function () {
                Route::post('desativar', 'desativar')->name('desativar');
                Route::post('reativar', 'reativar')->name('reativar');
                Route::post('nova-senha', 'novaSenha')->name('nova-senha');
                Route::post('convite', 'convite')->name('convite');
            });
    });

// SOLICITAÇÕES DE CADASTRO (#84; grupo fechado): quem se cadastrou e está "em análise".
// Comando Geral/Tecnologia veem todas; o admin de equipe, só as da própria equipe
// (a regra fina de cada pedido fica no Gate "cadastros.analisar").
Route::middleware(['auth', 'verified', 'can:cadastros.aprovar'])
    ->prefix('admin/solicitacoes')
    ->name('admin.solicitacoes.')
    ->controller(SolicitacoesCadastroController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('{usuario}/aprovar', 'aprovar')->name('aprovar');
        Route::post('{usuario}/recusar', 'recusar')->name('recusar');
    });

// TELA DE ESPERA do cadastro "em análise" (#84). Só com login; o porteiro
// (BloquearCadastroNaoAprovado) manda para cá quem ainda não foi aprovado.
Route::middleware('auth')->get('cadastro/em-analise', function (Request $request) {
    // Já aprovado (ou chegou aqui sem precisar): vai para a página inicial dele.
    if ($request->user()?->cadastroAprovado()) {
        return redirect()->route('dashboard');
    }

    return Inertia\Inertia::render('auth/cadastro-em-analise', [
        'equipe' => $request->user()?->equipeSolicitada?->nome,
    ]);
})->name('cadastro.em-analise');

require __DIR__.'/settings.php';

// VENDA DE FOTOS DOS EVENTOS (#90). Começa desligada (interruptor do Comando Geral).
Route::middleware(['auth', 'verified'])->group(function () {
    // Comando Geral: interruptor, álbuns e pedidos ("fotos.gerenciar").
    Route::middleware('can:fotos.gerenciar')->prefix('comando-geral/fotos')->name('fotos.admin.')->group(function () {
        Route::get('/', [AlbunsVendaController::class, 'index'])->name('index');
        Route::post('interruptor', [AlbunsVendaController::class, 'interruptor'])->name('interruptor');
        Route::resource('albuns', AlbunsVendaController::class)->except(['index', 'show', 'destroy'])->parameters(['albuns' => 'album']);
        Route::post('pedidos/{pedido}/confirmar', [AlbunsVendaController::class, 'confirmar'])->name('pedidos.confirmar');
        Route::post('pedidos/{pedido}/cancelar', [AlbunsVendaController::class, 'cancelar'])->name('pedidos.cancelar');
    });

    // Fotógrafo: os álbuns dele, o envio das fotos e o financeiro dele ("fotos.vender").
    Route::middleware('can:fotos.vender')->prefix('fotografo')->name('fotos.fotografo.')->group(function () {
        Route::get('/', [FotografoController::class, 'index'])->name('index');
        Route::get('albuns/{album}', [FotografoController::class, 'album'])->name('album');
        Route::post('albuns/{album}/fotos', [FotografoController::class, 'enviar'])->middleware('throttle:20,1')->name('enviar');
        Route::delete('fotos/{foto}', [FotografoController::class, 'apagar'])->name('apagar');
        Route::get('financeiro', [FotografoController::class, 'financeiro'])->name('financeiro');
    });

    // Aluno: vitrine, compra, pedido (Pix) e os arquivos (cada um confere a permissão).
    Route::get('fotos/albuns/{album}', [VitrineFotosController::class, 'show'])->name('fotos.vitrine');
    Route::post('fotos/albuns/{album}/comprar', [VitrineFotosController::class, 'comprar'])->middleware('throttle:10,1')->name('fotos.comprar');
    Route::get('fotos/pedidos/{pedido}', [VitrineFotosController::class, 'pedido'])->name('fotos.pedido');
    Route::get('fotos/previa/{foto}', [VitrineFotosController::class, 'previa'])->middleware('throttle:300,1')->name('fotos.previa');
    Route::get('fotos/pedidos/{pedido}/original/{foto}', [VitrineFotosController::class, 'original'])->middleware('throttle:120,1')->name('fotos.original');
});

// MEU PAINEL (#79): números dos cursos do instrutor (os dele) e do admin de equipe
// (os da equipe). Sem dado pessoal de aluno. Quem não é nenhum dos dois: 403.
Route::middleware(['auth', 'verified', 'can:painel.instrutor'])->group(function () {
    Route::get('meu-painel', [MeuPainelController::class, 'index'])->name('meu-painel');
    Route::get('meu-painel/csv', [MeuPainelController::class, 'csv'])->middleware('throttle:20,1')->name('meu-painel.csv');
});

// RELATÓRIOS (#73): índice, tela, CSV e impressão (PDF). Cada relatório confere a
// própria permissão no controlador (relatorio.filiados, relatorio.cursos...).
Route::middleware(['auth', 'verified', 'can:relatorios.acessar'])->prefix('relatorios')->name('relatorios.')->group(function () {
    Route::get('/', [RelatorioController::class, 'index'])->name('index');
    Route::get('{chave}', [RelatorioController::class, 'show'])->whereAlpha('chave')->name('show');
    Route::get('{chave}/csv', [RelatorioController::class, 'csv'])->whereAlpha('chave')->middleware('throttle:20,1')->name('csv');
    Route::get('{chave}/imprimir', [RelatorioController::class, 'imprimir'])->whereAlpha('chave')->middleware('throttle:20,1')->name('imprimir');
});

// MINHA CARTEIRINHA (#46): o cartão da própria pessoa para imprimir (só filiados).
Route::middleware(['auth', 'verified'])->get('minha-carteirinha', [CarteirinhaController::class, 'minha'])->name('carteirinha.minha');
// Passaporte (#81): troca o código do QR se ele vazou (no máximo 3 trocas por hora).
Route::middleware(['auth', 'verified', 'throttle:3,60'])->post('minha-carteirinha/trocar-codigo', [CarteirinhaController::class, 'trocarCodigo'])->name('carteirinha.trocar-codigo');
