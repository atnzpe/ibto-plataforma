<?php

/*
 * Cria a tabela das ÁREAS DE ATUAÇÃO do IBTO (#37) e já coloca nela as 15 áreas
 * do site (https://ib-to.org/areas-de-atuacao).
 *
 * As áreas entram aqui, na própria migration, e não num "seeder" (arquivo que
 * só roda quando alguém digita `php artisan db:seed`): o servidor não tem SSH, e
 * a migration roda sozinha pelo Cron (plataforma:pos-deploy). Assim, depois do
 * deploy, o cadastro já nasce com as áreas, sem ninguém digitar.
 *
 * Compatível com o MySQL 5.7 do servidor (só recursos simples).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * As 15 áreas do site, na mesma ordem da página "Áreas de atuação".
     *
     * @var list<string>
     */
    private const AREAS_DO_SITE = [
        'Operador de Armas de Fogo',
        'Tactical Combat Casualty Care',
        'Sobrevivência Urbana',
        'Krav Maga',
        'Combate com Faca',
        'Selva',
        'Tiro Tático',
        'APH Tático',
        'Combate Corpo-a-corpo',
        'Defesa Pessoal',
        'Combate com Bastão',
        'KAPAP',
        'Operações com Drones',
        'Imobilizações Táticas',
        'Close Quarters Battle',
    ];

    /**
     * Cria a tabela e coloca as áreas do site.
     */
    public function up(): void
    {
        Schema::create('areas_atuacao', function (Blueprint $table) {
            // Número de identificação de cada área (1, 2, 3...), criado pelo banco.
            $table->id();
            // Nome da área. "unique": o banco recusa duas áreas com o mesmo nome.
            // 100 caracteres cabem no índice do MySQL 5.7 com acentos (utf8mb4).
            $table->string('nome', 100)->unique();
            // Ativa (true) aparece nas listas; desativada (false) some das opções,
            // mas continua no banco, para os filiados e equipes antigos não ficarem "órfãos".
            $table->boolean('ativa')->default(true);
            // created_at e updated_at: quando a área foi criada e alterada pela última vez.
            $table->timestamps();
        });

        // Mesmo horário para todas as áreas iniciais (a hora em que a migration rodou).
        $agora = now();

        // Monta uma linha por área e grava todas de uma vez (um único comando no banco).
        DB::table('areas_atuacao')->insert(array_map(
            fn (string $nome) => ['nome' => $nome, 'ativa' => true, 'created_at' => $agora, 'updated_at' => $agora],
            self::AREAS_DO_SITE,
        ));
    }

    /**
     * Desfaz a migration: apaga a tabela (e as áreas junto).
     */
    public function down(): void
    {
        Schema::dropIfExists('areas_atuacao');
    }
};
