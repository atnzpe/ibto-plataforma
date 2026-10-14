<?php

/*
 * CONTEÚDO dos cursos (#39, bloco C2): curso → MÓDULOS → AULAS, na ordem
 * definida pelo instrutor. Mesma lógica de "vídeos e programas" do Dojo
 * Manager (pedido do Gleyson, 10/10/2026):
 * - aula com VÍDEO por link (YouTube vira player incorporado; .mp4/.webm vira
 *   o player do navegador) OU vídeo ENVIADO (curto, até 200 MB, guardado no
 *   disco privado) — a escolha é de quem cadastra (Gleyson, 10/10/2026);
 * - APOSTILA em PDF (o "programa técnico"), guardada fora da parte pública;
 * - módulo com NÍVEL MÍNIMO opcional: o filiado vê o do seu nível e do
 *   seguinte (regra "peso ≤ peso do aluno + 1" do Dojo); gestores veem tudo.
 * Compatível com o MySQL 5.7. Não existe "apagar" pela plataforma: desativa.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as tabelas de módulos e de aulas.
     */
    public function up(): void
    {
        Schema::create('curso_modulos', function (Blueprint $table) {
            $table->id();
            // De qual curso. restrict: curso com conteúdo não some.
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            // Ex.: 'Módulo 1 · Fundamentos'.
            $table->string('titulo', 150);
            // Posição no curso (1 = primeiro).
            $table->unsignedSmallInteger('ordem');
            // Nível mínimo para ver o módulo (vazio = todos os inscritos).
            $table->foreignId('nivel_minimo_id')->nullable()->constrained('niveis')->restrictOnDelete();
            // Desativado some para o aluno, sem apagar.
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['curso_id', 'ordem']);
        });

        Schema::create('curso_aulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('curso_modulos')->restrictOnDelete();
            // Ex.: 'Aula 2 · Defesas e controle'.
            $table->string('titulo', 150);
            // Posição no módulo (1 = primeira).
            $table->unsignedSmallInteger('ordem');
            // Link do vídeo (YouTube ou arquivo .mp4/.webm por https). Vazio = aula sem vídeo.
            $table->string('video_url', 500)->nullable();
            // OU o vídeo ENVIADO (Gleyson, 10/10/2026: "fica a critério do usuário"):
            // caminho no disco PRIVADO. Se existir, ele tem preferência sobre o link.
            $table->string('video_arquivo')->nullable();
            // Texto da aula (o que se pratica, observações).
            $table->text('conteudo')->nullable();
            // Caminho da apostila em PDF no disco PRIVADO (storage/app/private).
            $table->string('apostila')->nullable();
            // Duração aproximada, em minutos. Ex.: 12.
            $table->unsignedSmallInteger('duracao_minutos')->nullable();
            $table->boolean('ativa')->default(true);
            $table->timestamps();
            $table->index(['modulo_id', 'ordem']);
        });
    }

    /**
     * Desfaz: apaga as aulas e depois os módulos.
     */
    public function down(): void
    {
        Schema::dropIfExists('curso_aulas');
        Schema::dropIfExists('curso_modulos');
    }
};
