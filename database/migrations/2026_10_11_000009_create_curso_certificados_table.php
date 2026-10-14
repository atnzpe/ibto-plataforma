<?php

/*
 * CERTIFICADOS (#44: "certificado ao concluir o curso: nome do aluno, curso,
 * carga horária, data, instrutor e código único; página pública de
 * validação; certificado cancelado deixa de validar").
 *
 * O certificado guarda uma "FOTO" dos dados no dia da emissão (nome, curso,
 * carga horária, instrutor, data de conclusão): se o curso mudar depois, o
 * certificado emitido não muda (como a nota fiscal, que não muda quando o
 * preço do produto muda). Cancelar não apaga: marca e deixa de validar.
 * Emitido só pelo IBTO (cursos.gerenciar ou cursos.chancelar).
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
        Schema::create('curso_certificados', function (Blueprint $table) {
            $table->id();
            // Uma matrícula, no máximo um certificado.
            $table->foreignId('matricula_id')->unique()->constrained('curso_matriculas')->restrictOnDelete();
            // Código público único. Ex.: 'IBTO-7K2M-9QXA'.
            $table->string('codigo', 20)->unique();
            // A "foto" do dia da emissão.
            $table->string('nome', 150);
            $table->string('curso', 150);
            $table->unsignedSmallInteger('carga_horaria')->nullable();
            $table->string('instrutor', 150)->nullable();
            $table->date('concluido_em');
            // Quem emitiu e quando.
            $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('emitido_em');
            // Cancelamento (não apaga: deixa de validar).
            $table->timestamp('cancelado_em')->nullable();
            $table->string('motivo_cancelamento', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Desfaz: apaga a tabela.
     */
    public function down(): void
    {
        Schema::dropIfExists('curso_certificados');
    }
};
