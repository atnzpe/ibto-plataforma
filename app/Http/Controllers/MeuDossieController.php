<?php

/*
 * MEU DOSSIÊ (esboço do Gleyson de 08/10/2026: "todo filiado tem um dossiê";
 * desenho aprovado em 10/10/2026): o HISTÓRICO da pessoa no IBTO, num lugar só.
 * - Resumo: cursos concluídos (com certificado válido), horas certificadas e
 *   presenças nos encontros;
 * - Graduação: o nível atual (o histórico de graduações chega com a #46);
 * - Certificados: todos, válidos e cancelados (com o código para validar);
 * - Cursos: cada curso confirmado, com os ENCONTROS (dia, presente ou falta e
 *   o que foi trabalhado, registrado na chamada) e o progresso das aulas online.
 *
 * Quem vê: só a própria pessoa (a rota pega quem está logado; não existe
 * número de pessoa no endereço, então não dá para abrir o dossiê de outra).
 * LGPD: nada de CPF, nascimento ou endereço aqui.
 */

namespace App\Http\Controllers;

use App\Enums\SituacaoMatricula;
use App\Models\CursoAula;
use App\Models\CursoCertificado;
use App\Models\CursoMatricula;
use App\Models\CursoPresenca;
use App\Models\User;
use App\Services\SalaDeAula;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeuDossieController extends Controller
{
    /**
     * A tela do dossiê de quem está logado.
     *
     * @param  Request  $request  Quem está logado.
     * @param  SalaDeAula  $sala  As regras da sala de aula (progresso das aulas online).
     * @return Response A tela "meu-dossie".
     */
    public function __invoke(Request $request, SalaDeAula $sala): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $filiado = $usuario->filiado()->with(['equipe', 'nivel'])->first();

        // Os cursos do dossiê: matrículas CONFIRMADAS (as que viraram curso de
        // verdade), com o curso e o certificado, as mais novas primeiro.
        // Aguardando pagamento e canceladas não entram: ainda não são histórico.
        $matriculas = CursoMatricula::query()
            ->where('user_id', $usuario->id)
            ->where('situacao', SituacaoMatricula::Confirmada)
            ->with(['curso', 'certificado'])
            ->latest('id')
            ->get();

        // Os certificados da pessoa (válidos e cancelados), os mais novos primeiro.
        $certificados = CursoCertificado::query()
            ->whereIn('matricula_id', CursoMatricula::where('user_id', $usuario->id)->select('id'))
            ->latest('emitido_em')
            ->get();
        // Só os válidos contam no resumo (cancelado deixou de valer).
        $validos = $certificados->filter(fn (CursoCertificado $certificado) => $certificado->valido());

        // Os cursos, cada um com os seus encontros e o progresso online.
        $cursos = $matriculas->map(fn (CursoMatricula $matricula) => $this->curso($matricula, $usuario, $sala))->values();

        return Inertia::render('meu-dossie', [
            'pessoa' => [
                'nome' => $usuario->name,
                // Só filiado tem registro, equipe, categoria e nível.
                'filiado' => $filiado !== null,
                'registro' => $filiado?->registro,
                'categoria' => $filiado?->categoria?->rotulo(),
                'equipe' => $filiado?->equipe?->nome,
                // Logo da equipe; null = mostrar o emblema do IBTO.
                'logo_url' => $filiado?->equipe?->logoUrl(),
                // A graduação atual escrita. Ex.: 'Monitor II' (null = sem nível).
                'nivel' => $filiado?->nivel?->nome(),
            ],
            'resumo' => [
                // Cursos concluídos = certificados válidos. Ex.: 3.
                'concluidos' => $validos->count(),
                // Soma da carga horária dos certificados válidos (sem carga = 0). Ex.: 40.
                'horas' => (int) $validos->sum(fn (CursoCertificado $certificado) => $certificado->carga_horaria ?? 0),
                // Presenças em encontros, somando todos os cursos. Ex.: 9.
                'presencas' => $cursos->sum('presencas'),
            ],
            'certificados' => $certificados->map(fn (CursoCertificado $certificado) => [
                'codigo' => $certificado->codigo,
                'curso' => $certificado->curso,
                'carga_horaria' => $certificado->carga_horaria,
                'concluido_em' => $certificado->concluido_em->toDateString(),
                'valido' => $certificado->valido(),
            ])->values(),
            'cursos' => $cursos,
        ]);
    }

    /**
     * Um curso do dossiê: os encontros (com a presença e o que foi trabalhado)
     * e o progresso das aulas online.
     *
     * @param  CursoMatricula  $matricula  A matrícula confirmada. Ex.: a do "Combate Corpo a Corpo".
     * @param  User  $usuario  Quem está logado.
     * @param  SalaDeAula  $sala  As regras da sala de aula.
     * @return array<string, mixed> Os dados do cartão do curso.
     */
    private function curso(CursoMatricula $matricula, User $usuario, SalaDeAula $sala): array
    {
        $curso = $matricula->curso;

        // Os ENCONTROS: aulas ativas, com data, em módulos ativos, na ordem do dia.
        $encontros = CursoAula::query()
            ->where('ativa', true)
            ->whereNotNull('data')
            ->whereHas('modulo', fn ($modulo) => $modulo->where('curso_id', $curso->id)->where('ativo', true))
            ->orderBy('data')->orderBy('hora_inicio')
            ->get();

        // A presença desta matrícula em cada encontro: [aula_id => true/false].
        $presencas = CursoPresenca::query()
            ->where('matricula_id', $matricula->id)
            ->pluck('presente', 'aula_id');

        // Progresso online só para curso com aulas que a pessoa pode ver.
        $progresso = $sala->progresso($curso, $usuario);

        return [
            'titulo' => $curso->titulo,
            'modalidade' => $curso->modalidade->rotulo(),
            'carga_horaria' => $curso->carga_horaria,
            // O código do certificado VÁLIDO deste curso (null = ainda não tem).
            'certificado' => $matricula->certificado?->valido() ? $matricula->certificado->codigo : null,
            // Quantas presenças e em quantos encontros já houve chamada. Ex.: 3 de 4.
            'presencas' => $presencas->filter()->count(),
            'encontros_com_chamada' => $encontros->whereNotNull('chamada_feita_em')->count(),
            'encontros' => $encontros->map(fn (CursoAula $aula) => [
                'id' => $aula->id,
                'titulo' => $aula->titulo,
                'data' => $aula->data?->toDateString(),
                // 'presente' | 'falta' | 'sem_chamada' (a chamada ainda não foi feita).
                'presenca' => $aula->chamada_feita_em === null
                    ? 'sem_chamada'
                    : (($presencas[$aula->id] ?? false) ? 'presente' : 'falta'),
                // O que foi trabalhado no encontro (escrito na chamada). Só depois da chamada.
                'trabalhado' => $aula->chamada_feita_em !== null ? $aula->registro_encontro : null,
            ])->values(),
            // null = curso sem aulas online para esta pessoa (ex.: só presencial).
            'progresso' => $progresso['total'] > 0
                ? ['assistidas' => $progresso['assistidas'], 'total' => $progresso['total'], 'percentual' => $progresso['percentual']]
                : null,
        ];
    }
}
