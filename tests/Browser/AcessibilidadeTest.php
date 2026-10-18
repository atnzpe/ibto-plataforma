<?php

/*
 * AUDITORIA AUTOMÁTICA DE ACESSIBILIDADE (#74, 18/10/2026): o Pest abre as
 * telas num navegador de verdade (Chromium, pelo Playwright) e roda o axe, a
 * ferramenta que confere as regras da WCAG (contraste, rótulos, títulos,
 * nomes dos botões, idioma da página...). Uma falha de acessibilidade nova
 * deixa a CI vermelha, como um teste comum. Dados INVENTADOS.
 */

use App\Enums\SituacaoCurso;
use App\Models\Curso;
use App\Models\Papel;
use App\Models\User;

beforeEach(function () {
    // Um curso publicado, para o catálogo e a página do curso não ficarem vazios.
    $this->curso = new Curso(['titulo' => 'Combate com Faca', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 25000, 'data_inicio' => today()->addMonth()->toDateString(), 'local' => 'Recife', 'instrutor_nome' => 'Marcelo']);
    $this->curso->forceFill(['situacao' => SituacaoCurso::Publicado, 'percentual_ibto' => 20])->save();
});

test('telas públicas sem falhas de acessibilidade (axe)', function (string $endereco) {
    visit($endereco)->assertNoAccessibilityIssues();
})->with([
    'entrar' => '/login',
    'esqueci a senha' => '/forgot-password',
    'catálogo' => '/cursos',
    'privacidade' => '/privacidade',
]);

test('página do curso sem falhas de acessibilidade (axe)', function () {
    visit("/cursos/{$this->curso->id}")->assertNoAccessibilityIssues();
});

test('"Meus cursos" e "Meu dossiê" do aluno sem falhas de acessibilidade (axe)', function () {
    $this->actingAs(User::factory()->create());

    visit('/meus-cursos')->assertPathIs('/meus-cursos')->assertNoAccessibilityIssues();
    visit('/meu-dossie')->assertPathIs('/meu-dossie')->assertNoAccessibilityIssues();
});

test('telas do Comando Geral sem falhas de acessibilidade (axe)', function (string $endereco) {
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin);

    // assertPathIs: confere que a tela abriu de verdade (não caiu na de entrar).
    visit($endereco)->assertPathIs($endereco)->assertNoAccessibilityIssues();
})->with([
    'comando geral' => '/comando-geral',
    'financeiro' => '/financeiro',
    'relatórios' => '/relatorios',
    'relatório de cursos' => '/relatorios/cursos',
    'aulas regulares (financeiro)' => '/relatorios/aulas-regulares',
    'cursos' => '/comando-geral/cursos',
    'matrículas' => '/comando-geral/matriculas',
    'níveis' => '/cadastros/niveis',
    'ajuda' => '/ajuda',
]);
