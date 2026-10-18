<?php

/*
 * CONFERÊNCIA DO COMPROVANTE (18/10/2026, pedido do Gleyson): o aluno que já
 * enviou o comprovante precisa ver "aguardando conferência" (e não pagar de novo),
 * e o Comando Geral pode RECUSAR o comprovante (ilegível, valor errado...) sem
 * cancelar a matrícula: a vaga continua reservada e o aluno envia outro.
 *
 * - comprovante_enviado_em: quando o último comprovante chegou;
 * - comprovante_recusado_em / comprovante_recusa_motivo: a última recusa e o porquê
 *   (somem quando o aluno envia um novo).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acrescenta as três colunas (todas vazias nas matrículas antigas).
     */
    public function up(): void
    {
        Schema::table('curso_matriculas', function (Blueprint $table) {
            $table->timestamp('comprovante_enviado_em')->nullable()->after('comprovante');
            $table->timestamp('comprovante_recusado_em')->nullable()->after('comprovante_enviado_em');
            $table->string('comprovante_recusa_motivo', 500)->nullable()->after('comprovante_recusado_em');
        });
    }

    /**
     * Desfaz: tira as três colunas.
     */
    public function down(): void
    {
        Schema::table('curso_matriculas', function (Blueprint $table) {
            $table->dropColumn(['comprovante_enviado_em', 'comprovante_recusado_em', 'comprovante_recusa_motivo']);
        });
    }
};
