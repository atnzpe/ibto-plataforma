<?php

/*
 * PROVAS DOS CURSOS (#43, 18/10/2026): múltipla escolha, nota mínima e número
 * de tentativas. O GABARITO (qual alternativa é a certa) fica só no banco: a
 * tela do aluno nunca recebe essa informação; quem corrige é o servidor.
 *
 * - curso_provas: a prova de um curso (pode haver mais de uma, ex.: uma por módulo);
 * - curso_prova_questoes: as perguntas, em ordem;
 * - curso_prova_alternativas: as opções de cada pergunta (uma certa);
 * - curso_prova_tentativas: cada vez que o aluno respondeu (nota, aprovado e as
 *   respostas escolhidas), para contar as tentativas e guardar o histórico.
 * Tipos compatíveis com o MySQL 5.7 (respostas em texto, não no tipo JSON).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as quatro tabelas.
     */
    public function up(): void
    {
        Schema::create('curso_provas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            // Ex.: "Prova final" ou "Prova do módulo 2".
            $table->string('titulo', 150);
            // Orientações para o aluno antes de começar (opcional).
            $table->text('instrucoes')->nullable();
            // Nota mínima para aprovar, de 0 a 100. Padrão: 70.
            $table->unsignedTinyInteger('nota_minima')->default(70);
            // Quantas vezes o aluno pode responder. Padrão: 3.
            $table->unsignedTinyInteger('tentativas_max')->default(3);
            // Desativada não aparece para o aluno e não conta para o certificado.
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });

        Schema::create('curso_prova_questoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prova_id')->constrained('curso_provas')->cascadeOnDelete();
            // A pergunta. Ex.: "Qual a distância segura de reação?"
            $table->text('enunciado');
            // Posição na prova (1, 2, 3...).
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->timestamps();
        });

        Schema::create('curso_prova_alternativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questao_id')->constrained('curso_prova_questoes')->cascadeOnDelete();
            // O texto da opção. Ex.: "Cerca de 7 metros."
            $table->string('texto', 500);
            // O GABARITO: true na alternativa certa (nunca vai para a tela do aluno).
            $table->boolean('correta')->default(false);
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->timestamps();
        });

        Schema::create('curso_prova_tentativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prova_id')->constrained('curso_provas')->cascadeOnDelete();
            $table->foreignId('matricula_id')->constrained('curso_matriculas')->cascadeOnDelete();
            // Nota de 0 a 100 (acertos ÷ perguntas).
            $table->unsignedTinyInteger('nota');
            $table->boolean('aprovada');
            // As respostas escolhidas: {"id da pergunta": id da alternativa}, em texto JSON.
            $table->text('respostas');
            $table->timestamps();
            $table->index(['prova_id', 'matricula_id']);
        });
    }

    /**
     * Desfaz: apaga as quatro tabelas (na ordem certa por causa das ligações).
     */
    public function down(): void
    {
        Schema::dropIfExists('curso_prova_tentativas');
        Schema::dropIfExists('curso_prova_alternativas');
        Schema::dropIfExists('curso_prova_questoes');
        Schema::dropIfExists('curso_provas');
    }
};
