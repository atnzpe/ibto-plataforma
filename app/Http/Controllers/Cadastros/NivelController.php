<?php

/*
 * Telas do cadastro de níveis (#37): lista, novo nível e edição.
 * Como nas áreas, não existe "apagar": desativar tira o nível das listas de
 * escolha. Só entra quem tem a permissão "cadastros.niveis" (routes/web.php).
 *
 * Níveis livres (18/10/2026): o Comando Geral cria níveis novos com nome
 * próprio (ex.: "Grão-Mestre"), escolhe depois de qual nível ele entra na
 * hierarquia e a letra do número de série do certificado de graduação.
 */

namespace App\Http\Controllers\Cadastros;

use App\Enums\CategoriaNivel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cadastros\NivelRequest;
use App\Models\Nivel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
        return Inertia::render('cadastros/niveis/index', [
            'niveis' => Nivel::emOrdem(Nivel::all())
                ->map(fn (Nivel $nivel) => $this->paraTela($nivel)),
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
            'posicoes' => $this->posicoes(null),
        ]);
    }

    /**
     * Grava o nível novo, encaixa na posição escolhida e volta para a lista.
     *
     * @param  NivelRequest  $request  O formulário, já conferido. Ex.: nome "Grão-Mestre", depois de "Instrutor Expert V".
     * @return RedirectResponse Volta para a lista com a mensagem de sucesso.
     */
    public function store(NivelRequest $request): RedirectResponse
    {
        // Tudo junto ou nada: gravar e renumerar a ordem não podem ficar pela metade.
        $nivel = DB::transaction(function () use ($request) {
            $nivel = Nivel::create([...$this->dados($request), 'posicao' => $this->posicaoEscolhida($request, null)]);
            Nivel::renumerar();

            return $nivel;
        });

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
            'posicoes' => $this->posicoes($nivel),
        ]);
    }

    /**
     * Grava as mudanças (nome, letra, posição, ativar ou desativar) e volta para a lista.
     *
     * @param  NivelRequest  $request  O formulário, já conferido.
     * @param  Nivel  $nivel  O nível que está sendo editado.
     * @return RedirectResponse Volta para a lista com a mensagem de sucesso.
     */
    public function update(NivelRequest $request, Nivel $nivel): RedirectResponse
    {
        DB::transaction(function () use ($request, $nivel) {
            $nivel->update([...$this->dados($request), 'posicao' => $this->posicaoEscolhida($request, $nivel)]);
            Nivel::renumerar();
        });

        return to_route('cadastros.niveis.index')
            ->with('sucesso', "Nível \"{$nivel->fresh()?->nome()}\" atualizado.");
    }

    /**
     * Dados conferidos: grau e nome vazios viram null; a letra fica maiúscula.
     *
     * @return array{categoria: string, grau: int|null, nome_personalizado: string|null, letra: string|null, ativo: bool}
     */
    private function dados(NivelRequest $request): array
    {
        return [
            'categoria' => $request->string('categoria')->toString(),
            'grau' => $request->filled('grau') ? $request->integer('grau') : null,
            'nome_personalizado' => $request->filled('nome_personalizado') ? trim($request->string('nome_personalizado')->toString()) : null,
            'letra' => $request->filled('letra') ? strtoupper($request->string('letra')->toString()) : null,
            'ativo' => $request->boolean('ativo'),
        ];
    }

    /**
     * A posição (número da ordem) que o nível ganha, conforme o campo "depois de".
     * Ex.: depois do nível de posição 130 → 131 (a renumeração arruma para 140).
     *
     * @param  NivelRequest  $request  O formulário. 'inicio' = o mais baixo; vazio = não muda.
     * @param  Nivel|null  $nivel  O nível em edição, ou null quando é novo.
     * @return int A posição provisória.
     */
    private function posicaoEscolhida(NivelRequest $request, ?Nivel $nivel): int
    {
        $depoisDe = $request->string('depois_de')->toString();

        // 'inicio': antes de todos (0 é menor que o primeiro, que é 10).
        if ($depoisDe === 'inicio') {
            return 0;
        }

        // Um nível escolhido: logo depois dele.
        if ($depoisDe !== '') {
            return (int) Nivel::whereKey((int) $depoisDe)->value('posicao') + 1;
        }

        // Campo vazio na edição: o nível fica onde está.
        if ($nivel !== null) {
            return $nivel->posicao;
        }

        // Campo vazio num nível novo: vai para o fim (o mais alto).
        return (int) Nivel::max('posicao') + 10;
    }

    /**
     * Só o que as telas usam.
     *
     * @return array{id: int, categoria: string, grau: int|null, nome: string, nome_personalizado: string|null, letra: string, letra_escolhida: string|null, ativo: bool}
     */
    private function paraTela(Nivel $nivel): array
    {
        return [
            'id' => $nivel->id,
            'categoria' => $nivel->categoria->value,
            'grau' => $nivel->grau,
            'nome' => $nivel->nome(),
            'nome_personalizado' => $nivel->nome_personalizado,
            // A letra que vale (escolhida ou da categoria), para a lista.
            'letra' => $nivel->letraSerie(),
            // Só a escolhida no cadastro (vazia = usa a da categoria), para o formulário.
            'letra_escolhida' => $nivel->letra,
            'ativo' => $nivel->ativo,
        ];
    }

    /**
     * As opções do campo "Fica depois de": o início e cada nível, na ordem.
     * Na edição, o próprio nível sai da lista e vem marcado quem está antes dele.
     *
     * @param  Nivel|null  $nivel  O nível em edição, ou null quando é novo.
     * @return array{opcoes: list<array{valor: string, rotulo: string}>, atual: string}
     *                                                                                  Ex.: atual '12' = hoje fica depois do nível 12.
     */
    private function posicoes(?Nivel $nivel): array
    {
        $ordem = Nivel::emOrdem(Nivel::all());
        $opcoes = [['valor' => 'inicio', 'rotulo' => 'No início (o nível mais baixo)']];
        // Quem vem logo antes do nível em edição (para o campo já vir marcado).
        $atual = '';
        $anterior = 'inicio';

        foreach ($ordem as $item) {
            // O próprio nível não pode ficar "depois de si mesmo".
            if ($nivel !== null && $item->id === $nivel->id) {
                $atual = $anterior;

                continue;
            }
            $opcoes[] = ['valor' => (string) $item->id, 'rotulo' => "Depois de {$item->nome()}"];
            $anterior = (string) $item->id;
        }

        // Nível novo: a sugestão é o fim da lista (o mais alto).
        if ($nivel === null) {
            $atual = $anterior;
        }

        return ['opcoes' => $opcoes, 'atual' => $atual];
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
