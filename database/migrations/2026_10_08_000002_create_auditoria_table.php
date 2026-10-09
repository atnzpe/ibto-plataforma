<?php

/*
 * AUDITORIA (#36): registro de quem mudou o quê e quando, como no Painel da CBSA.
 * Ex.: "usuário 5 tirou a permissão cadastros.equipes do papel Administrador".
 *
 * LGPD: guarda só números e códigos (id do usuário, slugs), nunca e-mail, nome,
 * CPF ou IP. Compatível com o MySQL 5.7 (o tipo JSON existe desde a 5.7.8).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            // Quem fez. Se a conta for apagada, o registro fica, sem o vínculo (null).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Código estável da ação. Ex.: PERMISSAO_CONCEDIDA, PERMISSAO_REMOVIDA.
            $table->string('acao', 60)->index();
            // Detalhes em JSON (texto estruturado). Ex.: {"papel":"administrador","permissao":"cadastros.equipes"}
            $table->json('detalhes')->nullable();
            // Só a data de criação: registro de auditoria não é alterado depois.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
