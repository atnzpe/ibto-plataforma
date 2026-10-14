<?php

/*
 * CHANCELA dos cursos (#40; decisão do Gleyson em 10/10/2026): admin de equipe
 * e instrutor PROPÕEM cursos (permissão configurável "cursos.propor"); o curso
 * só é PUBLICADO depois de CHANCELADO pelo IBTO ("cursos.chancelar").
 * Também traz dois campos dos cartazes de referência: material incluso e
 * contato (WhatsApp). Compatível com o MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permissões novas: slug => [nome na tela, ordem na grade, papéis que já começam com ela].
     *
     * @var array<string, array{0: string, 1: int, 2: list<string>}>
     */
    private const PERMISSOES = [
        'cursos.propor' => ['Propor cursos (enviar para chancela)', 81, ['instrutor', 'admin_equipe']],
        'cursos.chancelar' => ['Chancelar, rejeitar, cancelar e encerrar cursos', 82, ['administrador', 'tecnologia']],
    ];

    /**
     * Cria as colunas e as permissões.
     */
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            // Quem propôs o curso (instrutor ou admin de equipe); vazio = criado pelo IBTO.
            $table->foreignId('responsavel_id')->nullable()->after('instrutor_id')->constrained('users')->nullOnDelete();
            // Equipe dona do curso (curso de equipe); vazio = curso do IBTO.
            $table->foreignId('equipe_id')->nullable()->after('responsavel_id')->constrained('equipes')->restrictOnDelete();
            // O que vem junto (dos cartazes). Ex.: 'Certificado, camisa do evento'.
            $table->string('material_incluso', 500)->nullable()->after('link_pagamento');
            // Telefone de contato (WhatsApp). Ex.: '81 99999-0000'.
            $table->string('contato_whatsapp', 20)->nullable()->after('material_incluso');
            // Motivo da rejeição ou do cancelamento (aparece para quem propôs).
            $table->string('motivo_situacao', 500)->nullable()->after('situacao');
            // Quem chancelou e quando (vazio enquanto não chancelado).
            $table->foreignId('chancelado_por')->nullable()->after('motivo_situacao')->constrained('users')->nullOnDelete();
            $table->timestamp('chancelado_em')->nullable()->after('chancelado_por');
        });

        $agora = now();
        foreach (self::PERMISSOES as $slug => [$nome, $ordem, $papeis]) {
            $permissaoId = DB::table('permissoes')->insertGetId([
                'slug' => $slug, 'nome' => $nome, 'grupo' => 'Cursos', 'ordem' => $ordem,
                'created_at' => $agora, 'updated_at' => $agora,
            ]);
            foreach ($papeis as $papel) {
                DB::table('papel_permissao')->insert([
                    'papel_id' => DB::table('papeis')->where('slug', $papel)->value('id'),
                    'permissao_id' => $permissaoId,
                ]);
            }
        }
    }

    /**
     * Desfaz: apaga as permissões e as colunas.
     */
    public function down(): void
    {
        DB::table('permissoes')->whereIn('slug', array_keys(self::PERMISSOES))->delete();
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chancelado_por');
            $table->dropConstrainedForeignId('equipe_id');
            $table->dropConstrainedForeignId('responsavel_id');
            $table->dropColumn(['material_incluso', 'contato_whatsapp', 'motivo_situacao', 'chancelado_em']);
        });
    }
};
