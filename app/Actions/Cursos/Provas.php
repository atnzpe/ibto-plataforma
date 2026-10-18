<?php

/*
 * REGRAS DAS PROVAS (#43): montar a prova (instrutor), corrigir as respostas
 * (servidor) e dizer a situação do aluno em cada prova.
 *
 * - O GABARITO nunca sai daqui para a tela do aluno: a correção é feita no
 *   servidor, comparando as respostas com a alternativa marcada como certa.
 * - Nota = acertos ÷ perguntas × 100 (arredondada). Aprovado = nota ≥ nota mínima.
 * - Tentativas contadas por matrícula; acabou, não responde mais.
 * - Toda prova montada ou respondida vai para a auditoria (só números).
 */

namespace App\Actions\Cursos;

use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\CursoProva;
use App\Models\CursoProvaQuestao;
use App\Models\CursoProvaTentativa;
use Illuminate\Support\Facades\DB;

class Provas
{
    /**
     * Cria ou atualiza uma prova com as perguntas e alternativas.
     * As perguntas são regravadas inteiras a cada salvamento (as tentativas
     * antigas guardam a própria nota e não mudam).
     *
     * @param  Curso  $curso  O curso da prova.
     * @param  array{titulo: string, instrucoes?: string|null, nota_minima: int, tentativas_max: int, ativa: bool, questoes: list<array{enunciado: string, alternativas: list<array{texto: string, correta: bool}>}>}  $dados
     *                                                                                                                                                                                                                         Ex.: ['titulo' => 'Prova final', 'nota_minima' => 70, 'tentativas_max' => 3, 'ativa' => true, 'questoes' => [['enunciado' => '...', 'alternativas' => [['texto' => 'A', 'correta' => true], ['texto' => 'B', 'correta' => false]]]]]
     * @param  CursoProva|null  $prova  A prova a atualizar; null = criar uma nova.
     * @return CursoProva A prova gravada.
     *
     * @throws RegraCurso Pergunta sem exatamente UMA alternativa certa.
     */
    public function salvar(Curso $curso, array $dados, ?CursoProva $prova = null): CursoProva
    {
        // Cada pergunta precisa de pelo menos 2 alternativas e de exatamente UMA certa.
        foreach ($dados['questoes'] as $numero => $questao) {
            $certas = count(array_filter($questao['alternativas'], fn (array $alternativa) => $alternativa['correta']));
            if (count($questao['alternativas']) < 2 || $certas !== 1) {
                throw new RegraCurso('PROVA_GABARITO_INVALIDO', 'A pergunta '.($numero + 1).' precisa de pelo menos 2 alternativas e de exatamente uma marcada como certa.');
            }
        }

        $prova = DB::transaction(function () use ($curso, $dados, $prova): CursoProva {
            $prova ??= new CursoProva;
            $prova->fill([
                'titulo' => trim($dados['titulo']),
                'instrucoes' => isset($dados['instrucoes']) ? trim((string) $dados['instrucoes']) : null,
                'nota_minima' => $dados['nota_minima'],
                'tentativas_max' => $dados['tentativas_max'],
                'ativa' => $dados['ativa'],
            ]);
            $prova->curso_id = $curso->id;
            $prova->save();

            // Regrava as perguntas (as alternativas saem junto, pela ligação em cascata).
            $prova->questoes()->delete();
            foreach ($dados['questoes'] as $posicao => $questao) {
                $nova = new CursoProvaQuestao(['enunciado' => trim($questao['enunciado']), 'ordem' => $posicao + 1]);
                $nova->prova_id = $prova->id;
                $nova->save();
                foreach ($questao['alternativas'] as $ordem => $alternativa) {
                    $nova->alternativas()->create(['texto' => trim($alternativa['texto']), 'correta' => $alternativa['correta'], 'ordem' => $ordem + 1]);
                }
            }

            return $prova;
        });

        Auditoria::registrar('PROVA_SALVA', ['prova_id' => $prova->id, 'curso_id' => $curso->id, 'questoes' => count($dados['questoes'])]);

        return $prova;
    }

    /**
     * Corrige as respostas do aluno e grava a tentativa.
     *
     * @param  CursoProva  $prova  A prova respondida.
     * @param  CursoMatricula  $matricula  A matrícula de quem respondeu.
     * @param  array<int|string, int|string>  $respostas  {número da pergunta: número da alternativa}. Ex.: [12 => 40, 13 => 44]
     * @return CursoProvaTentativa A tentativa, com a nota e se aprovou.
     *
     * @throws RegraCurso Matrícula não confirmada, prova desativada, sem tentativas ou pergunta sem resposta.
     */
    public function responder(CursoProva $prova, CursoMatricula $matricula, array $respostas): CursoProvaTentativa
    {
        // Só matrícula CONFIRMADA deste curso responde.
        if ($matricula->situacao !== SituacaoMatricula::Confirmada || $matricula->curso_id !== $prova->curso_id) {
            throw new RegraCurso('PROVA_SEM_MATRICULA', 'Só quem tem a matrícula confirmada no curso faz a prova.');
        }
        // Prova desativada não recebe respostas.
        if (! $prova->ativa) {
            throw new RegraCurso('PROVA_DESATIVADA', 'Esta prova não está disponível.');
        }

        return DB::transaction(function () use ($prova, $matricula, $respostas): CursoProvaTentativa {
            // Tranca a matrícula: dois envios ao mesmo tempo não passam do limite de tentativas.
            CursoMatricula::query()->lockForUpdate()->findOrFail($matricula->id);
            $usadas = CursoProvaTentativa::where('prova_id', $prova->id)->where('matricula_id', $matricula->id)->count();
            if ($usadas >= $prova->tentativas_max) {
                throw new RegraCurso('PROVA_SEM_TENTATIVAS', "Você já usou as {$prova->tentativas_max} tentativas desta prova.");
            }

            $questoes = $prova->questoes()->with('alternativas')->get();
            // Prova sem perguntas não pode ser respondida.
            if ($questoes->isEmpty()) {
                throw new RegraCurso('PROVA_SEM_QUESTOES', 'Esta prova ainda não tem perguntas.');
            }

            $acertos = 0;
            $escolhidas = [];
            foreach ($questoes as $questao) {
                $escolhida = (int) ($respostas[$questao->id] ?? 0);
                // Toda pergunta precisa de resposta, e a resposta precisa ser uma alternativa DELA.
                if (! $questao->alternativas->contains('id', $escolhida)) {
                    throw new RegraCurso('PROVA_RESPOSTA_FALTANDO', "Responda a pergunta {$questao->ordem} antes de enviar.");
                }
                $escolhidas[(string) $questao->id] = $escolhida;
                // Acertou: a escolhida é a marcada como certa (o gabarito, só aqui no servidor).
                if ($questao->alternativas->firstWhere('id', $escolhida)?->correta) {
                    $acertos++;
                }
            }

            // Nota de 0 a 100, arredondada. Ex.: 7 de 10 → 70.
            $nota = (int) round($acertos * 100 / $questoes->count());
            $tentativa = new CursoProvaTentativa;
            $tentativa->forceFill([
                'prova_id' => $prova->id,
                'matricula_id' => $matricula->id,
                'nota' => $nota,
                'aprovada' => $nota >= $prova->nota_minima,
                'respostas' => $escolhidas,
            ])->save();

            Auditoria::registrar('PROVA_RESPONDIDA', ['prova_id' => $prova->id, 'matricula_id' => $matricula->id, 'nota' => $nota, 'aprovada' => $tentativa->aprovada]);

            return $tentativa;
        });
    }

    /**
     * A situação de uma matrícula numa prova: melhor nota, se aprovou e as tentativas.
     *
     * @return array{melhor_nota: int|null, aprovada: bool, usadas: int, restantes: int}
     *                                                                                   Ex.: ['melhor_nota' => 80, 'aprovada' => true, 'usadas' => 2, 'restantes' => 1]
     */
    public function situacao(CursoProva $prova, CursoMatricula $matricula): array
    {
        $tentativas = CursoProvaTentativa::where('prova_id', $prova->id)->where('matricula_id', $matricula->id)->get(['nota', 'aprovada']);

        return [
            // null = ainda não fez a prova.
            'melhor_nota' => $tentativas->isEmpty() ? null : (int) $tentativas->max('nota'),
            'aprovada' => $tentativas->contains('aprovada', true),
            'usadas' => $tentativas->count(),
            'restantes' => max(0, $prova->tentativas_max - $tentativas->count()),
        ];
    }

    /**
     * As provas ATIVAS do curso em que a matrícula ainda não foi aprovada (para o certificado).
     *
     * @return list<string> Os títulos. Ex.: ['Prova final']
     */
    public function pendentes(CursoMatricula $matricula): array
    {
        return array_values(CursoProva::query()
            ->where('curso_id', $matricula->curso_id)
            ->where('ativa', true)
            ->whereDoesntHave('tentativas', fn ($tentativa) => $tentativa->where('matricula_id', $matricula->id)->where('aprovada', true))
            ->orderBy('id')
            ->pluck('titulo')
            ->all());
    }
}
