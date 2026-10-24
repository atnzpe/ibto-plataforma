<?php

use App\Http\Controllers\Api\PublicoAreasController;
use App\Http\Controllers\Api\PublicoBlogController;
use App\Http\Controllers\Api\PublicoCursosController;
use App\Http\Controllers\Api\PublicoEventosController;
use App\Http\Controllers\Api\PublicoPostController;
use App\Http\Controllers\Api\PublicoRedeNacionalController;
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

// Um post inteiro, para a página do post no SITE (#61, B4). O slug só aceita
// letras minúsculas, números e hífen (o formato que a plataforma gera); outro
// formato nem chega ao banco (responde 404 direto).
Route::get('publico/blog/{slug}', PublicoPostController::class)
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:60,1')
    ->name('api.publico.post');

// Galeria de eventos para o SITE (#61, B4): a lista de álbuns e cada álbum.
Route::get('publico/eventos', [PublicoEventosController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('api.publico.eventos');
Route::get('publico/eventos/{slug}', [PublicoEventosController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:60,1')
    ->name('api.publico.evento');

// API PÚBLICA DOS CURSOS (#52): os cursos publicados e não fechados, em JSON,
// para a página /cursos do site. Só leitura; só o site do IBTO lê pelo
// navegador (config/cors.php). Limite de 60 por minuto por IP.
Route::get('publico/cursos', PublicoCursosController::class)
    ->middleware('throttle:60,1')
    ->name('api.publico.cursos');

// API PÚBLICA DAS ÁREAS DE ATUAÇÃO (#57): só as ativas, para a página "Áreas
// de atuação" do site. Só leitura; só o site do IBTO lê pelo navegador
// (config/cors.php). Limite de 60 por minuto por IP.
Route::get('publico/areas-atuacao', PublicoAreasController::class)
    ->middleware('throttle:60,1')
    ->name('api.publico.areas');

// API PÚBLICA DA REDE NACIONAL (#11): monitores e instrutores credenciados, por estado,
// para a página /rede-nacional do site. Só leitura; nunca contato. 60 por minuto por IP.
Route::get('publico/rede-nacional', PublicoRedeNacionalController::class)
    ->middleware('throttle:60,1')
    ->name('api.publico.rede-nacional');
