<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Permissões que a tela precisa saber para mostrar ou esconder itens do
     * menu (#36). Permissão nova com item de menu entra aqui também.
     *
     * @var list<string>
     */
    private const PERMISSOES_DO_MENU = [
        'cadastros.areas',
        'cadastros.niveis',
        'cadastros.equipes',
        'usuarios.gerenciar',
        'permissoes.gerenciar',
    ];

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // O que o usuário logado pode fazer (#36), para a tela mostrar ou
            // esconder itens do menu. Ex.: { cadastros_equipes: true, ... }.
            // Só esconde o menu: quem garante a permissão de verdade são as rotas.
            // O ponto do nome vira "_" (no JavaScript, "pode.cadastros_equipes").
            'pode' => collect(self::PERMISSOES_DO_MENU)->mapWithKeys(fn (string $permissao) => [
                str_replace('.', '_', $permissao) => $request->user()?->can($permissao) ?? false,
            ])->all(),
            // Mensagem de sucesso da última ação (ex.: "Área cadastrada."),
            // mostrada uma vez na tela seguinte.
            'sucesso' => fn () => $request->session()->get('sucesso'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
