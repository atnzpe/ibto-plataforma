<?php

/*
 * Testes do MEU PAINEL (#79): o instrutor vê só os cursos dele; os números batem
 * com as matrículas, a chamada e o livro-caixa (centavos); nenhum dado pessoal;
 * o CSV sai; quem não é instrutor nem admin de equipe recebe 403.
 * Dados INVENTADOS.
 */

use App\Actions\AulasRegulares\Assinaturas;
use App\Actions\Cursos\Matriculas;
use App\Enums\SituacaoCurso;
use App\Models\Curso;
use App\Models\CursoModulo;
use App\Models\CursoPresenca;
use App\Models\FinFormaPagamento;
use App\Models\Papel;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->instrutor = User::factory()->create();
    $this->instrutor->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));

    // Curso pago do instrutor (R$ 250,00, 20% do IBTO → 80% dele), com um encontro.
    $this->curso = new Curso(['titulo' => 'Combate Corpo a Corpo', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 25000, 'data_inicio' => today()->toDateString(), 'local' => 'Recife']);
    $this->curso->forceFill(['situacao' => SituacaoCurso::Publicado, 'responsavel_id' => $this->instrutor->id, 'percentual_ibto' => 20])->save();
    $modulo = CursoModulo::create(['curso_id' => $this->curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
    $this->encontro = $modulo->aulas()->create(['titulo' => 'Encontro', 'ordem' => 1, 'ativa' => true, 'data' => today()->toDateString()]);

    // 2 pagos e confirmados, 1 aguardando o Pix.
    $matriculas = app(Matriculas::class);
    $pix = (int) FinFormaPagamento::where('nome', 'PIX')->value('id');
    $this->m1 = $matriculas->confirmarPagamento($matriculas->matricular($this->curso, User::factory()->create(['name' => 'Aluno Secreto'])), today()->toDateString(), $pix);
    $this->m2 = $matriculas->confirmarPagamento($matriculas->matricular($this->curso, User::factory()->create()), today()->toDateString(), $pix);
    $matriculas->matricular($this->curso, User::factory()->create());

    // Chamada: 1 presente de 2.
    $this->encontro->forceFill(['chamada_feita_em' => now()])->save();
    // forceFill: os campos da presença não vêm de formulário (proteção do modelo).
    (new CursoPresenca)->forceFill(['aula_id' => $this->encontro->id, 'matricula_id' => $this->m1->id, 'presente' => true])->save();
    (new CursoPresenca)->forceFill(['aula_id' => $this->encontro->id, 'matricula_id' => $this->m2->id, 'presente' => false])->save();
});

test('os números batem: inscritos, comparecimento e a receber (80% de 2 × R$ 250,00)', function () {
    $this->actingAs($this->instrutor)->get(route('meu-painel'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela
            ->has('cursos', 1)
            ->where('cursos.0.confirmados', 2)
            ->where('cursos.0.aguardando', 1)
            ->where('cursos.0.comparecimento', 50)
            ->where('cursos.0.a_receber_centavos', 40000)
            ->where('cursos.0.recebido_centavos', 0)
            ->where('totais.inscritos', 3)
            // Nenhum nome de aluno vai para a tela.
            ->missing('cursos.0.alunos'));

    expect($this->actingAs($this->instrutor)->get(route('meu-painel'))->getContent())->not->toContain('Aluno Secreto');
});

test('o instrutor não vê o curso de outro instrutor', function () {
    $outro = User::factory()->create();
    $outro->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));

    $this->actingAs($outro)->get(route('meu-painel'))->assertInertia(fn ($tela) => $tela->has('cursos', 0));
});

test('quem não é instrutor nem admin de equipe recebe 403; o CSV sai para o instrutor', function () {
    $this->actingAs(User::factory()->create())->get(route('meu-painel'))->assertForbidden();

    $resposta = $this->actingAs($this->instrutor)->get(route('meu-painel.csv'));
    $resposta->assertOk();
    expect($resposta->streamedContent())->toContain('Combate Corpo a Corpo')->toContain('400,00');
});

test('aulas regulares que ministro e a saúde financeira (cursos + aulas) aparecem no painel', function () {
    // Programa do instrutor: R$ 100,00/mês, 20% do IBTO. Um aluno pagou; outro não.
    $programa = Programa::create(['titulo' => 'Krav Maga', 'modalidade' => 'presencial', 'valor_mensal_centavos' => 10000, 'percentual_ibto' => 20, 'dia_vencimento' => 10, 'ativo' => true, 'so_filiados' => false, 'instrutor_id' => $this->instrutor->id]);
    $acoes = app(Assinaturas::class);
    $pago = $acoes->assinar($programa, User::factory()->create());
    $acoes->confirmarPagamento($pago->pagamentos()->sole(), today()->toDateString(), (int) FinFormaPagamento::where('nome', 'PIX')->value('id'));
    $acoes->assinar($programa, User::factory()->create());

    $this->actingAs($this->instrutor)->get(route('meu-painel'))
        ->assertInertia(fn ($tela) => $tela
            ->where('aulas.programas.0.nome', 'Krav Maga')
            ->where('aulas.alunos', 2)
            // Ninguém foi repassado ainda: recebido 0.
            ->where('saude.recebido_centavos', 0)
            // Pendente: 2 × R$ 200,00 (cursos) + R$ 80,00 (aula paga) = R$ 480,00.
            ->where('saude.pendente_centavos', 48000)
            // A vencer: a parte dele na mensalidade ainda não paga (R$ 80,00).
            ->where('saude.a_vencer_centavos', 8000)
            ->where('saude.total_a_receber_centavos', 56000));
});
