<?php

/*
 * VÍNCULOS DO LIVRO-CAIXA (18/10/2026, pedido do Gleyson: "quanto preciso pagar
 * a cada fotógrafo, instrutor... filtro por pessoa, equipe, modalidade").
 *
 * Cada lançamento passa a guardar A QUEM se refere, pelo NÚMERO (sem nome na
 * descrição, LGPD):
 * - pessoa_id: na RECEITA, quem pagou (o aluno); na DESPESA de repasse, quem
 *   recebe (o instrutor ou o fotógrafo);
 * - curso_id e programa_id: o curso (inscrição, repasse, fotos) ou o programa
 *   de aulas regulares (mensalidade e repasse).
 *
 * Os lançamentos que já existem são preenchidos a partir da "origem" (matrícula,
 * mensalidade, pedido de fotos, anuidade). O preenchimento vai DIRETO na tabela
 * (sem passar pelo modelo) porque lançamento pago não aceita alteração pelo
 * modelo; aqui só entram os vínculos novos, sem mexer em valor nem situação.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as colunas e preenche os lançamentos antigos.
     */
    public function up(): void
    {
        Schema::table('fin_lancamentos', function (Blueprint $table) {
            $table->foreignId('pessoa_id')->nullable()->after('equipe_id')->constrained('users')->nullOnDelete();
            $table->foreignId('curso_id')->nullable()->after('pessoa_id')->constrained('cursos')->nullOnDelete();
            $table->foreignId('programa_id')->nullable()->after('curso_id')->constrained('programas')->nullOnDelete();
        });

        // Lê cada lançamento com origem e grava os vínculos dele (poucos registros: um por vez).
        foreach (DB::table('fin_lancamentos')->whereNotNull('origem')->orderBy('id')->get(['id', 'origem', 'origem_id']) as $lancamento) {
            $vinculos = $this->vinculos((string) $lancamento->origem, $lancamento->origem_id !== null ? (int) $lancamento->origem_id : null, (int) $lancamento->id);
            // Achou alguma ligação: grava (sem tocar nas outras colunas).
            if ($vinculos !== []) {
                DB::table('fin_lancamentos')->where('id', $lancamento->id)->update($vinculos);
            }
        }

        // Estornos e saldos herdam os vínculos do lançamento de origem.
        foreach (['estorno_de_id', 'saldo_de_id'] as $coluna) {
            foreach (DB::table('fin_lancamentos')->whereNotNull($coluna)->get(['id', $coluna]) as $filho) {
                $pai = DB::table('fin_lancamentos')->where('id', $filho->{$coluna})->first(['pessoa_id', 'curso_id', 'programa_id']);
                if ($pai !== null) {
                    DB::table('fin_lancamentos')->where('id', $filho->id)->update(['pessoa_id' => $pai->pessoa_id, 'curso_id' => $pai->curso_id, 'programa_id' => $pai->programa_id]);
                }
            }
        }
    }

    /**
     * Os vínculos de um lançamento pela origem. Ex.: 'matricula', 12 → aluno e curso da matrícula 12.
     *
     * @return array<string, int|null>
     */
    private function vinculos(string $origem, ?int $origemId, int $lancamentoId): array
    {
        switch ($origem) {
            case 'matricula':
            case 'repasse':
                $matricula = DB::table('curso_matriculas')->where('id', $origemId)->first(['user_id', 'curso_id']);
                if ($matricula === null) {
                    return [];
                }
                // Receita: quem pagou (aluno). Repasse: quem recebe (instrutor do curso, ou o responsável).
                $curso = DB::table('cursos')->where('id', $matricula->curso_id)->first(['instrutor_id', 'responsavel_id']);
                $pessoa = $origem === 'matricula' ? $matricula->user_id : ($curso->instrutor_id ?? $curso->responsavel_id ?? null);

                return ['pessoa_id' => $pessoa, 'curso_id' => $matricula->curso_id];

            case 'assinatura':
            case 'repasse_assinatura':
                $pagamento = DB::table('assinatura_pagamentos')->join('assinaturas', 'assinaturas.id', '=', 'assinatura_pagamentos.assinatura_id')
                    ->join('programas', 'programas.id', '=', 'assinaturas.programa_id')
                    ->where('assinatura_pagamentos.id', $origemId)
                    ->first(['assinaturas.user_id', 'assinaturas.programa_id', 'programas.instrutor_id']);
                if ($pagamento === null) {
                    return [];
                }

                return ['pessoa_id' => $origem === 'assinatura' ? $pagamento->user_id : $pagamento->instrutor_id, 'programa_id' => $pagamento->programa_id];

            case 'pedido_foto':
            case 'repasse_foto':
                $pedido = DB::table('pedidos_fotos')->join('foto_albuns_venda', 'foto_albuns_venda.id', '=', 'pedidos_fotos.album_id')
                    ->where('pedidos_fotos.id', $origemId)
                    ->first(['pedidos_fotos.user_id', 'foto_albuns_venda.curso_id', 'foto_albuns_venda.fotografo_id']);
                if ($pedido === null) {
                    return [];
                }

                return ['pessoa_id' => $origem === 'pedido_foto' ? $pedido->user_id : $pedido->fotografo_id, 'curso_id' => $pedido->curso_id];

            case 'anuidade':
                // A anuidade guarda o lançamento no próprio pagamento: quem pagou é o filiado.
                $filiado = DB::table('anuidade_pagamentos')->join('filiados', 'filiados.id', '=', 'anuidade_pagamentos.filiado_id')
                    ->where('anuidade_pagamentos.lancamento_id', $lancamentoId)
                    ->first(['filiados.user_id']);

                return $filiado !== null ? ['pessoa_id' => $filiado->user_id] : [];
        }

        // Origem sem pessoa (lançamento manual etc.): fica sem vínculo.
        return [];
    }

    /**
     * Desfaz: tira as três colunas.
     */
    public function down(): void
    {
        Schema::table('fin_lancamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('programa_id');
            $table->dropConstrainedForeignId('curso_id');
            $table->dropConstrainedForeignId('pessoa_id');
        });
    }
};
