<?php

/*
 * Testes do CERTIFICADO (#44). Critério de aceite da issue: "código inventado
 * não valida; certificado real valida sem expor dados pessoais".
 * Também: presença mínima de 75%, um certificado por matrícula, a "foto"
 * dos dados, só o IBTO emite, cancelado deixa de validar, quem vê a página
 * do certificado, nome abreviado e "Ver certificado" em Meus cursos.
 * Dados INVENTADOS.
 */

use App\Actions\Cursos\Certificados;
use App\Actions\Cursos\Matriculas;
use App\Models\Curso;
use App\Models\CursoCertificado;
use App\Models\CursoModulo;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    // Curso gratuito presencial, com 4 encontros.
    $this->curso = Curso::create(['titulo' => 'Combate Corpo a Corpo', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0, 'carga_horaria' => 16, 'instrutor_nome' => 'Trinca', 'data_inicio' => today()->toDateString(), 'local' => 'Recife']);
    $this->curso->forceFill(['situacao' => 'publicado'])->save();
    $modulo = CursoModulo::create(['curso_id' => $this->curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
    $this->encontros = collect(range(1, 4))->map(fn ($n) => $modulo->aulas()->create(['titulo' => "Encontro {$n}", 'ordem' => $n, 'ativa' => true, 'data' => today()->toDateString()]));

    $this->aluno = User::factory()->create(['name' => 'Gleyson Atanazio da Silva']);
    $this->matricula = app(Matriculas::class)->matricular($this->curso, $this->aluno);
});

/**
 * Faz a chamada dos encontros pedidos, com o aluno presente em `presente` deles.
 *
 * @param  int  $encontros  Quantos encontros tiveram chamada. Ex.: 4
 * @param  int  $presente  Em quantos ele veio. Ex.: 3
 */
function chamadas($teste, int $encontros, int $presente): void
{
    foreach ($teste->encontros->take($encontros) as $indice => $aula) {
        $teste->actingAs($teste->admin)->put(route('comando-geral.cursos.chamada.update', ['curso' => $teste->curso->id, 'aula' => $aula->id]), [
            'presentes' => $indice < $presente ? [$teste->matricula->id] : [],
            'registro_encontro' => 'Guarda e base.',
        ]);
    }
}

test('presença abaixo de 75% não emite; com 75% emite, com a "foto" dos dados e o código', function () {
    chamadas($this, 4, 2);
    expect(app(Certificados::class)->apto($this->matricula)['motivo'])->toBe('Presença de 50% (mínimo 75%).');

    chamadas($this, 4, 3);
    $this->actingAs($this->admin)
        ->post(route('comando-geral.cursos.certificados.emitir', ['curso' => $this->curso->id, 'matricula' => $this->matricula->id]))
        ->assertSessionHas('sucesso');

    $certificado = CursoCertificado::firstOrFail();
    expect($certificado->codigo)->toMatch('/^IBTO-[A-Z2-9]{4}-[A-Z2-9]{4}$/');
    expect($certificado->nome)->toBe('Gleyson Atanazio da Silva');
    expect($certificado->carga_horaria)->toBe(16);
    expect($certificado->instrutor)->toBe('Trinca');

    // A "foto" não muda quando o curso muda depois.
    $this->curso->update(['titulo' => 'Outro nome']);
    expect($certificado->fresh()->curso)->toBe('Combate Corpo a Corpo');
});

test('um certificado por matrícula: emitir de novo é recusado', function () {
    chamadas($this, 1, 1);
    $emitir = route('comando-geral.cursos.certificados.emitir', ['curso' => $this->curso->id, 'matricula' => $this->matricula->id]);
    $this->actingAs($this->admin)->post($emitir);
    $this->actingAs($this->admin)->post($emitir)->assertSessionHasErrors('certificado');

    expect(CursoCertificado::count())->toBe(1);
});

test('só o IBTO emite: o instrutor dono do curso recebe 403', function () {
    $dono = User::factory()->create();
    $dono->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->curso->forceFill(['responsavel_id' => $dono->id])->save();

    $this->actingAs($dono)
        ->post(route('comando-geral.cursos.certificados.emitir', ['curso' => $this->curso->id, 'matricula' => $this->matricula->id]))
        ->assertForbidden();
});

test('ACEITE: código inventado não valida; o real valida com o nome abreviado; cancelado deixa de valer', function () {
    $this->get(route('certificados.validar', 'IBTO-XXXX-XXXX'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('certificados/validar')->where('certificado', null));

    chamadas($this, 1, 1);
    $certificado = app(Certificados::class)->emitir($this->matricula);

    $this->get(route('certificados.validar', strtolower($certificado->codigo)))
        ->assertInertia(fn ($tela) => $tela
            ->where('certificado.valido', true)
            ->where('certificado.nome', 'Gleyson A. S.')
            ->where('certificado.curso', 'Combate Corpo a Corpo'))
        // Sem dados pessoais: nem o nome inteiro, nem o e-mail.
        ->assertDontSee('Atanazio')
        ->assertDontSee($this->aluno->email);

    app(Certificados::class)->cancelar($certificado, 'Emitido por engano');
    $this->get(route('certificados.validar', $certificado->codigo))
        ->assertInertia(fn ($tela) => $tela->where('certificado.valido', false));
});

test('a página do certificado: o dono vê; outra pessoa não (403)', function () {
    chamadas($this, 1, 1);
    $certificado = app(Certificados::class)->emitir($this->matricula);

    $this->actingAs($this->aluno)->get(route('certificados.ver', $certificado->codigo))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('certificados/ver')
            ->where('certificado.nome', 'Gleyson Atanazio da Silva')
            // O local do curso (rodapé do modelo institucional, 15/10/2026).
            ->where('certificado.local', 'Recife')
            // O QR code vem pronto como desenho SVG (sem a linha "xml" do começo).
            ->where('certificado.qr_svg', fn (string $svg) => str_starts_with($svg, '<svg')));
    $this->actingAs(User::factory()->create())->get(route('certificados.ver', $certificado->codigo))->assertForbidden();
});

test('emitir todos os aptos: só quem tem a presença mínima', function () {
    $faltoso = app(Matriculas::class)->matricular($this->curso, User::factory()->create());
    // O aluno vem nos 4; o faltoso em nenhum.
    chamadas($this, 4, 4);

    $this->actingAs($this->admin)
        ->post(route('comando-geral.cursos.certificados.aptos', $this->curso))
        ->assertSessionHas('sucesso', fn (string $mensagem) => str_contains($mensagem, '1 certificado(s) emitido(s)') && str_contains($mensagem, '1 inscrito(s) sem a presença mínima'));

    expect(CursoCertificado::where('matricula_id', $faltoso->id)->exists())->toBeFalse();
});

test('Meus cursos mostra o certificado válido', function () {
    chamadas($this, 1, 1);
    $certificado = app(Certificados::class)->emitir($this->matricula);

    $this->actingAs($this->aluno)->get(route('meus-cursos'))
        ->assertInertia(fn ($tela) => $tela->where('cursos.0.certificado', $certificado->codigo));
});
