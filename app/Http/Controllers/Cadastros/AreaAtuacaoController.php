<?php

/*
 * Telas do cadastro de áreas de atuação (#37): lista, nova área e edição,
 * com a imagem de cada área (pedido do Gleyson em 07/10/2026).
 *
 * Não existe "apagar": desativar (ativa = false) tira a área das listas de
 * escolha sem deixar equipes e filiados antigos "órfãos". Quem pode abrir
 * estas telas é decidido nas rotas (permissão "cadastros.areas", em routes/web.php).
 */

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cadastros\AreaAtuacaoRequest;
use App\Models\AreaAtuacao;
use App\Support\ImagemPublica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class AreaAtuacaoController extends Controller
{
    // Pasta (dentro de storage/app/public) onde ficam as imagens das áreas.
    private const PASTA_IMAGENS = 'areas-atuacao';

    /**
     * Lista de todas as áreas (ativas e desativadas), em ordem de nome.
     *
     * @return Response A tela "cadastros/areas-atuacao/index" com as áreas.
     */
    public function index(): Response
    {
        return Inertia::render('cadastros/areas-atuacao/index', [
            'areas' => AreaAtuacao::query()->orderBy('nome')->get()
                ->map(fn (AreaAtuacao $area) => $this->paraTela($area)),
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
     * Grava a área nova (e a imagem, se veio) e volta para a lista.
     *
     * @param  AreaAtuacaoRequest  $request  O formulário, já conferido. Ex.: nome "Tiro Defensivo", ativa true, imagem tiro.webp.
     * @return RedirectResponse Volta para a lista com a mensagem de sucesso.
     */
    public function store(AreaAtuacaoRequest $request): RedirectResponse
    {
        // Só nome e ativa vão direto para o banco; a imagem é tratada à parte.
        $area = AreaAtuacao::create($request->safe()->only(['nome', 'ativa']));

        $this->guardarImagem($area, $request->file('imagem'));

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
            'area' => $this->paraTela($area),
        ]);
    }

    /**
     * Grava as mudanças (inclusive ativar, desativar e trocar a imagem) e volta para a lista.
     *
     * @param  AreaAtuacaoRequest  $request  O formulário, já conferido.
     * @param  AreaAtuacao  $area  A área que está sendo editada.
     * @return RedirectResponse Volta para a lista com a mensagem de sucesso.
     */
    public function update(AreaAtuacaoRequest $request, AreaAtuacao $area): RedirectResponse
    {
        $area->update($request->safe()->only(['nome', 'ativa']));

        $this->guardarImagem($area, $request->file('imagem'));

        return to_route('cadastros.areas-atuacao.index')
            ->with('sucesso', "Área \"{$area->nome}\" atualizada.");
    }

    /**
     * Guarda a imagem nova da área e apaga a antiga (regra comum em App\Support\ImagemPublica).
     *
     * @param  AreaAtuacao  $area  A área dona da imagem.
     * @param  UploadedFile|null  $imagem  O arquivo enviado, ou null se a pessoa não escolheu imagem.
     */
    private function guardarImagem(AreaAtuacao $area, ?UploadedFile $imagem): void
    {
        ImagemPublica::trocar($area, 'imagem', self::PASTA_IMAGENS, $imagem);
    }

    /**
     * Só os dados que as telas usam (nada de datas internas nem caminho de arquivo).
     *
     * @param  AreaAtuacao  $area  A área. Ex.: Krav Maga.
     * @return array{id: int, nome: string, ativa: bool, imagem_url: string|null}
     */
    private function paraTela(AreaAtuacao $area): array
    {
        return [
            'id' => $area->id,
            'nome' => $area->nome,
            'ativa' => $area->ativa,
            'imagem_url' => $area->imagemUrl(),
        ];
    }
}
