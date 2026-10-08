<?php

/*
 * Telas do cadastro de áreas de atuação (#37): lista, nova área e edição.
 *
 * Não existe "apagar": desativar (ativa = false) tira a área das listas de
 * escolha sem deixar equipes e filiados antigos "órfãos". Quem pode abrir
 * estas telas é decidido nas rotas (Gate "administrar", em routes/web.php).
 */

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cadastros\AreaAtuacaoRequest;
use App\Models\AreaAtuacao;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AreaAtuacaoController extends Controller
{
    /**
     * Lista de todas as áreas (ativas e desativadas), em ordem de nome.
     *
     * @return Response A tela "cadastros/areas-atuacao/index" com as áreas.
     */
    public function index(): Response
    {
        return Inertia::render('cadastros/areas-atuacao/index', [
            // Só os campos que a tela usa (nada de datas internas).
            'areas' => AreaAtuacao::query()->orderBy('nome')->get(['id', 'nome', 'ativa']),
        ]);
    }

    /**
     * Formulário vazio para cadastrar uma área nova.
     *
     * @return Response A tela do formulário, sem área (area = null).
     */
    public function create(): Response
    {
        return Inertia::render('cadastros/areas-atuacao/formulario', [
            'area' => null,
        ]);
    }

    /**
     * Grava a área nova e volta para a lista com a mensagem de sucesso.
     *
     * @param  AreaAtuacaoRequest  $request  O formulário, já conferido. Ex.: nome "Tiro Defensivo", ativa true.
     * @return RedirectResponse Volta para a lista.
     */
    public function store(AreaAtuacaoRequest $request): RedirectResponse
    {
        // validated(): só os campos conferidos pelas regras (nome e ativa).
        $area = AreaAtuacao::create($request->validated());

        return to_route('cadastros.areas-atuacao.index')
            ->with('sucesso', "Área \"{$area->nome}\" cadastrada.");
    }

    /**
     * Formulário preenchido para editar uma área.
     *
     * @param  AreaAtuacao  $area  A área do endereço (ex.: /cadastros/areas-atuacao/4/edit = área 4).
     * @return Response A tela do formulário com os dados da área.
     */
    public function edit(AreaAtuacao $area): Response
    {
        return Inertia::render('cadastros/areas-atuacao/formulario', [
            'area' => $area->only(['id', 'nome', 'ativa']),
        ]);
    }

    /**
     * Grava as mudanças (inclusive ativar ou desativar) e volta para a lista.
     *
     * @param  AreaAtuacaoRequest  $request  O formulário, já conferido.
     * @param  AreaAtuacao  $area  A área que está sendo editada.
     * @return RedirectResponse Volta para a lista.
     */
    public function update(AreaAtuacaoRequest $request, AreaAtuacao $area): RedirectResponse
    {
        $area->update($request->validated());

        return to_route('cadastros.areas-atuacao.index')
            ->with('sucesso', "Área \"{$area->nome}\" atualizada.");
    }
}
