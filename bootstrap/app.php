<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Todo erro gravado no log leva QUEM e ONDE (#58), para dar para
        // investigar. LGPD: só o número interno do usuário, nunca e-mail, nome ou IP.
        $exceptions->context(fn (): array => [
            // Número do usuário logado, ou null para visitante.
            'usuario_id' => Auth::id(),
            // Nome da rota (ex.: "cadastros.areas-atuacao.index"), ou null se não houver.
            'rota' => request()->route()?->getName(),
            // GET (abrir), POST (gravar), PUT (alterar)...
            'metodo' => request()->method(),
        ]);

        // Acesso negado (403) não é "erro" para o Laravel e, por padrão, não é
        // anotado em lugar nenhum (#58). Aqui ele vai para o canal "seguranca",
        // que grava mesmo com LOG_LEVEL=error no servidor. Devolver null deixa o
        // Laravel montar a resposta 403 normal (a tela não muda).
        $exceptions->render(function (HttpExceptionInterface $erro, Request $request) {
            // Outros códigos (404, 419, 500...) seguem o caminho de sempre.
            if ($erro->getStatusCode() !== 403) {
                return null;
            }

            Log::channel('seguranca')->warning('PERMISSAO_NEGADA', [
                // Número do usuário, ou "visitante" se ninguém estiver logado.
                'usuario_id' => Auth::id() ?? 'visitante',
                'rota' => $request->route()?->getName(),
                'metodo' => $request->method(),
                // Só o caminho (ex.: "cadastros/areas-atuacao"), sem a busca "?..." que poderia ter dados.
                'caminho' => $request->path(),
            ]);

            return null;
        });
    })->create();
