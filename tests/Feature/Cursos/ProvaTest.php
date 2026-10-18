<?php

/*
 * Testes das PROVAS (#43): o instrutor monta a prova (pergunta com exatamente
 * uma certa); a tela do aluno NÃO recebe o gabarito; a correção é no servidor
 * (nota e aprovação); as tentativas acabam; pergunta sem resposta é recusada;
 * o certificado exige a prova aprovada; quem não é matriculado não faz a prova.
 * Dados INVENTADOS.
 */

use App\Actions\Cursos\Certificados;
use App\Actions\Cursos\Matriculas;
use App\Enums\SituacaoCurso;
use App\Models\Curso;
use App\Models\CursoProva;
use App\Models\CursoProvaTentativa;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    // Curso online gratuito, sem aulas: só a prova decide o "apto".
    $this->curso = new Curso(['titulo' => 'Inteligência Operacional', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 0]);
    $this->curso->forceFill(['situacao' => SituacaoCurso::Publicado, 'percentual_ibto' => 20])->save();
    $this->aluno = User::factory()->create();
    $this->matricula = app(Matriculas::class)->matricular($this->curso, $this->aluno);

    // Prova com 2 perguntas, nota mínima 50, 2 tentativas.
    $this->actingAs($this->admin)->post(route('comando-geral.cursos.provas.store', $this->curso), [
        'titulo' => 'Prova final', 'instrucoes' => 'Leia com calma.', 'nota_minima' => 50, 'tentativas_max' => 2, 'ativa' => true,
        'questoes' => [
            ['enunciado' => 'Distância segura de reação?', 'alternativas' => [['texto' => '7 metros', 'correta' => true], ['texto' => '1 metro', 'correta' => false]]],
            ['enunciado' => 'Primeira ação diante da ameaça?', 'alternativas' => [['texto' => 'Criar distância', 'correta' => true], ['texto' => 'Discutir', 'correta' => false]]],
        ],
    ])->assertSessionHasNoErrors();
    $this->prova = CursoProva::with('questoes.alternativas')->sole();
    // As respostas certas e erradas de cada pergunta (para montar os envios).
    $this->certas = $this->prova->questoes->mapWithKeys(fn ($q) => [$q->id => $q->alternativas->firstWhere('correta', true)->id])->all();
    $this->erradas = $this->prova->questoes->mapWithKeys(fn ($q) => [$q->id => $q->alternativas->firstWhere('correta', false)->id])->all();
});

test('pergunta sem exatamente uma alternativa certa é recusada', function () {
    $this->actingAs($this->admin)->post(route('comando-geral.cursos.provas.store', $this->curso), [
        'titulo' => 'Ruim', 'nota_minima' => 70, 'tentativas_max' => 1, 'ativa' => true,
        'questoes' => [['enunciado' => 'Duas certas?', 'alternativas' => [['texto' => 'A', 'correta' => true], ['texto' => 'B', 'correta' => true]]]],
    ])->assertSessionHasErrors('questoes');
    expect(CursoProva::count())->toBe(1);
});

test('a tela do aluno não recebe o gabarito', function () {
    $resposta = $this->actingAs($this->aluno)->get(route('cursos.prova', [$this->curso, $this->prova]));

    $resposta->assertOk()->assertInertia(fn (Assert $tela) => $tela
        ->component('cursos/provas/fazer')
        ->has('prova.questoes', 2)
        ->missing('prova.questoes.0.alternativas.0.correta'));
    expect($resposta->getContent())->not->toContain('"correta"');
});

test('a correção é no servidor: metade certa aprova com mínima 50; tentativas acabam', function () {
    $metade = [array_key_first($this->certas) => reset($this->certas), array_key_last($this->erradas) => end($this->erradas)];
    $this->actingAs($this->aluno)->post(route('cursos.prova.responder', [$this->curso, $this->prova]), ['respostas' => $this->erradas])->assertSessionHasNoErrors();
    $this->actingAs($this->aluno)->post(route('cursos.prova.responder', [$this->curso, $this->prova]), ['respostas' => $metade])->assertSessionHasNoErrors();

    $notas = CursoProvaTentativa::orderBy('id')->pluck('nota')->all();
    expect($notas)->toBe([0, 50])->and(CursoProvaTentativa::latest('id')->first()->aprovada)->toBeTrue();

    // A terceira tentativa passa do limite (2): recusada.
    $this->actingAs($this->aluno)->post(route('cursos.prova.responder', [$this->curso, $this->prova]), ['respostas' => $this->certas])->assertSessionHasErrors('respostas');
    expect(CursoProvaTentativa::count())->toBe(2);
});

test('pergunta sem resposta é recusada e não gasta tentativa', function () {
    $this->actingAs($this->aluno)->post(route('cursos.prova.responder', [$this->curso, $this->prova]), ['respostas' => [array_key_first($this->certas) => reset($this->certas)]])
        ->assertSessionHasErrors('respostas');
    expect(CursoProvaTentativa::count())->toBe(0);
});

test('o certificado exige a prova aprovada; aprovado no curso concluído, o certificado sai na hora', function () {
    $certificados = app(Certificados::class);
    expect($certificados->apto($this->matricula->fresh())['apto'])->toBeFalse()
        ->and($certificados->apto($this->matricula->fresh())['motivo'])->toContain('Prova final');

    $this->actingAs($this->aluno)->post(route('cursos.prova.responder', [$this->curso, $this->prova]), ['respostas' => $this->certas])
        ->assertSessionHas('sucesso', fn (string $mensagem) => str_contains($mensagem, 'certificado está pronto'));
    expect($this->matricula->fresh()->certificado)->not->toBeNull();
});

test('quem não é matriculado não faz a prova; o instrutor vê as notas sem nome', function () {
    $this->actingAs(User::factory()->create())->get(route('cursos.prova', [$this->curso, $this->prova]))->assertForbidden();

    $this->actingAs($this->aluno)->post(route('cursos.prova.responder', [$this->curso, $this->prova]), ['respostas' => $this->certas]);
    $this->actingAs($this->admin)->get(route('comando-geral.cursos.provas.index', $this->curso))
        ->assertInertia(fn (Assert $tela) => $tela
            ->where('provas.0.fizeram', 1)
            ->where('provas.0.aprovados', 1)
            ->where('provas.0.media', 100)
            ->where('provas.0.alunos.0.quem', "Matrícula nº {$this->matricula->id}"));
});
