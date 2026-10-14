<?php

/*
 * DIVISÃO DO DINHEIRO DE CADA CURSO (decisão do Gleyson, 14/10/2026): em
 * cada curso, o IBTO escolhe quanto fica com ele; o resto vai para o
 * instrutor. Padrão: 20% IBTO / 80% instrutor. A Formação de instrutor
 * fica com 100% (o curso é do próprio IBTO). Substitui a regra antiga de
 * 50/50 escrita na issue #45.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a coluna "percentual_ibto". Os cursos que já existem ficam com 20.
     * (O número 20 está escrito aqui de propósito: uma migration é uma "foto"
     * do dia e não pode mudar se a constante do código mudar um dia.)
     */
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            // De 0 a 100 (conferido no CursoRequest). Ex.: 20 = 20% do IBTO e 80% do instrutor.
            $table->unsignedTinyInteger('percentual_ibto')->default(20)->after('valor_centavos');
        });
    }

    /**
     * Desfaz: tira a coluna.
     */
    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('percentual_ibto');
        });
    }
};
