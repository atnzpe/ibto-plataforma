<?php

/*
 * SALA DE AULA (#39 C4c + progresso das aulas online, 14/10/2026): as regras
 * que a sala de aula e "Meus cursos" usam, num lugar só.
 *
 * - sequencia(): as aulas que a pessoa pode ver, na ordem (módulo por módulo).
 *   Regra do nível (do Dojo Manager): módulo com "nível mínimo" só abre para o
 *   filiado daquele nível ou de um abaixo (CursoModulo::liberadoPara). A
 *   equipe do curso vê tudo, para conferir.
 * - progresso(): "3 de 10 aulas (30%)" e a próxima aula não assistida
 *   (o botão "Continuar"). Conta só as aulas que a pessoa PODE ver: aula
 *   desativada ou de módulo bloqueado não entra na conta.
 * - marcar(): marca ou desmarca a aula como assistida.
 */

namespace App\Services;

use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoAulaAssistida;
use App\Models\CursoModulo;
use App\Models\Nivel;
use App\Models\User;
use Illuminate\Support\Collection;

class SalaDeAula
{
    /**
     * Todas as aulas que a pessoa pode ver, na ordem: módulo por módulo (pela
     * ordem do módulo), e dentro dele pela ordem da aula. Ficam de fora as
     * aulas e os módulos desativados e, para quem não é da equipe do curso,
     * os módulos acima do nível da pessoa.
     *
     * @param  Curso  $curso  O curso. Ex.: "Combate Corpo a Corpo".
     * @param  User  $usuario  Quem está assistindo.
     * @return Collection<int, CursoAula> As aulas em sequência (0, 1, 2...).
     */
    public function sequencia(Curso $curso, User $usuario): Collection
    {
        // Equipe do curso (Comando Geral, quem chancela ou quem propôs): vê tudo.
        $daEquipe = $usuario->temPermissao('cursos.gerenciar')
            || $usuario->temPermissao('cursos.chancelar')
            || $curso->responsavel_id === $usuario->id;

        // Nível do filiado (null = sem nível) e a hierarquia dos níveis.
        $nivelDoAluno = $usuario->filiado?->nivel_id;
        $posicoes = $daEquipe ? [] : Nivel::posicoes();

        return $curso->modulos()
            ->where('ativo', true)
            ->with(['aulas' => fn ($aulas) => $aulas->where('ativa', true)])
            ->get()
            // Módulo bloqueado pelo nível: sai inteiro (a equipe não passa por este filtro).
            ->filter(fn (CursoModulo $modulo) => $daEquipe || $modulo->liberadoPara($nivelDoAluno, $posicoes))
            // Junta as aulas de todos os módulos numa lista só, já na ordem.
            ->flatMap(fn (CursoModulo $modulo) => $modulo->aulas->each(fn (CursoAula $aula) => $aula->setRelation('modulo', $modulo)))
            ->values();
    }

    /**
     * Os ids das aulas que a pessoa marcou como assistidas, entre as da lista.
     *
     * @param  Collection<int, CursoAula>  $aulas  As aulas da sequência.
     * @param  User  $usuario  Quem está assistindo.
     * @return list<int> Ex.: [31, 32, 35]
     */
    public function assistidas(Collection $aulas, User $usuario): array
    {
        // array_values: devolve uma lista numerada 0, 1, 2... (sem "buracos" nas posições).
        return array_values(CursoAulaAssistida::query()
            ->where('user_id', $usuario->id)
            // pluck('id'): os ids das aulas da lista. Ex.: [31, 32, 33].
            ->whereIn('aula_id', $aulas->pluck('id'))
            ->pluck('aula_id')
            // O banco pode entregar o número como texto: vira número inteiro.
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    /**
     * O progresso da pessoa no curso.
     *
     * @param  Curso  $curso  O curso.
     * @param  User  $usuario  Quem está assistindo.
     * @return array{assistidas: int, total: int, percentual: int, continuar_id: int|null}
     *                                                                                     Ex.: ['assistidas' => 3, 'total' => 10, 'percentual' => 30, 'continuar_id' => 34]
     */
    public function progresso(Curso $curso, User $usuario): array
    {
        $aulas = $this->sequencia($curso, $usuario);
        $assistidas = $this->assistidas($aulas, $usuario);
        $total = $aulas->count();

        // "Continuar": a primeira aula da sequência que ainda não foi assistida.
        // Todas assistidas: volta para a primeira (para rever). Sem aulas: null.
        $continuar = $aulas->first(fn (CursoAula $aula) => ! in_array($aula->id, $assistidas, true)) ?? $aulas->first();

        return [
            'assistidas' => count($assistidas),
            'total' => $total,
            // Sem aulas, 0% (e não divide por zero). intdiv = sem casas decimais (ex.: 33%).
            'percentual' => $total > 0 ? intdiv(count($assistidas) * 100, $total) : 0,
            'continuar_id' => $continuar?->id,
        ];
    }

    /**
     * Marca (ou desmarca) a aula como assistida pela pessoa.
     *
     * @param  CursoAula  $aula  A aula. Ex.: aula 31.
     * @param  User  $usuario  Quem assistiu.
     * @param  bool  $assistida  true = marcar; false = desmarcar.
     */
    public function marcar(CursoAula $aula, User $usuario, bool $assistida): void
    {
        // Desmarcar: apaga a linha (se não existir, não faz nada).
        if (! $assistida) {
            CursoAulaAssistida::query()->where('user_id', $usuario->id)->where('aula_id', $aula->id)->delete();

            return;
        }

        // Já marcada? Não faz nada (marcar duas vezes não duplica e não troca a
        // data da primeira vez).
        if (CursoAulaAssistida::query()->where('user_id', $usuario->id)->where('aula_id', $aula->id)->exists()) {
            return;
        }

        // Ainda não marcada: cria a linha (forceFill, porque o modelo não aceita
        // preenchimento direto de formulário).
        (new CursoAulaAssistida)->forceFill([
            'user_id' => $usuario->id,
            'aula_id' => $aula->id,
            'assistida_em' => now(),
        ])->save();
    }
}
