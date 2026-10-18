<?php

/*
 * CHAMADA DAS AULAS REGULARES (#80): o instrutor do programa (ou o Comando
 * Geral) vê as turmas dele, escolhe a data da aula e marca quem estava. Na
 * lista entram as assinaturas da turma que estão em dia ou na carência (quem
 * está travado não aparece: não está liberado para a aula). A frequência do mês
 * vai para o dossiê de cada aluno.
 */

namespace App\Http\Controllers\AulasRegulares;

use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\Auditoria;
use App\Models\Programa;
use App\Models\ProgramaAula;
use App\Models\ProgramaPresenca;
use App\Models\ProgramaTurma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChamadaRegularController extends Controller
{
    /**
     * As turmas que a pessoa conduz (o Comando Geral vê todas).
     */
    public function turmas(Request $request): Response
    {
        $consulta = Programa::query()->with(['turmas' => fn ($q) => $q->where('ativa', true)])->where('ativo', true)->orderBy('titulo');
        // Instrutor: só os programas dele. Comando Geral (cursos.gerenciar): todos.
        if (! $request->user()->can('cursos.gerenciar')) {
            $consulta->where('instrutor_id', $request->user()->id);
        }

        return Inertia::render('aulas-regulares/turmas', [
            'programas' => $consulta->get()->map(fn (Programa $p) => [
                'id' => $p->id,
                'titulo' => $p->titulo,
                'turmas' => $p->turmas->map(fn (ProgramaTurma $t) => ['id' => $t->id, 'nome' => $t->nome, 'horario' => $t->horarioPorExtenso()])->values()->all(),
            ])->values()->all(),
        ]);
    }

    /**
     * A chamada de uma turma numa data (padrão: hoje), com quem já foi marcado.
     */
    public function mostrar(Request $request, ProgramaTurma $turma): Response
    {
        Gate::authorize('programa.instrutor', $turma->programa);
        $data = $this->data((string) $request->query('data', ''));
        $aula = ProgramaAula::where('turma_id', $turma->id)->whereDate('data', $data)->first();
        $marcados = $aula ? $aula->presencas()->pluck('presente', 'assinatura_id')->all() : [];

        return Inertia::render('aulas-regulares/chamada', [
            'turma' => ['id' => $turma->id, 'nome' => $turma->nome, 'horario' => $turma->horarioPorExtenso(), 'programa' => $turma->programa->titulo],
            'data' => $data,
            'feita' => $aula?->chamada_feita_em !== null,
            // O que foi ministrado nesta aula (vazio = ainda não registrado).
            'conteudo' => $aula->conteudo ?? '',
            // A lista: as assinaturas da turma liberadas (em dia ou na carência); só o nome.
            'alunos' => $this->liberadas($turma)->map(fn (Assinatura $a) => [
                'assinatura_id' => $a->id,
                'nome' => $a->usuario->name,
                'acesso' => $a->acesso()['rotulo'],
                'presente' => (bool) ($marcados[$a->id] ?? false),
            ])->values()->all(),
        ]);
    }

    /**
     * Grava a chamada (quem estava) da turma na data.
     */
    public function salvar(Request $request, ProgramaTurma $turma): RedirectResponse
    {
        Gate::authorize('programa.instrutor', $turma->programa);
        $dados = $request->validate([
            'data' => ['required', 'date', 'before_or_equal:today'],
            'presentes' => ['array'],
            'presentes.*' => ['integer'],
            // Obrigatório (18/10/2026): o que foi ministrado vai para o dossiê de cada aluno.
            'conteudo' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'conteudo.required' => 'Escreva o que foi ministrado nesta aula (vai para o dossiê dos alunos).',
            'conteudo.min' => 'Descreva o que foi ministrado com pelo menos 3 letras.',
        ]);
        $presentes = array_map('intval', $dados['presentes'] ?? []);
        // Só as assinaturas liberadas desta turma entram (um número de fora é ignorado).
        $liberadas = $this->liberadas($turma)->pluck('id')->all();

        DB::transaction(function () use ($turma, $dados, $presentes, $liberadas): void {
            $aula = ProgramaAula::where('turma_id', $turma->id)->whereDate('data', $dados['data'])->first() ?? new ProgramaAula;
            $aula->forceFill(['turma_id' => $turma->id, 'data' => $dados['data'], 'conteudo' => trim($dados['conteudo']), 'chamada_feita_em' => now()])->save();

            foreach ($liberadas as $assinaturaId) {
                $presenca = ProgramaPresenca::where('aula_id', $aula->id)->where('assinatura_id', $assinaturaId)->first() ?? new ProgramaPresenca;
                $presenca->forceFill(['aula_id' => $aula->id, 'assinatura_id' => $assinaturaId, 'presente' => in_array($assinaturaId, $presentes, true)])->save();
            }
        });
        Auditoria::registrar('CHAMADA_REGULAR', ['turma_id' => $turma->id, 'data' => $dados['data'], 'presentes' => count(array_intersect($presentes, $liberadas))]);

        return back()->with('sucesso', 'Chamada registrada.');
    }

    /**
     * As assinaturas da turma que podem ir à aula (em dia ou na carência).
     *
     * @return Collection<int, Assinatura>
     */
    private function liberadas(ProgramaTurma $turma)
    {
        return Assinatura::query()->with('usuario:id,name')
            ->where('programa_id', $turma->programa_id)->where('turma_id', $turma->id)->where('situacao', 'ativa')
            ->get()->filter(fn (Assinatura $a) => $a->liberada())->sortBy(fn (Assinatura $a) => $a->usuario->name)->values();
    }

    /**
     * A data pedida, se válida e não futura; senão, hoje. Ex.: '2026-10-21'.
     */
    private function data(string $pedida): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $pedida) === 1 && $pedida <= today()->toDateString() ? $pedida : today()->toDateString();
    }
}
