<?php

namespace App\Http\Middleware;

use App\Support\VersaoNoAr;
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
        'filiados.gerenciar',
        'filiados.acessar',
        'usuarios.gerenciar',
        'permissoes.gerenciar',
        // Financeiro (#59).
        'financeiro.ver',
        // Painel "Comando Geral" (#59 bloco 4 / #45).
        'comando_geral.ver',
        // Cursos (#39 / #40): Comando Geral ou quem propõe (Gate cursos.acessar).
        'cursos.acessar',
        // Matrículas (#41): quem confirma os pagamentos PIX.
        'pagamentos.confirmar',
        // Dados para pagamento (#41): PIX e conta do IBTO e dos cursos.
        'pagamentos.configurar',
        // Blog (#61): escrever, publicar e arquivar posts.
        'blog.publicar',
        // Solicitações de cadastro (#84): Comando Geral, Tecnologia e admin de equipe.
        'cadastros.aprovar',
        // Cupons de desconto (#89).
        'cupons.gerenciar',
        // Venda de fotos (#90): o Comando Geral e o fotógrafo.
        'fotos.gerenciar',
        'fotos.vender',
        // Meu painel (#79): instrutor e admin de equipe.
        'painel.instrutor',
        // Relatórios (#73): quem tem pelo menos um relatório.
        'relatorios.acessar',
        // Minha carteirinha (#46): só filiados.
        'carteirinha.ver',
        // Fotos dos eventos (#90): o item aparece enquanto a loja de fotos estiver ligada.
        'fotos.eventos',
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
            // Número do suporte técnico no WhatsApp (só números), para o botão
            // "Suporte técnico". null = sem número no .env (o botão não aparece).
            'suporte_whatsapp' => config('app.suporte_whatsapp') ?: null,
            // A versão no ar (#75): back, front e banco, para o rodapé e o console do navegador.
            // Ex.: ['numero' => '0.2.0', 'commit' => 'a1b2c3d', 'front_build' => '17/10/2026 03:12', 'banco' => [...]].
            'versao' => fn () => VersaoNoAr::tudo(),
            'auth' => [
                // A conta logada SEM relacionamentos carregados (LGPD, 16/10/2026): antes, se a
                // requisição já tinha consultado o filiado (as regras de permissão fazem isso), ele
                // ia junto, com dados pessoais. Os campos sensíveis da conta já são escondidos no User.
                'user' => $request->user()?->withoutRelations(),
            ],
            // Tour guiado (#68): o interruptor e as telas já vistas da pessoa logada.
            // Sem login: null (as telas públicas não abrem o tour sozinhas).
            'tour' => $request->user() ? [
                'ativo' => (bool) $request->user()->tour_ativo,
                'vistos' => $request->user()->tours_vistos ?? [],
            ] : null,
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
