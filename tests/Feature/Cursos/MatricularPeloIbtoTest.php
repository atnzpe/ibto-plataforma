<?php

/*
 * Testes do "MATRICULAR PESSOA" no Comando Geral (14/10/2026): o IBTO
 * matricula pelo registro do filiado ou pelo e-mail da conta; é o único jeito
 * de entrar num curso FECHADO; as regras da matrícula continuam valendo
 * (ex.: vagas); quem não é do IBTO recebe 403. Dados INVENTADOS.
 */

use App\Actions\Filiados\CriarFiliado;
use App\Enums\SituacaoCurso;
use App\Models\Curso;
use App\Models\Equipe;
use App\Models\Papel;
use App\Models\User;

beforeEach(function () {
    // O IBTO: uma conta com o papel administrador (tem cursos.gerenciar).
    $this->ibto = User::factory()->create();
    $this->ibto->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    // Um curso FECHADO, publicado, pago e com data futura.
    $this->curso = new Curso([
        'titulo' => 'Formação de instrutor', 'modalidade' => 'presencial', 'acesso' => 'aberto',
        'valor_centavos' => 50000, 'data_inicio' => now()->addMonth()->toDateString(), 'local' => 'Recife', 'vagas' => 1,
    ]);
    $this->curso->forceFill(['situacao' => SituacaoCurso::Publicado, 'fechado' => true])->save();

    // A pessoa a matricular (e-mail inventado).
    $this->aluno = User::factory()->create(['email' => 'aluno.teste@exemplo.com']);
});

test('o IBTO matricula pelo e-mail, mesmo em curso fechado', function () {
    $this->actingAs($this->ibto)
        ->post(route('comando-geral.cursos.matricular', $this->curso), ['pessoa' => '  ALUNO.teste@exemplo.com '])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('sucesso');

    expect($this->curso->matriculas()->where('user_id', $this->aluno->id)->exists())->toBeTrue();
});

test('o IBTO matricula pelo registro do filiado (minúsculas também valem)', function () {
    // Um filiado com conta, criado como a importação faz (o registro é calculado
    // pela plataforma). CPF de EXEMPLO público, que não é de ninguém.
    $filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Filiado Teste', 'email' => 'filiado.teste@exemplo.com', 'cpf' => '529.982.247-25',
        'nascimento' => '1990-01-01', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => Equipe::factory()->create()->id,
    ]);

    $this->actingAs($this->ibto)
        ->post(route('comando-geral.cursos.matricular', $this->curso), ['pessoa' => mb_strtolower($filiado->registro)])
        ->assertSessionHasNoErrors();

    expect($this->curso->matriculas()->count())->toBe(1);
});

test('registro ou e-mail que não existe: avisa com o código e não grava', function () {
    $this->actingAs($this->ibto)
        ->post(route('comando-geral.cursos.matricular', $this->curso), ['pessoa' => 'ninguem@exemplo.com'])
        ->assertSessionHasErrors('matricular');

    expect(session('errors')->first('matricular'))->toContain('PESSOA_NAO_ENCONTRADA');
    expect($this->curso->matriculas()->count())->toBe(0);
});

test('as regras continuam valendo: sem vaga, recusa com o código', function () {
    // A única vaga é ocupada.
    $this->actingAs($this->ibto)->post(route('comando-geral.cursos.matricular', $this->curso), ['pessoa' => 'aluno.teste@exemplo.com']);
    User::factory()->create(['email' => 'outro@exemplo.com']);

    $this->post(route('comando-geral.cursos.matricular', $this->curso), ['pessoa' => 'outro@exemplo.com'])
        ->assertSessionHasErrors('matricular');
    expect(session('errors')->first('matricular'))->toContain('VAGAS_ESGOTADAS');
});

test('quem não é do IBTO não matricula ninguém (403)', function () {
    $instrutor = User::factory()->create();
    $instrutor->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));

    $this->actingAs($instrutor)
        ->post(route('comando-geral.cursos.matricular', $this->curso), ['pessoa' => 'aluno.teste@exemplo.com'])
        ->assertForbidden();
    expect($this->curso->matriculas()->count())->toBe(0);
});

test('a tela de detalhes mostra o formulário só para o IBTO', function () {
    $this->actingAs($this->ibto)->get(route('comando-geral.cursos.inscritos', $this->curso))
        ->assertInertia(fn ($tela) => $tela->where('pode_matricular', true)->where('fechado', true));
});
