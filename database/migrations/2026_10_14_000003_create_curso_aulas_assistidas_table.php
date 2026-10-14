<?php

/*
 * PROGRESSO DAS AULAS ONLINE (piloto, 14/10/2026): guarda "esta pessoa
 * marcou esta aula como assistida", e quando. Com isso a sala de aula mostra
 * "3 de 10 aulas (30%)" e o ✓ em cada aula, e "Meus cursos" mostra a barra
 * de progresso e o botão "Continuar" na próxima aula não assistida.
 *
 * Uma linha por pessoa e aula (a mesma aula não conta duas vezes).
 * Desmarcar = apagar a linha: é só um "lembrete" do aluno, não é registro
 * oficial (a presença oficial dos encontros é a chamada, em curso_presencas).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela.
     */
    public function up(): void
    {
        Schema::create('curso_aulas_assistidas', function (Blueprint $table) {
            $table->id();
            // Quem assistiu. Conta apagada = as marcações dela somem junto (cascade).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Qual aula. Aula apagada = as marcações dela somem junto.
            $table->foreignId('aula_id')->constrained('curso_aulas')->cascadeOnDelete();
            // Quando marcou. Ex.: 14/10/2026 10:32.
            $table->timestamp('assistida_em');
            // A mesma pessoa não marca a mesma aula duas vezes.
            $table->unique(['user_id', 'aula_id']);
        });
    }

    /**
     * Desfaz: apaga a tabela.
     */
    public function down(): void
    {
        Schema::dropIfExists('curso_aulas_assistidas');
    }
};
