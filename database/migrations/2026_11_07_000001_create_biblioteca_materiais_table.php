<?php

/*
 * BIBLIOTECA DE TÉCNICAS (#107, pedido do Alex em 06/11/2026): material de apoio
 * (vídeo e apostila em PDF) para monitores e instrutores revisarem as técnicas,
 * do básico ao avançado, antes de passar ao aluno. Funciona como a biblioteca do
 * Dojo Manager (material liberado por nível), mas sem a playlist do exame de faixa.
 *
 * Esta migration cria:
 *   - a tabela "biblioteca_materiais" (um material = uma técnica, com vídeo e/ou apostila);
 *   - a permissão "biblioteca.ver" (abrir a biblioteca): começa com instrutor e admin de equipe;
 *   - a permissão "biblioteca.gerenciar" (enviar, editar e retirar material): começa
 *     com administrador e tecnologia (o Comando Geral).
 * Monitores e instrutores graduados entram também pelo nível do filiado (regra no
 * Gate "biblioteca.ver", em AppServiceProvider), mesmo sem papel nenhum.
 * As duas permissões podem ser mudadas depois na tela de Permissões.
 * Compatível com o MySQL 5.7 do servidor (só colunas simples).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * As permissões novas: slug => [nome na tela, ordem na grade, papéis que começam com ela].
     * A ordem 96 e 97 põe o grupo "Biblioteca" logo depois do Blog (95).
     *
     * @var array<string, array{0: string, 1: int, 2: list<string>}>
     */
    private const PERMISSOES = [
        'biblioteca.ver' => ['Ver a biblioteca de técnicas', 96, ['instrutor', 'admin_equipe']],
        'biblioteca.gerenciar' => ['Enviar, editar e retirar material da biblioteca', 97, ['administrador', 'tecnologia']],
    ];

    /**
     * Cria a tabela e as permissões, já ligadas aos papéis iniciais.
     */
    public function up(): void
    {
        Schema::create('biblioteca_materiais', function (Blueprint $table) {
            $table->id();
            // Nome da técnica. Ex.: "Defesa contra faca: ataque de cima".
            $table->string('titulo', 150);
            // Explicação curta: o que observar, erros comuns (até 2.000 letras).
            $table->text('descricao')->nullable();
            // Área de atuação (ex.: Krav Maga). Vazio = vale para todas.
            // restrictOnDelete: o banco não deixa apagar uma área que tem material
            // (na plataforma, área nunca é apagada, só desativada).
            $table->foreignId('area_atuacao_id')->nullable()->constrained('areas_atuacao')->restrictOnDelete();
            // Nível mínimo para ver (ex.: Instrutor I). Vazio = todos que abrem a biblioteca.
            $table->foreignId('nivel_minimo_id')->nullable()->constrained('niveis')->restrictOnDelete();
            // Link do vídeo (YouTube não listado ou .mp4/.webm por https).
            $table->string('video_url', 500)->nullable();
            // Caminho do vídeo ENVIADO, na pasta privada (nunca um link público).
            $table->string('video_arquivo')->nullable();
            // Caminho da apostila (PDF), na pasta privada.
            $table->string('apostila')->nullable();
            // false = retirado da biblioteca (some da lista, mas não é apagado).
            $table->boolean('ativo')->default(true);
            // Quem enviou (para a auditoria e para a tela). Conta apagada = fica vazio.
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // A lista abre "só os ativos, por área": este índice deixa a consulta rápida.
            $table->index(['ativo', 'area_atuacao_id']);
        });

        $agora = now();
        // Para cada permissão nova: cria e liga aos papéis iniciais.
        foreach (self::PERMISSOES as $slug => [$nome, $ordem, $papeis]) {
            $permissaoId = DB::table('permissoes')->insertGetId([
                'slug' => $slug,
                'nome' => $nome,
                'grupo' => 'Biblioteca',
                'ordem' => $ordem,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
            foreach ($papeis as $papel) {
                $papelId = DB::table('papeis')->where('slug', $papel)->value('id');
                // Papel ainda não existe (banco de teste vazio): pula, sem erro.
                if ($papelId !== null) {
                    DB::table('papel_permissao')->insert(['papel_id' => $papelId, 'permissao_id' => $permissaoId]);
                }
            }
        }
    }

    /**
     * Desfaz: tira as permissões (as ligações saem junto) e a tabela.
     * Os arquivos enviados continuam na pasta privada; apagar à mão se quiser.
     */
    public function down(): void
    {
        DB::table('permissoes')->whereIn('slug', array_keys(self::PERMISSOES))->delete();
        Schema::dropIfExists('biblioteca_materiais');
    }
};
