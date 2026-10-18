<?php

/*
 * RESPOSTA DO LOGIN (17/10/2026): para onde a pessoa vai depois de entrar.
 *
 * O problema: ao abrir uma página protegida sem estar logado, o Laravel guarda
 * esse endereço ("intended") e leva a pessoa até ele depois do login. Se quem
 * entra é OUTRA conta, sem permissão (ex.: a página Usuários era do
 * administrador), a tela abria com 403 e só sumia recarregando.
 *
 * A regra: volta para a página guardada só se esta conta puder abri-la (todas
 * as permissões "can:" da rota passam). Senão, vai para o início do papel dela.
 */

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\LoginResponse as RespostaDoLogin;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LoginResponse implements RespostaDoLogin
{
    /**
     * Monta a resposta do login.
     *
     * @param  Request  $request  O pedido do login (com a sessão já da pessoa).
     * @return Response Para a página guardada (se pode abrir) ou para o início.
     */
    public function toResponse($request): Response
    {
        // Pedido por API/JSON (raro aqui): só confirma o login, como o Fortify faz.
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $guardada = $request->session()->pull('url.intended');
        // Há página guardada e esta conta pode abri-la: volta para ela.
        if (is_string($guardada) && $this->podeAbrir($request, $guardada)) {
            return new RedirectResponse($guardada);
        }

        // Sem página guardada, ou sem permissão para ela: o início (cada papel tem o seu).
        return new RedirectResponse(route('dashboard'));
    }

    /**
     * Esta conta passa em todas as permissões "can:..." da rota do endereço?
     *
     * @param  Request  $request  O pedido (para saber quem está logado).
     * @param  string  $endereco  Ex.: 'https://cursos-homolog.ib-to.org/admin/usuarios'
     */
    private function podeAbrir(Request $request, string $endereco): bool
    {
        try {
            // Acha a rota do endereço (só GET: é uma página para abrir).
            $rota = Route::getRoutes()->match(Request::create($endereco, 'GET'));
        } catch (Throwable) {
            // Endereço que não é desta plataforma (ou sumiu): não arrisca; vai para o início.
            return false;
        }

        foreach ($rota->gatherMiddleware() as $middleware) {
            // Só as permissões simples "can:nome" (sem parâmetro de modelo) decidem aqui.
            if (is_string($middleware) && str_starts_with($middleware, 'can:') && ! str_contains($middleware, ',')) {
                // Uma permissão falhou: esta conta não pode abrir a página.
                if (Gate::forUser($request->user())->denies(substr($middleware, 4))) {
                    return false;
                }
            }
        }

        return true;
    }
}
