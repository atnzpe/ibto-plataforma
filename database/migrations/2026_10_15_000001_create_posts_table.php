<?php

/*
 * BLOG do IBTO (#61; desenho aprovado pelo Gleyson em 14/10/2026, no molde
 * das notícias do site da CBSA): os POSTS, em duas categorias (Notícias e
 * Eventos), com capa, vídeo, curso ligado, situação, agendamento e a
 * AUTORIZAÇÃO DE USO DE IMAGEM (LGPD).
 *
 * E a permissão "blog.publicar" (escrever, publicar e arquivar), que começa
 * com Administrador e Tecnologia e pode ser delegada depois (grade de Permissões).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela "posts" e a permissão.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            // Título. Ex.: 'Formação de Instrutores em Pernambuco: estande de tiro'.
            $table->string('titulo', 150);
            // Pedaço do endereço, feito do título. Ex.: 'formacao-de-instrutores-em-pernambuco'.
            $table->string('slug', 170)->unique();
            // 'noticia' ou 'evento' (App\Enums\CategoriaPost).
            $table->string('categoria', 20)->default('noticia');
            // O resumo que aparece no cartão da lista (obrigatório para publicar).
            $table->string('resumo', 300)->nullable();
            // O texto do post (as quebras de linha digitadas são mantidas na tela).
            $table->text('texto')->nullable();
            // Caminho da capa no disco público (gravada pelo App\Support\ImagemPublica).
            $table->string('capa')->nullable();
            // A DESCRIÇÃO da capa para quem usa leitor de tela (acessibilidade): obrigatória para publicar.
            $table->string('capa_descricao', 200)->nullable();
            // Crédito da foto. Ex.: 'Divulgação / IBTO'.
            $table->string('credito', 120)->nullable();
            // Link de vídeo (YouTube ou arquivo .mp4/.webm), como nas aulas.
            $table->string('video_url', 500)->nullable();
            // Curso ligado (opcional). Curso apagado: o post fica, sem o link.
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            // 'rascunho' | 'em_revisao' | 'publicado' | 'arquivado' (App\Enums\SituacaoPost).
            $table->string('situacao', 20)->default('rascunho');
            // Quando aparece no site. Futuro = agendado; vazio = na hora de publicar.
            $table->dateTime('publicar_em')->nullable();
            // LGPD: as fotos ou o vídeo mostram pessoas? Então precisa da autorização para publicar.
            $table->boolean('tem_pessoas')->default(false);
            $table->boolean('autorizacao_imagem')->default(false);
            // Como a autorização foi obtida. Ex.: 'Termo assinado na inscrição do curso'.
            $table->string('autorizacao_origem', 200)->nullable();
            // LGPD art. 14 e ECA art. 17: com menores de idade, também a autorização dos responsáveis.
            $table->boolean('tem_menores')->default(false);
            $table->boolean('autorizacao_responsaveis')->default(false);
            // Quem escreveu. Conta apagada: o post fica, sem o autor.
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // O site lista "publicados, por data": este índice deixa a consulta rápida.
            $table->index(['situacao', 'publicar_em']);
        });

        $agora = now();
        $permissaoId = DB::table('permissoes')->insertGetId([
            'slug' => 'blog.publicar',
            'nome' => 'Escrever, publicar e arquivar posts do blog',
            'grupo' => 'Blog',
            // Depois dos cursos e do financeiro na grade de Permissões.
            'ordem' => 95,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
        foreach (['administrador', 'tecnologia'] as $papel) {
            DB::table('papel_permissao')->insert([
                'papel_id' => DB::table('papeis')->where('slug', $papel)->value('id'),
                'permissao_id' => $permissaoId,
            ]);
        }
    }

    /**
     * Desfaz: apaga a permissão (as ligações vão junto) e a tabela.
     */
    public function down(): void
    {
        DB::table('permissoes')->where('slug', 'blog.publicar')->delete();
        Schema::dropIfExists('posts');
    }
};
