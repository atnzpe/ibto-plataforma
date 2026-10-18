<?php

/*
 * Testes da CHAMADA DAS AULAS REGULARES (#80): o instrutor do programa vê as
 * turmas dele e faz a chamada; só alunos em dia (ou na carência) entram na lista;
 * a frequência aparece no dossiê; outro instrutor recebe 403; data futura é
 * recusada; corrigir a chamada não duplica. Dados INVENTADOS.
 */

use App\Actions\AulasRegulares\Assinaturas;
use App\Models\FinFormaPagamento;
use App\Models\Papel;
use App\Models\Programa;
use App\Models\ProgramaPresenca;
use App\Models\ProgramaTurma;
use App\Models\User;

beforeEach(function () {
    $this->instrutor = User::factory()->create();
    $this->instrutor->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->programa = Programa::create(['titulo' => 'Kapap presencial', 'modalidade' => 'presencial', 'valor_mensal_centavos' => 10000, 'percentual_ibto' => 20, 'dia_vencimento' => 10, 'ativo' => true, 'so_filiados' => false, 'instrutor_id' => $this->instrutor->id]);
    $this->turma = new ProgramaTurma(['nome' => 'Noite', 'dias_semana' => 'ter', 'hora_inicio' => '19:00', 'ativa' => true]);
    $this->turma->forceFill(['programa_id' => $this->programa->id])->save();

    $acoes = app(Assinaturas::class);
    $pix = (int) FinFormaPagamento::where('nome', 'PIX')->value('id');
    // Um aluno em dia e um que ainda não pagou (fica fora da lista).
    $this->emDia = User::factory()->create(['name' => 'Aluno Em Dia']);
    $assinatura = $acoes->assinar($this->programa, $this->emDia, $this->turma->id);
    $acoes->confirmarPagamento($assinatura->pagamentos()->sole(), today()->toDateString(), $pix);
    $acoes->assinar($this->programa, User::factory()->create(['name' => 'Aluno Sem Pagar']), $this->turma->id);
    $this->assinaturaEmDia = $assinatura;
});

test('o instrutor vê a turma e a lista só com quem está em dia', function () {
    $this->actingAs($this->instrutor)->get(route('aulas.turmas'))->assertInertia(fn ($tela) => $tela->has('programas', 1));
    $this->actingAs($this->instrutor)->get(route('aulas.chamada', $this->turma))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->has('alunos', 1)->where('alunos.0.nome', 'Aluno Em Dia'));
});

test('salvar a chamada grava a presença, não duplica ao corrigir e vai para o dossiê', function () {
    $hoje = today()->toDateString();
    $this->actingAs($this->instrutor)->post(route('aulas.chamada.salvar', $this->turma), ['data' => $hoje, 'presentes' => [$this->assinaturaEmDia->id], 'conteudo' => 'Defesa contra estrangulamento.'])->assertSessionHasNoErrors();
    $this->actingAs($this->instrutor)->post(route('aulas.chamada.salvar', $this->turma), ['data' => $hoje, 'presentes' => [$this->assinaturaEmDia->id], 'conteudo' => 'Defesa contra estrangulamento.']);

    expect(ProgramaPresenca::count())->toBe(1)->and(ProgramaPresenca::sole()->presente)->toBeTrue();

    $this->actingAs($this->emDia)->get(route('meu-dossie'))->assertInertia(fn ($tela) => $tela
        ->where('aulas_regulares.0.programa', 'Kapap presencial')
        ->where('aulas_regulares.0.meses.0.presencas', 1)
        ->where('aulas_regulares.0.meses.0.aulas', 1));
});

test('outro instrutor recebe 403 e data futura é recusada', function () {
    $outro = User::factory()->create();
    $outro->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->actingAs($outro)->get(route('aulas.chamada', $this->turma))->assertForbidden();

    $this->actingAs($this->instrutor)->post(route('aulas.chamada.salvar', $this->turma), ['data' => today()->addDay()->toDateString()])->assertSessionHasErrors('data');
});

test('o conteúdo da aula é obrigatório e vai para o diário do dossiê', function () {
    $hoje = today()->toDateString();
    // Sem dizer o que foi ministrado: não salva.
    $this->actingAs($this->instrutor)->post(route('aulas.chamada.salvar', $this->turma), ['data' => $hoje, 'presentes' => [$this->assinaturaEmDia->id]])->assertSessionHasErrors('conteudo');

    $this->actingAs($this->instrutor)->post(route('aulas.chamada.salvar', $this->turma), ['data' => $hoje, 'presentes' => [$this->assinaturaEmDia->id], 'conteudo' => 'Quedas e rolamentos.'])->assertSessionHasNoErrors();

    $this->actingAs($this->emDia)->get(route('meu-dossie'))->assertInertia(fn ($tela) => $tela
        ->where('aulas_regulares.0.presencas', 1)
        ->where('aulas_regulares.0.percentual', 100)
        ->where('aulas_regulares.0.diario.0.conteudo', 'Quedas e rolamentos.')
        ->where('aulas_regulares.0.diario.0.presente', true));
});
