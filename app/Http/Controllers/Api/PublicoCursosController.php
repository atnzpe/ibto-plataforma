<?php

/*
 * API PÚBLICA DOS CURSOS (#52): os cursos PUBLICADOS (chancelados) e que NÃO
 * são fechados, em JSON, para a página /cursos do site mostrar sozinha os
 * cursos da plataforma (sem precisar mudar o site a cada curso novo).
 * Só leitura, sem login; só o necessário para o cartão. O curso fechado
 * (ex.: Formação de instrutor) nunca aparece. Quem lê pelo navegador: só o
 * site do IBTO (config/cors.php).
 * Ex.: GET https://cursos.ib-to.org/api/publico/cursos
 */

namespace App\Http\Controllers\Api;

use App\Enums\AcessoCurso;
use App\Enums\SituacaoCurso;
use App\Http\Controllers\Controller;
use App\Models\Curso;
use Illuminate\Http\JsonResponse;

class PublicoCursosController extends Controller
{
    // No máximo quantos cursos uma resposta traz.
    private const MAXIMO = 60;

    /**
     * Os cursos publicados e abertos ao site, os com data mais próxima primeiro.
     *
     * @return JsonResponse {"cursos": [{titulo, chamada, modalidade, area, restrito, tipo, valor_centavos, data_inicio, local, carga_horaria, instrutor, imagem_url, url}]}
     */
    public function __invoke(): JsonResponse
    {
        $cursos = Curso::query()
            ->where('situacao', SituacaoCurso::Publicado)
            // Curso FECHADO não aparece no site (decisão de 14/10/2026).
            ->noSite()
            ->with('area:id,nome')
            // Os com data primeiro (do mais próximo), os sem data no fim.
            ->orderByRaw('data_inicio is null')->orderBy('data_inicio')->orderBy('titulo')
            ->limit(self::MAXIMO)->get()
            ->map(fn (Curso $curso) => [
                'titulo' => $curso->titulo,
                'chamada' => $curso->chamada,
                'modalidade' => $curso->modalidade->rotulo(),
                'area' => $curso->area?->nome,
                // true = "Exclusivo para filiados".
                'restrito' => $curso->acesso === AcessoCurso::Restrito,
                // Ex.: 'Pago · aberto ao público'.
                'tipo' => $curso->tipo(),
                'valor_centavos' => $curso->valor_centavos,
                'data_inicio' => $curso->data_inicio?->toDateString(),
                'local' => $curso->local,
                'carga_horaria' => $curso->carga_horaria,
                'instrutor' => $curso->instrutorExibido(),
                // Endereços COMPLETOS (o site está em outro domínio).
                'imagem_url' => $curso->imagemUrl(),
                'url' => route('catalogo.curso', $curso->id),
            ])->values();

        // Cache de 1 minuto (era 5; 16/10/2026): o site não consulta a plataforma a cada
        // visita, mas um curso cancelado some do site em até 1 minuto (com 5, ele ficava
        // aparecendo e o link dava 404). Blog, eventos e áreas mudam menos: seguem com 5.
        return response()->json(['cursos' => $cursos])
            ->header('Cache-Control', 'public, max-age=60');
    }
}
