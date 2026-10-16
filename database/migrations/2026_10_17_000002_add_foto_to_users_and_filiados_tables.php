<?php

/*
 * FOTO DO USUÁRIO E DO FILIADO (#78), com a autorização de imagem (LGPD).
 *
 * - users.foto: caminho da foto de perfil na pasta PRIVADA (storage/app/private).
 *   Ex.: 'fotos/usuarios/9f1c...webp'. Vazio = sem foto (aparecem as iniciais).
 * - filiados.foto: a foto 3x4 do filiado (carteirinha #46 e Rede Nacional #11).
 * - filiados.autoriza_imagem_rede: a pessoa autorizou a foto na Rede Nacional
 *   do site? Começa DESMARCADO (false): sem autorização, a foto nunca sai do
 *   lado de dentro da plataforma.
 * - filiados.autoriza_imagem_em: quando a autorização foi marcada (quem marcou
 *   vai para a auditoria).
 * Roda no MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as colunas. Contas e filiados que já existem ficam sem foto e sem autorização.
     */
    public function up(): void
    {
        // Foto de perfil da conta (a própria pessoa envia em Configurações).
        Schema::table('users', function (Blueprint $table) {
            $table->string('foto', 120)->nullable()->after('cadastro_motivo');
        });

        // Foto 3x4 do filiado e a autorização de imagem (desmarcada por padrão).
        Schema::table('filiados', function (Blueprint $table) {
            $table->string('foto', 120)->nullable();
            $table->boolean('autoriza_imagem_rede')->default(false);
            $table->timestamp('autoriza_imagem_em')->nullable();
        });
    }

    /**
     * Desfaz: tira as colunas. (Os arquivos das fotos ficam na pasta; apagar à mão se preciso.)
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('foto');
        });

        Schema::table('filiados', function (Blueprint $table) {
            $table->dropColumn(['foto', 'autoriza_imagem_rede', 'autoriza_imagem_em']);
        });
    }
};
