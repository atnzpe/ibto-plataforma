<?php

/*
 * CURSO FECHADO (decisão do Gleyson, 14/10/2026): "a Formação de instrutor
 * NÃO deve constar no site". O curso existe na plataforma, mas:
 * - não aparece no catálogo, nas trilhas nem na landpage;
 * - ninguém se inscreve sozinho: quem entra é matriculado pelo IBTO;
 * - a página dele só abre para quem está matriculado ou cuida do curso.
 * Só o IBTO (quem gerencia ou chancela cursos) liga ou desliga.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a coluna "fechado". Os cursos que já existem ficam ABERTOS (false),
     * do jeito que estão hoje.
     */
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            // true = fora do site; false = aparece normalmente (padrão).
            $table->boolean('fechado')->default(false)->after('acesso');
        });
    }

    /**
     * Desfaz: tira a coluna (todos os cursos voltam a aparecer).
     */
    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('fechado');
        });
    }
};
