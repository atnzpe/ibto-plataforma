<?php

/*
 * PAPÉIS e PERMISSÕES da plataforma (#36), no mesmo molde do Painel da CBSA
 * (decisões do Gleyson em 07/10/2026).
 *
 * - papel: um perfil de pessoa (Aluno, Filiado, Instrutor, Administrador, Tecnologia);
 * - permissão: uma coisa que dá para fazer (ex.: "cadastros.equipes" = abrir o cadastro de equipes);
 * - papel_permissao: quais permissões cada papel tem (configurável pela tela, no bloco 3);
 * - papel_usuario: quais papéis cada pessoa tem (uma pessoa pode ter vários).
 *
 * Os papéis e as permissões iniciais entram pela própria migration (servidor sem SSH).
 * Compatível com o MySQL 5.7 do servidor.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Papéis iniciais: slug (identificador fixo usado no código) => [nome, descrição, sistema].
     * "sistema" = papel que a tela não deixa apagar (o código depende dele).
     *
     * @var array<string, array{0: string, 1: string, 2: bool}>
     */
    private const PAPEIS = [
        'aluno' => ['Aluno', 'Qualquer pessoa cadastrada: faz cursos abertos ao público.', true],
        'filiado' => ['Filiado', 'Membro do IBTO com anuidade: acessa os cursos restritos.', true],
        'instrutor' => ['Instrutor', 'Filiado autorizado a criar cursos na sua área.', true],
        'administrador' => ['Administrador', 'Equipe do IBTO: cuida dos cadastros e dos usuários.', true],
        'tecnologia' => ['Tecnologia', 'Time de Tecnologia: o único que mexe nas permissões.', true],
    ];

    /**
     * Permissões iniciais: slug => [nome, grupo, ordem na tela].
     *
     * @var array<string, array{0: string, 1: string, 2: int}>
     */
    private const PERMISSOES = [
        'cadastros.areas' => ['Cadastro de áreas de atuação', 'Cadastros', 10],
        'cadastros.niveis' => ['Cadastro de níveis', 'Cadastros', 20],
        'cadastros.equipes' => ['Cadastro de equipes', 'Cadastros', 30],
        'usuarios.gerenciar' => ['Dar e tirar papéis dos usuários', 'Administração', 40],
        'permissoes.gerenciar' => ['Configurar papéis e permissões', 'Administração', 50],
    ];

    /**
     * Quais permissões cada papel recebe no começo (depois, muda pela tela).
     *
     * @var array<string, list<string>>
     */
    private const VINCULOS = [
        'administrador' => ['cadastros.areas', 'cadastros.niveis', 'cadastros.equipes', 'usuarios.gerenciar'],
        'tecnologia' => ['cadastros.areas', 'cadastros.niveis', 'cadastros.equipes', 'usuarios.gerenciar', 'permissoes.gerenciar'],
    ];

    /**
     * Cria as tabelas e coloca os papéis, as permissões e os vínculos iniciais.
     */
    public function up(): void
    {
        Schema::create('papeis', function (Blueprint $table) {
            $table->id();
            // Identificador fixo, sem acento nem espaço (ex.: "administrador"). Único.
            $table->string('slug', 50)->unique();
            $table->string('nome', 100);
            $table->string('descricao', 255)->nullable();
            // true = a tela não deixa apagar este papel.
            $table->boolean('sistema')->default(false);
            $table->timestamps();
        });

        Schema::create('permissoes', function (Blueprint $table) {
            $table->id();
            // Identificador usado nas rotas (ex.: "cadastros.equipes"). Único.
            $table->string('slug', 100)->unique();
            $table->string('nome', 150);
            // Grupo e ordem só organizam a tela (ex.: "Cadastros", 10).
            $table->string('grupo', 50);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('papel_permissao', function (Blueprint $table) {
            // Apagou o papel ou a permissão: o vínculo some junto (cascade).
            $table->foreignId('papel_id')->constrained('papeis')->cascadeOnDelete();
            $table->foreignId('permissao_id')->constrained('permissoes')->cascadeOnDelete();
            $table->primary(['papel_id', 'permissao_id']);
        });

        Schema::create('papel_usuario', function (Blueprint $table) {
            $table->foreignId('papel_id')->constrained('papeis')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['papel_id', 'user_id']);
        });

        $agora = now();

        foreach (self::PAPEIS as $slug => [$nome, $descricao, $sistema]) {
            DB::table('papeis')->insert(['slug' => $slug, 'nome' => $nome, 'descricao' => $descricao, 'sistema' => $sistema, 'created_at' => $agora, 'updated_at' => $agora]);
        }

        foreach (self::PERMISSOES as $slug => [$nome, $grupo, $ordem]) {
            DB::table('permissoes')->insert(['slug' => $slug, 'nome' => $nome, 'grupo' => $grupo, 'ordem' => $ordem, 'created_at' => $agora, 'updated_at' => $agora]);
        }

        // Liga cada papel às suas permissões iniciais, pelos slugs.
        foreach (self::VINCULOS as $papel => $permissoes) {
            $papelId = DB::table('papeis')->where('slug', $papel)->value('id');
            foreach ($permissoes as $permissao) {
                DB::table('papel_permissao')->insert([
                    'papel_id' => $papelId,
                    'permissao_id' => DB::table('permissoes')->where('slug', $permissao)->value('id'),
                ]);
            }
        }
    }

    /**
     * Desfaz: apaga os vínculos primeiro (eles dependem das outras tabelas).
     */
    public function down(): void
    {
        Schema::dropIfExists('papel_usuario');
        Schema::dropIfExists('papel_permissao');
        Schema::dropIfExists('permissoes');
        Schema::dropIfExists('papeis');
    }
};
