<?php

/*
 * CONFERÊNCIA DOS PRÉ-REQUISITOS DE UM CURSO (#88).
 *
 * Três tipos (o curso pode ter nenhum, um ou vários; todos precisam ser cumpridos):
 * - CURSO ANTERIOR: ter concluído pelo menos UM dos cursos da lista (certificado válido);
 * - NÍVEL: o nível do filiado igual ou acima do mínimo (na ordem das graduações);
 * - TEMPO DE FILIADO: meses desde a data de filiação ≥ o mínimo.
 *
 * Usada pela página do curso (mostra ✅/❌ em cada item) e pela matrícula (o
 * servidor recusa quem não cumpre). Como a ficha de inscrição de uma academia:
 * a secretária confere cada exigência antes de aceitar.
 */

namespace App\Support;

use App\Models\Curso;
use App\Models\CursoCertificado;
use App\Models\Nivel;
use App\Models\User;
use Illuminate\Support\Carbon;

class Requisitos
{
    /**
     * Confere os requisitos do curso para uma pessoa.
     *
     * @param  Curso  $curso  O curso. Ex.: "Combate com Faca", que exige "Combate Corpo a Corpo".
     * @param  User|null  $usuario  Quem quer se inscrever (null = visitante: lista sem marcação).
     * @return list<array{tipo: string, texto: string, cumprido: bool|null}>
     *                                                                       Um item por requisito. cumprido: true/false; null para visitante.
     *                                                                       Ex.: [['tipo' => 'curso', 'texto' => 'Ter concluído: Combate Corpo a Corpo', 'cumprido' => false]]
     */
    public static function avaliar(Curso $curso, ?User $usuario): array
    {
        $itens = [];
        // O registro de filiado da pessoa (nível e data de filiação moram nele).
        $filiado = $usuario?->filiado;

        // 1) Curso anterior: basta um da lista concluído, com certificado válido.
        $anteriores = $curso->requisitosCursos()->get(['cursos.id', 'cursos.titulo']);
        if ($anteriores->isNotEmpty()) {
            $concluiu = $usuario === null ? null : CursoCertificado::query()
                ->whereNull('cancelado_em')
                ->whereHas('matricula', fn ($matricula) => $matricula
                    ->where('user_id', $usuario->id)
                    ->whereIn('curso_id', $anteriores->pluck('id')))
                ->exists();

            $itens[] = [
                'tipo' => 'curso',
                // Um curso: "Ter concluído: X". Vários: "Ter concluído um destes: X ou Y".
                'texto' => ($anteriores->count() === 1 ? 'Ter concluído: ' : 'Ter concluído um destes: ')
                    .$anteriores->pluck('titulo')->implode(' ou '),
                'cumprido' => $concluiu,
            ];
        }

        // 2) Nível mínimo: compara as posições na hierarquia (Praticante I … Instrutor Expert V).
        if ($curso->nivel_minimo_id !== null) {
            $posicoes = Nivel::posicoes();
            $exigida = $posicoes[$curso->nivel_minimo_id] ?? PHP_INT_MAX;
            // Sem nível (ou não filiado): posição 0, não cumpre nenhum mínimo.
            $daPessoa = $filiado?->nivel_id !== null ? ($posicoes[$filiado->nivel_id] ?? 0) : 0;

            $itens[] = [
                'tipo' => 'nivel',
                'texto' => 'Nível mínimo: '.($curso->nivelMinimo?->nome() ?? 'definido pelo IBTO'),
                'cumprido' => $usuario === null ? null : $daPessoa >= $exigida,
            ];
        }

        // 3) Tempo de filiado: meses inteiros desde a data de filiação.
        if ($curso->meses_filiacao_minimo !== null && $curso->meses_filiacao_minimo > 0) {
            $desde = $filiado?->data_filiacao;
            $meses = $desde !== null ? (int) Carbon::parse($desde)->diffInMonths(today()) : null;

            $itens[] = [
                'tipo' => 'filiacao',
                'texto' => 'Tempo mínimo de filiado: '.self::meses($curso->meses_filiacao_minimo)
                    // Filiado sem a data cadastrada: o aviso diz o porquê de não cumprir.
                    .($usuario !== null && $filiado !== null && $desde === null ? ' (sua data de filiação não está cadastrada; fale com o admin da sua equipe)' : ''),
                'cumprido' => $usuario === null ? null : ($meses !== null && $meses >= $curso->meses_filiacao_minimo),
            ];
        }

        return $itens;
    }

    /**
     * Todos os requisitos cumpridos? (Curso sem requisito: sim.)
     *
     * @param  Curso  $curso  O curso.
     * @param  User  $usuario  Quem quer se inscrever.
     */
    public static function cumpre(Curso $curso, User $usuario): bool
    {
        foreach (self::avaliar($curso, $usuario) as $item) {
            // Um item não cumprido basta para barrar.
            if ($item['cumprido'] !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * Os textos dos requisitos que faltam (para a mensagem de recusa).
     *
     * @return list<string> Ex.: ['Ter concluído: Combate Corpo a Corpo']
     */
    public static function pendentes(Curso $curso, User $usuario): array
    {
        return array_values(array_map(
            fn (array $item) => $item['texto'],
            array_filter(self::avaliar($curso, $usuario), fn (array $item) => $item['cumprido'] !== true),
        ));
    }

    /**
     * "1 mês", "12 meses".
     *
     * @param  int  $meses  Ex.: 12.
     */
    private static function meses(int $meses): string
    {
        return $meses === 1 ? '1 mês' : "{$meses} meses";
    }
}
