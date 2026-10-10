<?php

/*
 * PAGAMENTO PARCIAL no financeiro (#59, opção B, pedido do Gleyson em
 * 10/10/2026): quando entra só parte do valor, o lançamento fica com o valor
 * pago e nasce outro, pendente, com o SALDO que falta. Esta coluna liga o
 * saldo ao lançamento de onde ele veio. Compatível com o MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a coluna (vazia em quem não é saldo de outro lançamento).
     */
    public function up(): void
    {
        Schema::table('fin_lancamentos', function (Blueprint $table) {
            // De qual lançamento este é o saldo. unique: cada baixa parcial gera
            // UM saldo só. restrictOnDelete: o original não pode sumir.
            $table->foreignId('saldo_de_id')->nullable()->unique()->after('estorno_de_id')
                ->constrained('fin_lancamentos')->restrictOnDelete();
        });
    }

    /**
     * Desfaz: apaga a ligação e a coluna.
     */
    public function down(): void
    {
        Schema::table('fin_lancamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('saldo_de_id');
        });
    }
};
