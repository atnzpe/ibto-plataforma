<?php

/*
 * TOUR GUIADO (#68): duas colunas na conta de cada pessoa.
 * - tour_ativo: o interruptor "Tour guiado" do menu do usuário. Conta nova
 *   começa LIGADA (o tour aparece na primeira visita de cada tela).
 * - tours_vistos: a lista das telas cujo tour a pessoa já viu (só o nome
 *   técnico da tela, nenhum dado pessoal). Ex.: ["comando-geral","meus-cursos"].
 * Ficam na conta (e não no navegador) para valer no computador e no celular.
 * Roda no MySQL 5.7 (o tipo JSON existe desde o 5.7.8).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as duas colunas.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ligado por padrão: quem entra pela primeira vez já ganha o tour.
            $table->boolean('tour_ativo')->default(true)->after('remember_token');
            // Lista de telas já vistas; null = nenhuma ainda.
            $table->json('tours_vistos')->nullable()->after('tour_ativo');
        });
    }

    /**
     * Desfaz: tira as duas colunas.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tour_ativo', 'tours_vistos']);
        });
    }
};
