<?php

/*
 * VENDA DE FOTOS DOS EVENTOS (#90), no modelo das corridas de rua. Começa
 * DESLIGADA (depende da aprovação do Mestre Eduardo).
 *
 * - configuracoes: chaves liga/desliga que o Comando Geral muda pela tela (sem
 *   depender do .env). Ex.: 'venda_fotos_ligada' = '0'.
 * - foto_albuns_venda: o álbum à venda de um CURSO, com o fotógrafo, o preço por
 *   foto, a parte do fotógrafo (%) e quando expira (as fotos são apagadas).
 * - fotos_venda: cada foto, com o ORIGINAL (privado, só sai depois de pago) e a
 *   PRÉVIA reduzida com marca d'água.
 * - pedidos_fotos e pedido_foto_itens: o pedido do aluno (fotos escolhidas, valor,
 *   situação, quem confirmou o pagamento, até quando pode baixar).
 * - papel "fotografo" e as permissões "fotos.vender" (fotógrafo) e
 *   "fotos.gerenciar" (Comando Geral e Tecnologia).
 * Roda no MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as tabelas, as categorias do financeiro, o papel e as permissões.
     */
    public function up(): void
    {
        // Chaves liga/desliga (texto curto). Começa com a venda de fotos DESLIGADA.
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 60)->unique();
            $table->string('valor', 255)->nullable();
            $table->timestamps();
        });
        DB::table('configuracoes')->insert(['chave' => 'venda_fotos_ligada', 'valor' => '0', 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('foto_albuns_venda', function (Blueprint $table) {
            $table->id();
            // O curso do evento: só quem tem matrícula confirmada nele vê as fotos.
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            // O fotógrafo credenciado que sobe as fotos deste álbum.
            $table->foreignId('fotografo_id')->constrained('users')->restrictOnDelete();
            $table->string('titulo', 150);
            // Preço de cada foto, em centavos. Ex.: 1500 = R$ 15,00.
            $table->unsignedInteger('preco_centavos');
            // Parte do fotógrafo, em %. Ex.: 70 (o IBTO fica com 30).
            $table->unsignedTinyInteger('percentual_fotografo')->default(70);
            // Quando as fotos são apagadas (saúde do servidor). Ex.: 60 dias depois do evento.
            $table->date('expira_em');
            $table->boolean('ativo')->default(true);
            // true = as fotos já foram apagadas pela limpeza automática (fica só o histórico).
            $table->boolean('expirado')->default(false);
            $table->timestamps();
        });

        Schema::create('fotos_venda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('foto_albuns_venda')->cascadeOnDelete();
            // Caminhos na pasta PRIVADA. O original só sai depois de pago; a prévia tem a marca d'água.
            $table->string('original', 120);
            $table->string('previa', 120);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('pedidos_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('album_id')->constrained('foto_albuns_venda')->restrictOnDelete();
            // 'aguardando_pagamento', 'pago' ou 'cancelado'.
            $table->string('situacao', 25)->default('aguardando_pagamento');
            $table->unsignedInteger('valor_centavos');
            $table->foreignId('lancamento_id')->nullable()->constrained('fin_lancamentos')->nullOnDelete();
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmado_em')->nullable();
            // Até quando os links de download valem (30 dias depois de pago).
            $table->dateTime('download_ate')->nullable();
            $table->timestamps();
        });

        Schema::create('pedido_foto_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos_fotos')->cascadeOnDelete();
            // A foto pode ter sido apagada pela expiração: o item fica, sem a foto.
            $table->foreignId('foto_id')->nullable()->constrained('fotos_venda')->nullOnDelete();
            $table->unique(['pedido_id', 'foto_id']);
        });

        // Categorias do livro-caixa: a receita da venda e o repasse ao fotógrafo.
        $agora = now();
        foreach (['Venda de fotos' => 'receita', 'Fotógrafos' => 'despesa'] as $nome => $tipo) {
            DB::table('fin_categorias')->insertOrIgnore(['nome' => $nome, 'tipo' => $tipo, 'ativa' => true, 'created_at' => $agora, 'updated_at' => $agora]);
        }

        // O papel "Fotógrafo" (credenciado pelo IBTO).
        $fotografoId = DB::table('papeis')->insertGetId([
            'slug' => 'fotografo', 'nome' => 'Fotógrafo',
            'descricao' => 'Fotógrafo credenciado: sobe as fotos dos eventos à venda e vê só os próprios pedidos e o próprio financeiro.',
            'sistema' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        // As permissões: vender (fotógrafo) e cuidar da venda (Comando Geral e Tecnologia).
        $vender = DB::table('permissoes')->insertGetId(['slug' => 'fotos.vender', 'nome' => 'Subir e vender fotos dos eventos', 'grupo' => 'Fotos', 'ordem' => 90, 'created_at' => $agora, 'updated_at' => $agora]);
        $gerenciar = DB::table('permissoes')->insertGetId(['slug' => 'fotos.gerenciar', 'nome' => 'Cuidar da venda de fotos (álbuns, pedidos e pagamentos)', 'grupo' => 'Fotos', 'ordem' => 91, 'created_at' => $agora, 'updated_at' => $agora]);
        DB::table('papel_permissao')->insert(['papel_id' => $fotografoId, 'permissao_id' => $vender]);
        foreach (['administrador', 'tecnologia'] as $papel) {
            $papelId = DB::table('papeis')->where('slug', $papel)->value('id');
            // Papel ainda não existe (banco de teste vazio): pula, sem erro.
            if ($papelId !== null) {
                DB::table('papel_permissao')->insert(['papel_id' => $papelId, 'permissao_id' => $gerenciar]);
            }
        }
    }

    /**
     * Desfaz: tira permissões, papel, categorias (se não usadas) e as tabelas.
     */
    public function down(): void
    {
        DB::table('permissoes')->whereIn('slug', ['fotos.vender', 'fotos.gerenciar'])->delete();
        DB::table('papeis')->where('slug', 'fotografo')->delete();
        Schema::dropIfExists('pedido_foto_itens');
        Schema::dropIfExists('pedidos_fotos');
        Schema::dropIfExists('fotos_venda');
        Schema::dropIfExists('foto_albuns_venda');
        Schema::dropIfExists('configuracoes');
    }
};
