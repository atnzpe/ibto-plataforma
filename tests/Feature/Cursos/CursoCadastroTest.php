<?php

/*
 * Testes do CADASTRO DE CURSOS E TRILHAS no Comando Geral (#39 / #38, bloco C1):
 * permissão, as três modalidades de acesso (gratuito é só para filiados),
 * presencial exige data e local, valor em centavos, imagem, trilha em ordem,
 * rascunho não vaza pela imagem e não existe apagar. Dados INVENTADOS.
 */

use App\Enums\SituacaoCurso;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\Papel;
use App\Models\Trilha;
use App\Models\User;
use App\Services\FluxoCurso;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($this->admin);
});

/**
 * Campos de um curso presencial válido; o teste troca só o que precisa.
 *
 * @param  array<string, mixed>  $troca  Ex.: ['valor' => '']
 * @return array<string, mixed>
 */
function cursoValido(array $troca = []): array
{
    return [
        'titulo' => 'Combate Corpo a Corpo',
        'descricao' => 'Fundamentos.',
        'modalidade' => 'presencial',
        'acesso' => 'aberto',
        'valor' => '150,00',
        'vagas' => 20,
        'data_inicio' => '2026-11-10',
        'data_fim' => '2026-11-11',
        'hora_inicio' => '08:00',
        'hora_fim' => '17:00',
        'local' => 'Recife · PE',
        'carga_horaria' => 16,
        'situacao' => 'publicado',
        ...$troca,
    ];
}

test('aluno não entra nos cursos do Comando Geral (403)', function () {
    $aluno = User::factory()->create();
    $aluno->papeis()->attach(Papel::where('slug', 'aluno')->value('id'));

    $this->actingAs($aluno)->get(route('comando-geral.cursos.index'))->assertForbidden();
});

test('curso presencial pago: "150,00" vira 15000 centavos, com auditoria', function () {
    $this->post(route('comando-geral.cursos.store'), cursoValido())
        ->assertRedirect(route('comando-geral.cursos.edit', Curso::first()));

    $curso = Curso::first();
    // Todo curso nasce rascunho (publicar é pela chancela, #40), com quem propôs.
    expect($curso->situacao)->toBe(SituacaoCurso::Rascunho);
    expect($curso->responsavel_id)->toBe($this->admin->id);
    expect($curso->valor_centavos)->toBe(15000);
    expect($curso->tipo())->toBe('Pago · aberto ao público');
    expect(Auditoria::where('acao', 'CURSO_CRIADO')->exists())->toBeTrue();
});

test('gratuito e aberto é recusado (CURSO_GRATUITO_ABERTO); gratuito e restrito pode', function () {
    $this->post(route('comando-geral.cursos.store'), cursoValido(['valor' => '', 'acesso' => 'aberto']))
        ->assertSessionHasErrors('acesso');
    expect(session('errors')->first('acesso'))->toContain('CURSO_GRATUITO_ABERTO');

    $this->post(route('comando-geral.cursos.store'), cursoValido(['valor' => '', 'acesso' => 'restrito']))
        ->assertSessionHasNoErrors();
    expect(Curso::first()->tipo())->toBe('Gratuito · só filiados');
});

test('presencial sem data e sem local é recusado; online sem data pode', function () {
    $this->post(route('comando-geral.cursos.store'), cursoValido(['data_inicio' => '', 'data_fim' => '', 'local' => '']))
        ->assertSessionHasErrors(['data_inicio', 'local']);

    $this->post(route('comando-geral.cursos.store'), cursoValido(['modalidade' => 'online', 'data_inicio' => '', 'data_fim' => '', 'local' => '']))
        ->assertSessionHasNoErrors();
});

test('imagem de capa: gravada e entregue; de rascunho, escondida do público', function () {
    Storage::fake('public');

    $this->post(route('comando-geral.cursos.store'), cursoValido([
        'situacao' => 'rascunho',
        'imagem' => UploadedFile::fake()->image('capa.png', 800, 400),
    ]));
    $curso = Curso::first();
    expect($curso->imagem)->not->toBeNull();

    // Visitante não vê a capa de rascunho.
    auth()->logout();
    $this->get(route('cursos.imagem', $curso))->assertNotFound();

    // Publicado: a capa aparece para todos.
    $curso->forceFill(['situacao' => SituacaoCurso::Publicado])->save();
    $this->get(route('cursos.imagem', $curso))->assertOk();
});

test('filtros da lista: busca, situação e período', function () {
    $this->post(route('comando-geral.cursos.store'), cursoValido(['titulo' => 'Combate', 'data_inicio' => '2026-11-10', 'data_fim' => '']));
    $this->post(route('comando-geral.cursos.store'), cursoValido(['titulo' => 'Bastão Tático', 'data_inicio' => '2027-02-01', 'data_fim' => '']));
    // Publica o "Combate" pelo fluxo (chancela), como o IBTO faria.
    app(FluxoCurso::class)->aplicar(Curso::where('titulo', 'Combate')->first(), $this->admin, 'chancelar');

    $this->get(route('comando-geral.cursos.index', ['busca' => 'bastão']))
        ->assertInertia(fn ($tela) => $tela->has('cursos', 1)->where('cursos.0.titulo', 'Bastão Tático'));
    $this->get(route('comando-geral.cursos.index', ['situacao' => 'publicado']))
        ->assertInertia(fn ($tela) => $tela->has('cursos', 1)->where('cursos.0.titulo', 'Combate'));
    $this->get(route('comando-geral.cursos.index', ['de' => '2026-11-01', 'ate' => '2026-11-30']))
        ->assertInertia(fn ($tela) => $tela->has('cursos', 1));
});

test('trilha guarda os cursos na ordem escolhida', function () {
    $this->post(route('comando-geral.cursos.store'), cursoValido(['titulo' => 'A']));
    $this->post(route('comando-geral.cursos.store'), cursoValido(['titulo' => 'B']));
    $this->post(route('comando-geral.cursos.store'), cursoValido(['titulo' => 'C']));
    [$a, $b, $c] = Curso::orderBy('id')->pluck('id')->all();

    // Ordem escolhida: C, A, B.
    $this->post(route('comando-geral.trilhas.store'), ['nome' => 'Formação', 'ativa' => '1', 'cursos' => [$c, $a, $b]])
        ->assertRedirect(route('comando-geral.trilhas.index'));

    expect(Trilha::first()->cursos()->pluck('cursos.id')->all())->toBe([$c, $a, $b]);
});

test('trilha sem cursos ou com curso repetido é recusada', function () {
    $this->post(route('comando-geral.cursos.store'), cursoValido());
    $id = Curso::value('id');

    $this->post(route('comando-geral.trilhas.store'), ['nome' => 'Vazia', 'ativa' => '1', 'cursos' => []])
        ->assertSessionHasErrors('cursos');
    $this->post(route('comando-geral.trilhas.store'), ['nome' => 'Repetida', 'ativa' => '1', 'cursos' => [$id, $id]])
        ->assertSessionHasErrors('cursos.0');
});

test('não existe rota para apagar curso ou trilha', function () {
    expect(Route::has('comando-geral.cursos.destroy'))->toBeFalse();
    expect(Route::has('comando-geral.trilhas.destroy'))->toBeFalse();
});

test('trilha aceita dois cursos na MESMA etapa (lado a lado) e recusa etapa fora do limite', function () {
    $this->post(route('comando-geral.cursos.store'), cursoValido(['titulo' => 'A']));
    $this->post(route('comando-geral.cursos.store'), cursoValido(['titulo' => 'B']));
    $this->post(route('comando-geral.cursos.store'), cursoValido(['titulo' => 'C']));
    [$a, $b, $c] = Curso::orderBy('id')->pluck('id')->all();

    // A na etapa 1; B e C juntos na etapa 2 (caminhos à escolha).
    $this->post(route('comando-geral.trilhas.store'), ['nome' => 'Formação', 'ativa' => '1', 'cursos' => [$a, $b, $c], 'etapas' => [$a => 1, $b => 2, $c => 2]])
        ->assertRedirect(route('comando-geral.trilhas.index'));
    expect(Trilha::first()->cursos()->pluck('curso_trilha.ordem', 'cursos.id')->map(fn ($etapa) => (int) $etapa)->all())
        ->toBe([$a => 1, $b => 2, $c => 2]);

    // Etapa 0 não existe (começa em 1).
    $this->post(route('comando-geral.trilhas.store'), ['nome' => 'Errada', 'ativa' => '1', 'cursos' => [$a], 'etapas' => [$a => 0]])
        ->assertSessionHasErrors("etapas.{$a}");
});
