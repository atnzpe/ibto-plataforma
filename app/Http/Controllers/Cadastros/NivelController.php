<?php

/*
 * Telas do cadastro de níveis (#37): lista, novo nível e edição.
 * Como nas áreas, não existe "apagar": desativar tira o nível das listas de
 * escolha. Só entra quem tem a permissão "cadastros.niveis" (routes/web.php).
 */

namespace App\Http\Controllers\Cadastros;

use App\Enums\CategoriaNivel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cadastros\NivelRequest;
use App\Models\Nivel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NivelController extends Controller
{
    /**
     * Lista de todos os níveis, na ordem da hierarquia.
     *
     * @return Response A tela "cadastros/niveis/index".
     */
    public function index(): Response
    {
        // A hierarquia é FECHADA (18/10/2026): Praticante + Monitor I–II + Instrutor I–V +
        // Instrutor Expert I–V = 13 níveis, todos criados pela migração. Conta quantos
        // ainda faltam (só falta algum se alguém apagou direto no banco).
        $possiveis = array_sum(array_map(fn (CategoriaNivel $categoria) => max(1, $categoria->grauMaximo()), CategoriaNivel::cases()));

        return Inertia::render('cadastros/niveis/index', [
            'niveis' => Nivel::emOrdem(Nivel::all())
                ->map(fn (Nivel $nivel) => $this->paraTela($nivel)),
            // 0 = a hierarquia está completa: a tela esconde o "Novo nível" e explica.
            'faltando' => max(0, $possiveis - Nivel::count()),
        ]);
    }

    /**
     * Formulário vazio para cadastrar um nível novo.
     *
     * @return Response A tela do formulário, sem nível (nivel = null).
     */
    public function create(): Response
    {
        return Inertia::render('cadastros/niveis/formulario', [
            'nivel' => null,
            'categorias' => $this->categorias(),
        ]);
    }

    /**
     * Grava o nível novo e volta para a lista.
     *
     * @param  NivelRequest  $request  O formulário, já conferido. Ex.: categoria "Monitor", grau 2.
     * @return RedirectResponse Volta para a lista com a mensagem de sucesso.
     */
    public function store(NivelRequest $request): RedirectResponse
    {
        $nivel = Nivel::create($this->dados($request));

        return to_route('cadastros.niveis.index')
            ->with('sucesso', "Nível \"{$nivel->nome()}\" cadastrado.");
    }

    /**
     * Formulário preenchido para editar um nível.
     *
     * @param  Nivel  $nivel  O nível do endereço (ex.: /cadastros/niveis/3/edit = nível 3).
     * @return Response A tela do formulário com os dados do nível.
     */
    public function edit(Nivel $nivel): Response
    {
        return Inertia::render('cadastros/niveis/formulario', [
            'nivel' => $this->paraTela($nivel),
            'categorias' => $this->categorias(),
        ]);
    }

    /**
     * Grava as mudanças (inclusive ativar ou desativar) e volta para a lista.
     *
     * @param  NivelRequest  $request  O formulário, já conferido.
     * @param  Nivel  $nivel  O nível que está sendo editado.
     * @return RedirectResponse Volta para a lista com a mensagem de sucesso.
     */
    public function update(NivelRequest $request, Nivel $nivel): RedirectResponse
    {
        $nivel->update($this->dados($request));

        return to_route('cadastros.niveis.index')
            ->with('sucesso', "Nível \"{$nivel->nome()}\" atualizado.");
    }

    /**
     * Dados conferidos, com o grau vazio virando null (Praticante).
     *
     * @return array{categoria: string, grau: int|null, ativo: bool}
     */
    private function dados(NivelRequest $request): array
    {
        return [
            'categoria' => $request->string('categoria')->toString(),
            'grau' => $request->filled('grau') ? $request->integer('grau') : null,
            'ativo' => $request->boolean('ativo'),
        ];
    }

    /**
     * Só o que as telas usam.
     *
     * @return array{id: int, categoria: string, grau: int|null, nome: string, ativo: bool}
     */
    private function paraTela(Nivel $nivel): array
    {
        return [
            'id' => $nivel->id,
            'categoria' => $nivel->categoria->value,
            'grau' => $nivel->grau,
            'nome' => $nivel->nome(),
            'ativo' => $nivel->ativo,
        ];
    }

    /**
     * As categorias com o grau máximo de cada uma, para o formulário mostrar
     * só os graus permitidos (a conferência de verdade é no NivelRequest).
     *
     * @return list<array{valor: string, grau_maximo: int}>
     */
    private function categorias(): array
    {
        return array_map(
            fn (CategoriaNivel $categoria) => ['valor' => $categoria->value, 'grau_maximo' => $categoria->grauMaximo()],
            CategoriaNivel::cases(),
        );
    }
}
