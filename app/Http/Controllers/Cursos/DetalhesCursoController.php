<?php

/*
 * DETALHES DO CURSO no Comando Geral (tela 3 do desenho aprovado pelo
 * Gleyson em 10/10/2026): o resumo vagas → pagamento → presença →
 * certificado e a lista dos INSCRITOS com o próximo passo de cada um.
 *
 * LGPD (decisão de 10/10/2026): a lista mostra só o REGISTRO (ou o nº da
 * matrícula) e a equipe; o nome fica na ficha da matrícula.
 * Pagamento e vaga são UM passo só (confirmar o pagamento já confirma a vaga).
 * Presença (#44): os encontros com o botão da chamada e o "x de y" de cada
 * inscrito. O certificado entra com a #44 (aparece como "em breve").
 * Quem vê: Gate "curso.inscritos" (Comando Geral, quem chancela, quem
 * confirma pagamentos e o dono do curso).
 */

namespace App\Http\Controllers\Cursos;

use App\Actions\Cursos\Certificados;
use App\Enums\SituacaoMatricula;
use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoMatricula;
use App\Models\CursoPresenca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DetalhesCursoController extends Controller
{
    /**
     * A tela de detalhes de um curso.
     *
     * @param  Request  $request  Quem está vendo.
     * @param  Curso  $curso  O do endereço. Ex.: /comando-geral/cursos/7/inscritos
     * @param  Certificados  $certificados  As regras do certificado (quem está apto).
     * @return Response A tela "cursos/detalhes".
     */
    public function __invoke(Request $request, Curso $curso, Certificados $certificados): Response
    {
        Gate::authorize('curso.inscritos', $curso);

        // Todas as matrículas do curso (canceladas também: o histórico aparece), as mais novas primeiro.
        $matriculas = $curso->matriculas()
            ->with(['usuario.filiado:id,user_id,registro,equipe_id', 'usuario.filiado.equipe:id,nome', 'certificado'])
            ->latest('id')
            ->get();

        // Quantas em cada situação. Ex.: ['aguardando_pagamento' => 2, 'confirmada' => 12].
        $porSituacao = $matriculas->countBy(fn (CursoMatricula $matricula) => $matricula->situacao->value);
        $ocupadas = ($porSituacao[SituacaoMatricula::AguardandoPagamento->value] ?? 0) + ($porSituacao[SituacaoMatricula::Confirmada->value] ?? 0);

        // Os ENCONTROS (aulas ativas com data, em módulos ativos), na ordem do dia, para a chamada (#44).
        $encontros = CursoAula::query()
            ->where('ativa', true)
            ->whereNotNull('data')
            ->whereHas('modulo', fn ($modulo) => $modulo->where('curso_id', $curso->id)->where('ativo', true))
            ->orderBy('data')->orderBy('hora_inicio')
            ->get();
        // Presenças marcadas em todos os encontros do curso: [matricula_id => quantas presenças].
        $presencasPorMatricula = CursoPresenca::query()
            ->whereIn('aula_id', $encontros->modelKeys())
            ->where('presente', true)
            ->selectRaw('matricula_id, count(*) as total')
            ->groupBy('matricula_id')
            ->pluck('total', 'matricula_id');
        // Os encontros que já tiveram chamada (a base do "x de y").
        $chamados = $encontros->whereNotNull('chamada_feita_em');
        // Presentes na ÚLTIMA chamada feita (o número do passo 3 do resumo).
        $ultimaChamada = $chamados->sortByDesc('data')->first();
        $presentesNaUltima = $ultimaChamada !== null
            ? CursoPresenca::where('aula_id', $ultimaChamada->id)->where('presente', true)->count()
            : null;

        // Só quem confirma pagamentos abre a ficha (nome e e-mail).
        $podeConfirmar = (bool) $request->user()?->can('pagamentos.confirmar');

        return Inertia::render('cursos/detalhes', [
            'curso' => [
                'id' => $curso->id,
                'titulo' => $curso->titulo,
                'modalidade' => $curso->modalidade->rotulo(),
                'data_inicio' => $curso->data_inicio?->toDateString(),
                'local' => $curso->local,
                'situacao_rotulo' => $curso->situacao->rotulo(),
                'pode_editar' => Gate::allows('curso.editar', $curso),
            ],
            'resumo' => [
                'vagas' => $curso->vagas,
                'ocupadas' => $ocupadas,
                'aguardando' => $porSituacao[SituacaoMatricula::AguardandoPagamento->value] ?? 0,
                'confirmadas' => $porSituacao[SituacaoMatricula::Confirmada->value] ?? 0,
                'canceladas' => $porSituacao[SituacaoMatricula::Cancelada->value] ?? 0,
                // Presentes na última chamada (null = nenhuma chamada ainda) e qual encontro foi.
                'presentes_ultima' => $presentesNaUltima,
                'ultima_chamada' => $ultimaChamada?->titulo,
                // Certificados válidos já emitidos (passo 4).
                'certificados' => $matriculas->filter(fn (CursoMatricula $matricula) => $matricula->certificado?->valido() ?? false)->count(),
            ],
            'inscritos' => $matriculas->map(fn (CursoMatricula $matricula) => [
                'id' => $matricula->id,
                // LGPD: registro do filiado; não filiado = só o nº da matrícula (o nome fica na ficha).
                'registro' => $matricula->usuario->filiado?->registro,
                // Foto de perfil (#78), só se quem está vendo pode ver a foto desta pessoa; senão null.
                'foto' => Gate::allows('usuario.foto', $matricula->usuario) ? $matricula->usuario->avatar : null,
                'equipe' => $matricula->usuario->filiado !== null ? ($matricula->usuario->filiado->equipe->nome ?? 'Sem equipe') : 'Não filiado',
                'situacao' => $matricula->situacao->value,
                'situacao_rotulo' => $matricula->situacao->rotulo(),
                'tem_comprovante' => $matricula->comprovante !== null,
                // Presenças: quantas vezes veio, de quantos encontros com chamada. Ex.: 2 de 3.
                'presencas' => (int) ($presencasPorMatricula[$matricula->id] ?? 0),
                'encontros_com_chamada' => $chamados->count(),
                // Certificado (#44): o código (null = não emitido) e se ainda vale.
                'certificado' => $matricula->certificado?->codigo,
                'certificado_id' => $matricula->certificado?->id,
                'certificado_valido' => $matricula->certificado?->valido() ?? false,
                // Apto para receber (só para quem não tem): null = não se aplica.
                'motivo_nao_apto' => $matricula->certificado === null && $matricula->situacao === SituacaoMatricula::Confirmada
                    ? $certificados->apto($matricula)['motivo']
                    : null,
            ])->values(),
            'pode_confirmar' => $podeConfirmar,
            // Os encontros e se a pessoa pode fazer a chamada (Gate curso.chamada).
            'encontros' => $encontros->map(fn (CursoAula $aula) => [
                'id' => $aula->id,
                'titulo' => $aula->titulo,
                'data' => $aula->data?->toDateString(),
                'hora_inicio' => $aula->hora_inicio !== null ? substr($aula->hora_inicio, 0, 5) : null,
                'chamada_feita' => $aula->chamada_feita_em !== null,
            ])->values(),
            'pode_chamada' => Gate::allows('curso.chamada', $curso),
            // Emitir e cancelar certificados: só o IBTO (Gate certificados.emitir).
            'pode_emitir' => Gate::allows('certificados.emitir'),
            // "Matricular pessoa" (14/10/2026): só o IBTO (cursos.gerenciar); false = o formulário nem aparece.
            'pode_matricular' => Gate::allows('cursos.gerenciar'),
            // true = curso FECHADO: a tela explica que só entra quem o IBTO matricular.
            'fechado' => $curso->fechado,
            // Fotos do curso (17/10/2026): o instrutor responsável (ou o Comando Geral) cuida delas.
            'pode_fotos' => Gate::allows('curso.instrutor', $curso),
        ]);
    }
}
