<?php

/*
 * VITRINE dos cursos e ENCONTROS (#38, bloco C4), a partir dos cartazes de
 * referência do IBTO (10/10/2026):
 * - curso: nome e especialidade do instrutor (nem todo instrutor tem conta;
 *   ex.: "CAETANO (especialista em tiro)") e a CHAMADA, a frase de efeito
 *   (ex.: "Mais que treinamento, preparação para a vida real");
 * - aula/encontro: dia, horário e quem aplica — para o CRONOGRAMA clicável
 *   dos cursos presenciais (conversa de 23/09/2026: janela com detalhes e
 *   lembrete na agenda).
 * Compatível com o MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as colunas novas.
     */
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            // Nome do instrutor como aparece no cartaz (vale mais que o da conta, se preenchido).
            $table->string('instrutor_nome', 100)->nullable()->after('instrutor_id');
            // Ex.: 'Especialista em tiro'.
            $table->string('instrutor_especialidade', 100)->nullable()->after('instrutor_nome');
            // A frase de efeito do cartaz. Ex.: 'Torne-se preparado. Sobreviva.'
            $table->string('chamada', 200)->nullable()->after('titulo');
        });

        Schema::table('curso_aulas', function (Blueprint $table) {
            // Encontro presencial: dia e horário (vazios na aula online, "no seu ritmo").
            $table->date('data')->nullable()->after('duracao_minutos');
            $table->time('hora_inicio')->nullable()->after('data');
            $table->time('hora_fim')->nullable()->after('hora_inicio');
            // Quem aplica o encontro. Ex.: 'Instrutor Trinca'.
            $table->string('quem_aplica', 100)->nullable()->after('hora_fim');
            // Busca rápida dos encontros por dia (cronograma).
            $table->index('data');
        });
    }

    /**
     * Desfaz: apaga as colunas.
     */
    public function down(): void
    {
        Schema::table('curso_aulas', function (Blueprint $table) {
            $table->dropIndex(['data']);
            $table->dropColumn(['data', 'hora_inicio', 'hora_fim', 'quem_aplica']);
        });
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn(['instrutor_nome', 'instrutor_especialidade', 'chamada']);
        });
    }
};
