<?php

/*
 * PRÉ-REQUISITOS DOS CURSOS (#88): como nas graduações, ninguém pula etapa.
 *
 * - cursos.nivel_minimo_id: o nível mínimo para se inscrever (ex.: a partir de
 *   "Instrutor I"). Vazio = qualquer nível (ou sem nível).
 * - cursos.meses_filiacao_minimo: tempo mínimo de filiação, em meses (ex.: 12).
 *   Vazio = sem exigência.
 * - curso_requisitos: os cursos ANTERIORES; basta ter concluído UM deles (com
 *   certificado válido). Ex.: Combate com Faca exige a turma de março OU a de
 *   abril do Combate Corpo a Corpo.
 * - filiados.data_filiacao: a data real de filiação ao IBTO (o tempo de filiado
 *   conta a partir dela; o cadastro na plataforma não serve, porque a planilha
 *   importou todos no mesmo dia).
 * - curso_matriculas.excecao_motivo: quando o IBTO matricula alguém que NÃO
 *   cumpre os requisitos, o motivo fica aqui (e a exceção vai para a auditoria).
 * Roda no MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as colunas e a tabela dos cursos anteriores. Cursos que já existem ficam sem requisito.
     */
    public function up(): void
    {
        // Nível mínimo e tempo mínimo de filiação no próprio curso.
        Schema::table('cursos', function (Blueprint $table) {
            $table->foreignId('nivel_minimo_id')->nullable()->constrained('niveis')->nullOnDelete();
            $table->unsignedSmallInteger('meses_filiacao_minimo')->nullable();
        });

        // Os cursos anteriores (um curso pode exigir vários; basta um concluído).
        Schema::create('curso_requisitos', function (Blueprint $table) {
            $table->id();
            // O curso que tem o requisito.
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            // O curso que precisa ter sido concluído antes.
            $table->foreignId('requisito_curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->timestamps();
            // O mesmo requisito não se repete no mesmo curso.
            $table->unique(['curso_id', 'requisito_curso_id']);
        });

        // A data real de filiação ao IBTO.
        Schema::table('filiados', function (Blueprint $table) {
            $table->date('data_filiacao')->nullable();
        });

        // O motivo da exceção, quando o IBTO matricula quem não cumpre os requisitos.
        Schema::table('curso_matriculas', function (Blueprint $table) {
            $table->string('excecao_motivo', 500)->nullable();
        });
    }

    /**
     * Desfaz: tira a tabela e as colunas.
     */
    public function down(): void
    {
        Schema::table('curso_matriculas', function (Blueprint $table) {
            $table->dropColumn('excecao_motivo');
        });
        Schema::table('filiados', function (Blueprint $table) {
            $table->dropColumn('data_filiacao');
        });
        Schema::dropIfExists('curso_requisitos');
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nivel_minimo_id');
            $table->dropColumn('meses_filiacao_minimo');
        });
    }
};
