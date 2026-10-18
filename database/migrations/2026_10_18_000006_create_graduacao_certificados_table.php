<?php

/*
 * CERTIFICADO DE GRADUAÇÃO (#46, parte 2; modelo do Mestre Eduardo de 14/10/2026):
 * "Certificamos que [nome] integra o quadro de membros do IBTO e é reconhecido
 * por esta instituição na condição de [condição] [nível]".
 *
 * Cada certificado é uma "foto" do dia da emissão (o nome, a condição e o nível
 * ficam gravados como estavam): se o nível do filiado mudar, sai um novo e o
 * antigo continua como histórico (ou é cancelado com motivo).
 *
 * Dois códigos (decisão do Gleyson, 14/10/2026):
 * - numero_serie: o número institucional IMPRESSO. Ex.: 'GR20260034E0' =
 *   GR + ano da emissão + ordem do registro do filiado + letra do nível + via;
 * - codigo: sorteado, só para o QR e a validação pública (ninguém adivinha a
 *   sequência). Ex.: 'IBTO-7K2M-9QXA'.
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
        Schema::create('graduacao_certificados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filiado_id')->constrained('filiados')->cascadeOnDelete();
            // Código sorteado do QR (mesmo formato do certificado de curso).
            $table->string('codigo', 20)->unique();
            // Número de série impresso. Ex.: 'GR20260034E0'.
            $table->string('numero_serie', 30)->unique();
            // A "foto" do dia: o nome, a condição e o nível como estavam na emissão.
            $table->string('nome', 150);
            // Ex.: 'Instrutor de Krav Maga'.
            $table->string('condicao', 150);
            // Ex.: 'Nível Expert - [E]'.
            $table->string('nivel', 100);
            // Ex.: 'Nacional'.
            $table->string('abrangencia', 60);
            // Ex.: 'Rio de Janeiro - RJ'.
            $table->string('local', 100);
            $table->date('emitido_em');
            $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();
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
        Schema::dropIfExists('graduacao_certificados');
    }
};
