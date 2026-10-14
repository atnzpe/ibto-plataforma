<?php

/*
 * PRESENÇA / CHAMADA DO ENCONTRO (#44; desenho aprovado em 10/10/2026: "o
 * admin de equipe (ou o instrutor) marca quem veio e registra o conteúdo da
 * aula; tudo entra no dossiê de cada presente").
 *
 * - curso_presencas: uma linha por inscrito CONFIRMADO em cada encontro
 *   (aula com data), dizendo se esteve presente. A chamada pode ser refeita
 *   (corrige a linha; a auditoria guarda cada versão);
 * - curso_aulas.registro_encontro: o que foi trabalhado no encontro;
 *   chamada_feita_em / chamada_por: quando e quem fez a chamada.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela e as colunas.
     */
    public function up(): void
    {
        Schema::create('curso_presencas', function (Blueprint $table) {
            $table->id();
            // O encontro. cascadeOnDelete não é usado: aulas não se apagam (desativam).
            $table->foreignId('aula_id')->constrained('curso_aulas')->restrictOnDelete();
            // A matrícula (pessoa + curso) de quem foi chamado.
            $table->foreignId('matricula_id')->constrained('curso_matriculas')->restrictOnDelete();
            // true = esteve presente; false = faltou.
            $table->boolean('presente');
            $table->timestamps();

            // Uma linha por pessoa em cada encontro (refazer a chamada atualiza a mesma linha).
            $table->unique(['aula_id', 'matricula_id']);
        });

        Schema::table('curso_aulas', function (Blueprint $table) {
            // O que foi trabalhado no encontro (registrado na chamada). Ex.: "Guarda, base e quedas."
            $table->text('registro_encontro')->nullable()->after('introducao');
            // Quando e quem fez a chamada (vazio = chamada ainda não feita).
            $table->timestamp('chamada_feita_em')->nullable()->after('registro_encontro');
            $table->foreignId('chamada_por')->nullable()->after('chamada_feita_em')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Desfaz: tira as colunas e a tabela.
     */
    public function down(): void
    {
        Schema::table('curso_aulas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chamada_por');
            $table->dropColumn(['registro_encontro', 'chamada_feita_em']);
        });
        Schema::dropIfExists('curso_presencas');
    }
};
