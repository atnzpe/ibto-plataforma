<?php

/*
 * BIBLIOTECA DE TÉCNICAS para quem estuda (#107): monitores, instrutores e o
 * Comando Geral revisam as técnicas (vídeo e apostila) antes de passar ao aluno.
 *
 * Telas e arquivos:
 *   - a lista, com filtro por área e por nível e busca pelo nome;
 *   - a ficha de um material, com o vídeo e o botão da apostila;
 *   - o vídeo enviado e a apostila, que só saem por aqui (nunca por link público).
 * Quem entra: Gate "biblioteca.ver" (na rota). Cada material confere ainda o
 * Gate "biblioteca.material" (ativo e liberado para o nível da pessoa): sem isso,
 * 403, mesmo com o link copiado de outra pessoa (critério de aceite da #107).
 */

namespace App\Http\Controllers\Biblioteca;

use App\Http\Controllers\Controller;
use App\Models\AreaAtuacao;
use App\Models\BibliotecaMaterial;
use App\Models\Nivel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BibliotecaController extends Controller
{
    /**
     * A lista: só os materiais ATIVOS e LIBERADOS para o nível da pessoa, com os
     * filtros escolhidos. Ex.: /biblioteca?area=2&nivel=5&busca=faca
     *
     * @param  Request  $request  Os filtros: area (id), nivel (id) e busca (texto).
     * @return Response A tela "biblioteca/index".
     */
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        // O que a pessoa digitou na busca, sem espaços nas pontas.
        $busca = trim((string) $request->query('busca', ''));
        // Filtros de área e de nível: só números; outra coisa = sem filtro.
        $area = filter_var($request->query('area'), FILTER_VALIDATE_INT) ?: null;
        $nivel = filter_var($request->query('nivel'), FILTER_VALIDATE_INT) ?: null;

        $consulta = BibliotecaMaterial::query()->ativos()->with(['area', 'nivelMinimo'])->orderBy('titulo');
        // Com busca: nome OU explicação contém o texto. Sem busca: todos.
        if ($busca !== '') {
            $consulta->where(fn ($onde) => $onde->where('titulo', 'like', '%'.$busca.'%')
                ->orWhere('descricao', 'like', '%'.$busca.'%'));
        }
        // Filtro de área: os da área escolhida E os que valem para todas as áreas.
        if ($area !== null) {
            $consulta->where(fn ($onde) => $onde->where('area_atuacao_id', $area)->orWhereNull('area_atuacao_id'));
        }
        // Filtro de nível: só os materiais daquele nível exato.
        if ($nivel !== null) {
            $consulta->where('nivel_minimo_id', $nivel);
        }

        // Tira os que a pessoa não pode ver (o nível é conferido no PHP, pela
        // hierarquia livre dos níveis). Até 500 por vez (a biblioteca do piloto é pequena).
        $materiais = $consulta->limit(500)->get()
            ->filter(fn (BibliotecaMaterial $material) => Gate::forUser($usuario)->allows('biblioteca.material', $material))
            ->values();

        return Inertia::render('biblioteca/index', [
            'filtros' => ['busca' => $busca, 'area' => $area, 'nivel' => $nivel],
            'materiais' => $materiais->map(fn (BibliotecaMaterial $material) => [
                'id' => $material->id,
                'titulo' => $material->titulo,
                // Só o começo da explicação, para o cartão (a ficha mostra tudo).
                'resumo' => $material->descricao !== null ? Str::limit($material->descricao, 160) : null,
                'area' => $material->area?->nome,
                'nivel' => $material->nivelMinimo?->nome(),
                'tem_video' => $material->video() !== null,
                'tem_apostila' => $material->apostila !== null,
            ]),
            'areas' => AreaAtuacao::query()->where('ativa', true)->orderBy('nome')->get(['id', 'nome']),
            'niveis' => Nivel::emOrdem(Nivel::ativos()->get())->map(fn (Nivel $item) => ['id' => $item->id, 'nome' => $item->nome()])->values(),
            // O nível da pessoa, para a tela explicar por que alguns materiais não aparecem.
            'meu_nivel' => $usuario?->filiado?->nivel?->nome(),
        ]);
    }

    /**
     * A ficha de um material: explicação, vídeo e apostila.
     *
     * @param  BibliotecaMaterial  $material  O material do endereço (ex.: /biblioteca/3).
     * @return Response A tela "biblioteca/material".
     */
    public function show(BibliotecaMaterial $material): Response
    {
        // Retirado ou acima do nível da pessoa: 403 (não mostra nada do material).
        Gate::authorize('biblioteca.material', $material);

        return Inertia::render('biblioteca/material', [
            'material' => [
                'id' => $material->id,
                'titulo' => $material->titulo,
                'descricao' => $material->descricao,
                'area' => $material->area?->nome,
                'nivel' => $material->nivelMinimo?->nome(),
                // Como tocar: YouTube (sem cookies), arquivo por link ou vídeo enviado.
                'video' => $material->video(),
                // Endereço da apostila (rota protegida); null = não tem apostila.
                'apostila_url' => $material->apostila !== null ? route('biblioteca.apostila', $material) : null,
            ],
        ]);
    }

    /**
     * Baixa a apostila (PDF) do material, com um nome limpo.
     *
     * @param  BibliotecaMaterial  $material  O material (ex.: /biblioteca/3/apostila).
     * @return StreamedResponse O PDF. Ex.: 'defesa-contra-faca.pdf'.
     */
    public function apostila(BibliotecaMaterial $material): StreamedResponse
    {
        Gate::authorize('biblioteca.material', $material);

        $disco = Storage::disk('local');
        // Sem apostila cadastrada, ou o arquivo sumiu do disco: 404.
        abort_if($material->apostila === null || ! $disco->exists($material->apostila), 404);

        return $disco->download($material->apostila, Str::slug($material->titulo).'.pdf');
    }

    /**
     * Toca o vídeo enviado. A resposta aceita "pedaços" (Range), então o player
     * consegue avançar e voltar sem baixar o vídeo inteiro.
     *
     * @param  BibliotecaMaterial  $material  O material (ex.: /biblioteca/3/video).
     * @return BinaryFileResponse O vídeo, sem guardar em cache público.
     */
    public function video(BibliotecaMaterial $material): BinaryFileResponse
    {
        Gate::authorize('biblioteca.material', $material);

        $disco = Storage::disk('local');
        // Sem vídeo enviado, ou o arquivo sumiu do disco: 404.
        abort_if($material->video_arquivo === null || ! $disco->exists($material->video_arquivo), 404);

        // response()->file entrega por pedaços (Range) quando o navegador pede.
        return response()->file($disco->path($material->video_arquivo), [
            // "private": só o navegador da pessoa guarda; nenhum servidor no meio.
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
