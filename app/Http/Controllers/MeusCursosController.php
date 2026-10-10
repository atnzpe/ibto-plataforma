<?php

/*
 * MEUS CURSOS (desenho aprovado pelo Gleyson em 10/10/2026, tela 1 do aluno):
 * a página inicial de quem NÃO é da equipe do IBTO (aluno, filiado,
 * instrutor, admin de equipe).
 * - Cabeçalho com o LOGO DA EQUIPE do filiado (sem equipe ou não filiado:
 *   o emblema do IBTO), o nome, o registro e a categoria;
 * - TRILHA DE FORMAÇÃO: Praticante → Monitor → Instrutor → Instrutor Expert,
 *   com cada etapa "feita", "atual" ou "a seguir", pela categoria do filiado;
 * - os CURSOS da pessoa (matrículas aguardando pagamento ou confirmadas),
 *   com o próximo passo de cada um (pagar ou assistir).
 * Ficam para depois: a foto do filiado (upload com LGPD, #47) e o dossiê
 * (presenças e certificados, #44).
 */

namespace App\Http\Controllers;

use App\Enums\CategoriaFiliado;
use App\Enums\SituacaoMatricula;
use App\Models\CursoAula;
use App\Models\CursoMatricula;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeusCursosController extends Controller
{
    // As etapas da trilha de formação, em ordem (a função no IBTO, decisão de 09/10/2026).
    private const ETAPAS = [
        CategoriaFiliado::Praticante,
        CategoriaFiliado::Monitor,
        CategoriaFiliado::Instrutor,
        CategoriaFiliado::InstrutorExpert,
    ];

    /**
     * A página "Meus cursos".
     *
     * @param  Request  $request  Quem está logado.
     * @return Response A tela "meus-cursos".
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $filiado = $usuario->filiado()->with('equipe')->first();

        // As matrículas que valem (aguardando ou confirmadas), as mais novas primeiro.
        $matriculas = CursoMatricula::query()
            ->where('user_id', $usuario->id)
            ->ocupandoVaga()
            ->with('curso.area:id,nome')
            ->latest('id')
            ->get();

        return Inertia::render('meus-cursos', [
            'pessoa' => [
                'nome' => $usuario->name,
                // Só filiado tem registro, equipe e categoria.
                'filiado' => $filiado !== null,
                'registro' => $filiado?->registro,
                'categoria' => $filiado?->categoria?->rotulo(),
                'equipe' => $filiado?->equipe?->nome,
                // Logo da equipe; null = mostrar o emblema do IBTO.
                'logo_url' => $filiado?->equipe?->logoUrl(),
            ],
            'trilha' => $this->trilha($filiado?->categoria),
            'cursos' => $matriculas->map(fn (CursoMatricula $matricula) => [
                'matricula_id' => $matricula->id,
                'curso_id' => $matricula->curso_id,
                'titulo' => $matricula->curso->titulo,
                'imagem_url' => $matricula->curso->imagemUrl(),
                'modalidade' => $matricula->curso->modalidade->rotulo(),
                'area' => $matricula->curso->area?->nome,
                'data_inicio' => $matricula->curso->data_inicio?->toDateString(),
                'situacao' => $matricula->situacao->value,
                'situacao_rotulo' => $matricula->situacao->rotulo(),
                // Primeira aula liberada (para "Assistir"), só na matrícula confirmada.
                'primeira_aula_id' => $matricula->situacao === SituacaoMatricula::Confirmada
                    ? $this->primeiraAula($matricula->curso_id)
                    : null,
            ])->values(),
        ]);
    }

    /**
     * As etapas da trilha de formação com o estado de cada uma.
     *
     * @param  CategoriaFiliado|null  $atual  A categoria do filiado. Ex.: Monitor.
     * @return list<array{nome: string, estado: string}> estado: 'feita', 'atual' ou 'a_seguir'.
     */
    private function trilha(?CategoriaFiliado $atual): array
    {
        // Posição da categoria atual na trilha (false = fora dela: não filiado ou admin de equipe).
        $posicao = $atual !== null ? array_search($atual, self::ETAPAS, true) : false;

        $etapas = [];
        foreach (self::ETAPAS as $indice => $etapa) {
            $estado = match (true) {
                // Fora da trilha: tudo "a seguir".
                $posicao === false => 'a_seguir',
                $indice < $posicao => 'feita',
                $indice === $posicao => 'atual',
                default => 'a_seguir',
            };
            $etapas[] = ['nome' => $etapa->rotulo(), 'estado' => $estado];
        }

        return $etapas;
    }

    /**
     * A primeira aula ativa (em módulo ativo) do curso, ou null se não tem aula.
     *
     * @param  int  $cursoId  Ex.: 7
     * @return int|null O id da aula. Ex.: 31
     */
    private function primeiraAula(int $cursoId): ?int
    {
        $aula = CursoAula::query()
            ->where('ativa', true)
            ->whereHas('modulo', fn ($modulo) => $modulo->where('curso_id', $cursoId)->where('ativo', true))
            ->join('curso_modulos', 'curso_modulos.id', '=', 'curso_aulas.modulo_id')
            // Na ordem: módulo, depois aula.
            ->orderBy('curso_modulos.ordem')->orderBy('curso_aulas.ordem')->orderBy('curso_aulas.id')
            ->value('curso_aulas.id');

        return $aula !== null ? (int) $aula : null;
    }
}
