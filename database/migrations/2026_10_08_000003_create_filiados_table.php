<?php

/*
 * FILIADOS do IBTO (#37): o REGISTRO da pessoa no instituto (carteirinha),
 * diferente da CONTA do app (tabela users). Decisão do Gleyson (07/10/2026):
 * todo filiado ganha conta, porque só se entra por indicação; por isso cada
 * filiado aponta para um usuário (user_id).
 *
 * LGPD (a parte mais sensível do projeto):
 * - CPF, nascimento e telefone ficam CRIPTOGRAFADOS (colunas "text");
 * - cpf_hash é a "impressão digital" do CPF (HMAC-SHA256 com a APP_KEY): deixa
 *   o banco barrar CPF repetido sem guardar o CPF legível. Não dá para voltar
 *   do hash para o número.
 *
 * Compatível com o MySQL 5.7 do servidor.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filiados', function (Blueprint $table) {
            $table->id();
            // A conta do app desta pessoa (uma conta, um filiado). O nome e o e-mail
            // ficam na conta. Se a conta for apagada, o registro fica sem conta (null).
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            // Número de registro gerado pela plataforma. Ex.: "BR20260001PE". Único.
            $table->string('registro', 20)->unique();
            // Dados pessoais criptografados.
            $table->text('cpf');
            $table->text('nascimento');
            $table->text('telefone')->nullable();
            // Impressão digital do CPF: 64 letras/números (sha256 em hexadecimal). Única.
            $table->char('cpf_hash', 64)->unique();
            // Onde mora (cidade e sigla do estado).
            $table->string('municipio', 100);
            $table->char('uf', 2);
            // Equipe e nível. Se a equipe ou o nível sumirem do banco, o filiado fica sem (null).
            $table->foreignId('equipe_id')->nullable()->constrained('equipes')->nullOnDelete();
            $table->foreignId('nivel_id')->nullable()->constrained('niveis')->nullOnDelete();
            // ativo ou inativo (a anuidade vem num bloco próprio).
            $table->string('status', 20)->default('ativo');
            $table->timestamps();
        });

        // Áreas de atuação do filiado (várias), como nas equipes.
        Schema::create('area_atuacao_filiado', function (Blueprint $table) {
            $table->foreignId('filiado_id')->constrained('filiados')->cascadeOnDelete();
            $table->foreignId('area_atuacao_id')->constrained('areas_atuacao')->cascadeOnDelete();
            $table->primary(['filiado_id', 'area_atuacao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_atuacao_filiado');
        Schema::dropIfExists('filiados');
    }
};
