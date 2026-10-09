<?php

/*
 * Cria as EQUIPES filiadas ao IBTO (#37) e a ligação equipe ↔ áreas de atuação,
 * e já coloca as 9 equipes da planilha atual com o status "em homologação"
 * (só o nome; o resto é completado pela tela). Decisões do Gleyson em 07/10/2026.
 *
 * LGPD: telefone, e-mail e documento do responsável são dados pessoais. Ficam
 * CRIPTOGRAFADOS (embaralhados com a APP_KEY): quem abrir o banco direto vê só
 * um texto sem sentido. Por isso essas colunas são "text" (o texto criptografado
 * é bem mais longo que o original).
 *
 * Compatível com o MySQL 5.7 do servidor.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * As 9 equipes da planilha "Registro de Filiados IBTO" (Canva), conferidas em 07/10/2026.
     *
     * @var list<string>
     */
    private const EQUIPES_INICIAIS = [
        'Vulcan',
        'Moura Top Team',
        'Street Dogs',
        'Dragão Negro',
        'CT Macena Leão do Nordeste',
        'ALFA COMBAT Treinamentos Táticos',
        'IBTO',
        'ANKM',
        'InstrutorTrinca',
    ];

    /**
     * Cria as tabelas e coloca as equipes iniciais.
     */
    public function up(): void
    {
        Schema::create('equipes', function (Blueprint $table) {
            $table->id();
            // Nome da equipe; o banco recusa nomes repetidos.
            $table->string('nome', 150)->unique();
            // Caminho do arquivo da logo (em storage/app/public), ou null.
            $table->string('logo', 255)->nullable();
            // Cidade e estado (sigla da UF, ex.: "PE"). Vazios enquanto em homologação.
            $table->string('cidade', 100)->nullable();
            $table->char('uf', 2)->nullable();
            // Nome do responsável pela equipe.
            $table->string('responsavel_nome', 150)->nullable();
            // Contato e documento: criptografados (LGPD).
            $table->text('responsavel_telefone')->nullable();
            $table->text('responsavel_email')->nullable();
            $table->text('documento')->nullable();
            // em_homologacao, ativa ou inativa (App\Enums\StatusEquipe).
            $table->string('status', 20)->default('em_homologacao');
            $table->timestamps();
        });

        // Uma equipe pode ter várias áreas, e uma área pode estar em várias
        // equipes: a ligação fica numa tabela própria ("tabela pivô").
        Schema::create('area_atuacao_equipe', function (Blueprint $table) {
            // Se a equipe for apagada do banco, as ligações dela somem junto (cascade).
            $table->foreignId('equipe_id')->constrained('equipes')->cascadeOnDelete();
            $table->foreignId('area_atuacao_id')->constrained('areas_atuacao')->cascadeOnDelete();
            // A mesma área não entra duas vezes na mesma equipe.
            $table->primary(['equipe_id', 'area_atuacao_id']);
        });

        $agora = now();

        // Grava as 9 equipes de uma vez, todas em homologação.
        DB::table('equipes')->insert(array_map(
            fn (string $nome) => ['nome' => $nome, 'status' => 'em_homologacao', 'created_at' => $agora, 'updated_at' => $agora],
            self::EQUIPES_INICIAIS,
        ));
    }

    /**
     * Desfaz: apaga a ligação e as equipes.
     */
    public function down(): void
    {
        Schema::dropIfExists('area_atuacao_equipe');
        Schema::dropIfExists('equipes');
    }
};
