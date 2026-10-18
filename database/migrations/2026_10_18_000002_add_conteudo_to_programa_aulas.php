<?php

/*
 * DIÁRIO DAS AULAS REGULARES (18/10/2026, pedido do Gleyson): ao registrar a
 * aula (chamada), o instrutor escreve o que foi ministrado. Isso alimenta o
 * dossiê do aluno com a lista cronológica do que ele viu, como já acontece
 * nos encontros dos cursos (curso_aulas.registro_encontro).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acrescenta "conteudo" (texto livre, até 2000 caracteres na validação).
     */
    public function up(): void
    {
        Schema::table('programa_aulas', function (Blueprint $table) {
            // O que foi ministrado. Ex.: "Defesa contra estrangulamento frontal e quedas."
            $table->text('conteudo')->nullable()->after('data');
        });
    }

    /**
     * Desfaz: tira a coluna.
     */
    public function down(): void
    {
        Schema::table('programa_aulas', function (Blueprint $table) {
            $table->dropColumn('conteudo');
        });
    }
};
