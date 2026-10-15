<?php

/*
 * GALERIA DE EVENTOS do blog (#61, bloco B2; pedido do Mestre Eduardo em
 * 14/10/2026: "um departamento de eventos no site com as fotografias"), no
 * molde da galeria do site da CBSA:
 * - ÁLBUM: a cobertura de um evento (título, data, local), ligado ou não a
 *   um post do blog, com a situação (rascunho, publicado, arquivado) e a
 *   AUTORIZAÇÃO DE USO DE IMAGEM (LGPD; com menores, também a dos responsáveis);
 * - FOTO: cada imagem do álbum, com a DESCRIÇÃO (texto alternativo, para
 *   quem usa leitor de tela), o crédito e a ordem.
 * As regras de publicar ficam em App\Actions\Blog\PublicacaoAlbum.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as duas tabelas.
     */
    public function up(): void
    {
        Schema::create('galeria_albuns', function (Blueprint $table) {
            $table->id();
            // Ex.: 'Formação de Instrutores · estande de tiro (PE)'.
            $table->string('titulo', 150);
            // Pedaço do endereço, feito do título. Ex.: /eventos/formacao-de-instrutores-pe
            $table->string('slug', 170)->unique();
            $table->text('descricao')->nullable();
            // Quando e onde foi o evento. Ex.: 2026-10-11, 'Recife - PE'.
            $table->date('data_evento')->nullable();
            $table->string('local_evento', 150)->nullable();
            // O post do blog ligado (opcional): as fotos aparecem dentro dele.
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            // Crédito usado quando a foto não tem um próprio.
            $table->string('credito_padrao', 120)->default('Divulgação / IBTO');
            // 'rascunho' | 'publicado' | 'arquivado' (App\Enums\SituacaoAlbum).
            $table->string('situacao', 20)->default('rascunho');
            // LGPD: fotos de evento mostram pessoas; só publica com a autorização e a origem dela.
            $table->boolean('autorizacao_imagem')->default(false);
            $table->string('autorizacao_origem', 200)->nullable();
            // LGPD art. 14 e ECA art. 17: menores de idade nas fotos.
            $table->boolean('tem_menores')->default(false);
            $table->boolean('autorizacao_responsaveis')->default(false);
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // O site lista "publicados, do evento mais novo para o mais antigo".
            $table->index(['situacao', 'data_evento']);
        });

        Schema::create('galeria_fotos', function (Blueprint $table) {
            $table->id();
            // Álbum apagado: as fotos dele vão junto.
            $table->foreignId('album_id')->constrained('galeria_albuns')->cascadeOnDelete();
            // Caminho no disco público (nome aleatório, gravado pelo ImagemPublica).
            $table->string('caminho');
            // A DESCRIÇÃO (texto alternativo): pode ser enviada vazia e descrita depois,
            // mas o álbum só é publicado com todas descritas (mínimo de 5 letras).
            $table->string('descricao', 200)->nullable();
            // Crédito desta foto; vazio = o crédito padrão do álbum.
            $table->string('credito', 120)->nullable();
            // A ordem no álbum (1, 2, 3...).
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->timestamps();
            $table->index(['album_id', 'ordem']);
        });
    }

    /**
     * Desfaz: apaga as fotos e os álbuns (nesta ordem, por causa da ligação).
     */
    public function down(): void
    {
        Schema::dropIfExists('galeria_fotos');
        Schema::dropIfExists('galeria_albuns');
    }
};
