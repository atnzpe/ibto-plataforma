<?php

/*
 * PERMISSÕES DOS RELATÓRIOS (#73): uma por relatório.
 *
 * Quem começa com cada uma:
 * - relatorio.filiados: Administrador, Tecnologia e admin de equipe (este vê só a equipe dele);
 * - relatorio.cursos: Administrador e Tecnologia;
 * - relatorio.frequencia: Administrador, Tecnologia e instrutor (este vê só os cursos dele);
 * - relatorio.financeiro: Administrador e Tecnologia;
 * - relatorio.auditoria: só a Tecnologia.
 * Dá para mudar na tela de Permissões.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * As 5 permissões e quem começa com cada uma.
     *
     * @var array<string, array{nome: string, ordem: int, papeis: list<string>}>
     */
    private const PERMISSOES = [
        'relatorio.filiados' => ['nome' => 'Relatório de filiados por equipe', 'ordem' => 100, 'papeis' => ['administrador', 'tecnologia', 'admin_equipe']],
        'relatorio.cursos' => ['nome' => 'Relatório de cursos (inscritos, receita e repasse)', 'ordem' => 101, 'papeis' => ['administrador', 'tecnologia']],
        'relatorio.frequencia' => ['nome' => 'Relatório de frequência e certificados', 'ordem' => 102, 'papeis' => ['administrador', 'tecnologia', 'instrutor']],
        'relatorio.financeiro' => ['nome' => 'Relatório financeiro por categoria', 'ordem' => 103, 'papeis' => ['administrador', 'tecnologia']],
        'relatorio.auditoria' => ['nome' => 'Relatório de auditoria (quem fez o quê)', 'ordem' => 104, 'papeis' => ['tecnologia']],
    ];

    /**
     * Cria as permissões e liga aos papéis.
     */
    public function up(): void
    {
        $agora = now();
        foreach (self::PERMISSOES as $slug => $dados) {
            $id = DB::table('permissoes')->insertGetId(['slug' => $slug, 'nome' => $dados['nome'], 'grupo' => 'Relatórios', 'ordem' => $dados['ordem'], 'created_at' => $agora, 'updated_at' => $agora]);
            foreach ($dados['papeis'] as $papel) {
                $papelId = DB::table('papeis')->where('slug', $papel)->value('id');
                // Papel ainda não existe (banco de teste vazio): pula, sem erro.
                if ($papelId !== null) {
                    DB::table('papel_permissao')->insert(['papel_id' => $papelId, 'permissao_id' => $id]);
                }
            }
        }
    }

    /**
     * Desfaz: tira as permissões (as ligações saem junto, pela chave estrangeira).
     */
    public function down(): void
    {
        DB::table('permissoes')->whereIn('slug', array_keys(self::PERMISSOES))->delete();
    }
};
