<?php

/*
 * CÓDIGO ÚNICO DE PAGAMENTO (pedido do Gleyson em 17/10/2026): cada cobrança
 * (inscrição em curso, pedido de fotos e mensalidade de aula regular) ganha um
 * código sorteado e que NUNCA se repete (ULID: 26 letras e números). Ele forma o
 * endereço da página de pagamento /pagar/<código>, que pode ir por WhatsApp ou
 * e-mail. Sorteado (e não o número da cobrança) para ninguém chegar ao pagamento
 * de outra pessoa trocando um número no endereço. As cobranças que já existem
 * recebem o código aqui. Roda no MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** As tabelas das cobranças. */
    private const TABELAS = ['curso_matriculas', 'pedidos_fotos', 'assinatura_pagamentos'];

    /**
     * Cria a coluna nas três tabelas e sorteia o código das cobranças que já existem.
     */
    public function up(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->char('codigo_pagamento', 26)->nullable()->unique();
            });
            foreach (DB::table($tabela)->whereNull('codigo_pagamento')->pluck('id') as $id) {
                DB::table($tabela)->where('id', $id)->update(['codigo_pagamento' => strtolower((string) Str::ulid())]);
            }
        }
    }

    /**
     * Desfaz: tira a coluna das três tabelas.
     */
    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropUnique(['codigo_pagamento']);
                $table->dropColumn('codigo_pagamento');
            });
        }
    }
};
