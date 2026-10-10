<?php

/*
 * PARCELAS no financeiro (#59, pedido do Gleyson em 09/10/2026): um título
 * lançado "em 3x" vira 3 lançamentos (1/3, 2/3, 3/3), um por mês, cada um com
 * a sua baixa. Estas colunas ligam as parcelas entre si.
 * Compatível com o MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as três colunas das parcelas (vazias para quem é "à vista").
     */
    public function up(): void
    {
        Schema::table('fin_lancamentos', function (Blueprint $table) {
            // Qual parcela é esta. Ex.: 2 (de "2/3").
            $table->unsignedTinyInteger('parcela_numero')->nullable()->after('vencimento');
            // Quantas parcelas o título tem. Ex.: 3 (de "2/3").
            $table->unsignedTinyInteger('parcela_total')->nullable()->after('parcela_numero');
            // Código igual em todas as parcelas do mesmo título (um UUID: texto
            // aleatório de 36 letras que não se repete). Index: busca rápida do grupo.
            $table->string('grupo_parcelas', 36)->nullable()->after('parcela_total')->index();
        });
    }

    /**
     * Desfaz: apaga as três colunas.
     */
    public function down(): void
    {
        Schema::table('fin_lancamentos', function (Blueprint $table) {
            $table->dropIndex(['grupo_parcelas']);
            $table->dropColumn(['parcela_numero', 'parcela_total', 'grupo_parcelas']);
        });
    }
};
