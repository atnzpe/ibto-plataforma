<?php

/*
 * Testes das REGRAS DA MATRÍCULA (#41): gratuito confirma na hora; pago fica
 * aguardando o PIX; "só filiados" exige filiado ativo; não matricula duas
 * vezes; a ÚLTIMA VAGA vai para uma pessoa só (com a trava no MySQL);
 * confirmar lança a receita no livro-caixa, guarda a referência, manda o
 * e-mail e não confirma duas vezes; cancelar não apaga e libera a vaga;
 * a matrícula confirmada abre a sala de aula. Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Actions\Filiados\CriarFiliado;
use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\User;
use App\Notifications\MatriculaConfirmada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Um curso publicado inventado.
 *
 * @param  array<string, mixed>  $troca  Ex.: ['vagas' => 1]
 */
function cursoParaMatricula(array $troca = []): Curso
{
    $curso = Curso::create([
        'titulo' => 'Tiro Defensivo', 'modalidade' => 'online', 'acesso' => 'aberto',
        'valor_centavos' => 25000, 'vagas' => 10, ...$troca,
    ]);
    // A situação não entra pelo create (só pelo fluxo da chancela): forceFill.
    $curso->forceFill(['situacao' => 'publicado'])->save();

    return $curso;
}

/**
 * Atalho: matricula pela classe de regras.
 */
function matricular(Curso $curso, User $usuario): CursoMatricula
{
    return app(Matriculas::class)->matricular($curso, $usuario);
}

/**
 * Atalho: o código do erro de regra que a ação dispara (ou null se não disparou).
 *
 * @param  callable(): mixed  $acao
 */
function codigoDoErro(callable $acao): ?string
{
    try {
        $acao();
    } catch (RegraCurso $erro) {
        return $erro->codigo;
    }

    return null;
}

beforeEach(function () {
    Notification::fake();
    $this->pix = (int) FinFormaPagamento::where('nome', 'PIX')->value('id');
});

test('gratuito: confirmada na hora; pago: aguardando pagamento, com o preço do dia', function () {
    $aluno = User::factory()->create();

    expect(matricular(cursoParaMatricula(['valor_centavos' => 0]), $aluno)->situacao)->toBe(SituacaoMatricula::Confirmada);

    $pago = matricular(cursoParaMatricula(), $aluno);
    expect($pago->situacao)->toBe(SituacaoMatricula::AguardandoPagamento);
    expect($pago->valor_centavos)->toBe(25000);
});

test('curso "só filiados": quem não é filiado ativo não entra', function () {
    $curso = cursoParaMatricula(['acesso' => 'restrito']);

    expect(codigoDoErro(fn () => matricular($curso, User::factory()->create())))->toBe('MATRICULA_SO_FILIADOS');

    $filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Filiado Teste', 'email' => 'filiado@ibto.test', 'cpf' => '529.982.247-25',
        'nascimento' => '1990-05-20', 'municipio' => 'Recife', 'uf' => 'PE',
    ]);
    expect(matricular($curso, $filiado->usuario)->situacao)->toBe(SituacaoMatricula::AguardandoPagamento);
});

test('não matricula duas vezes; curso não publicado ou com data passada não aceita', function () {
    $aluno = User::factory()->create();
    $curso = cursoParaMatricula();
    matricular($curso, $aluno);

    expect(codigoDoErro(fn () => matricular($curso, $aluno)))->toBe('MATRICULA_JA_EXISTE');

    $rascunho = cursoParaMatricula();
    $rascunho->forceFill(['situacao' => 'rascunho'])->save();
    expect(codigoDoErro(fn () => matricular($rascunho, $aluno)))->toBe('CURSO_INDISPONIVEL');

    $passado = cursoParaMatricula(['modalidade' => 'presencial', 'data_inicio' => today()->subDay()->toDateString(), 'local' => 'Recife']);
    expect(codigoDoErro(fn () => matricular($passado, $aluno)))->toBe('MATRICULAS_ENCERRADAS');
});

test('a última vaga vai para UMA pessoa; cancelar libera a vaga e não apaga', function () {
    $curso = cursoParaMatricula(['vagas' => 1]);
    [$primeira, $segunda] = User::factory()->count(2)->create();

    $matricula = matricular($curso, $primeira);
    expect(codigoDoErro(fn () => matricular($curso, $segunda)))->toBe('VAGAS_ESGOTADAS');

    app(Matriculas::class)->cancelar($matricula, 'Desistência');
    // A linha continua no banco, cancelada, com o motivo.
    expect($matricula->fresh()->situacao)->toBe(SituacaoMatricula::Cancelada);
    expect($matricula->fresh()->motivo_cancelamento)->toBe('Desistência');
    // A vaga voltou: a segunda pessoa entra.
    expect(matricular($curso, $segunda)->situacao)->toBe(SituacaoMatricula::AguardandoPagamento);
});

test('a contagem das vagas TRANCA o curso (lockForUpdate) no MySQL', function () {
    // No SQLite (testes locais) não existe trava de linha; a CI roda no MySQL 5.7.
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('A trava de linha só existe no MySQL (conferida na CI).');
    }

    $comandos = [];
    DB::listen(function ($consulta) use (&$comandos) {
        $comandos[] = $consulta->sql;
    });

    matricular(cursoParaMatricula(), User::factory()->create());

    // O curso foi lido com "for update" (a trava), antes de contar as vagas.
    expect(collect($comandos)->contains(fn (string $sql) => str_contains($sql, 'from `cursos`') && str_contains($sql, 'for update')))->toBeTrue();
});

test('confirmar o pagamento: receita paga no livro-caixa, referência na matrícula, e-mail; não confirma duas vezes', function () {
    $aluno = User::factory()->create();
    $matricula = matricular(cursoParaMatricula(), $aluno);

    $confirmada = app(Matriculas::class)->confirmarPagamento($matricula, '2026-10-12', $this->pix);

    expect($confirmada->situacao)->toBe(SituacaoMatricula::Confirmada);
    $receita = FinLancamento::findOrFail($confirmada->lancamento_id);
    expect($receita->valor_centavos)->toBe(25000);
    expect($receita->situacao)->toBe('pago');
    // LGPD: o nome da pessoa não vai para a descrição do financeiro.
    expect($receita->descricao)->not->toContain($aluno->name);
    Notification::assertSentTo($aluno, MatriculaConfirmada::class);

    expect(codigoDoErro(fn () => app(Matriculas::class)->confirmarPagamento($matricula, '2026-10-12', $this->pix)))->toBe('MATRICULA_NAO_AGUARDANDO');
    // Só uma receita foi lançada.
    expect(FinLancamento::where('origem', 'matricula')->count())->toBe(1);
});

test('matrícula confirmada abre a sala de aula; aguardando pagamento ainda não', function () {
    $aluno = User::factory()->create();
    $curso = cursoParaMatricula();
    $matricula = matricular($curso, $aluno);

    expect($aluno->can('curso.conteudo', $curso))->toBeFalse();

    app(Matriculas::class)->confirmarPagamento($matricula, '2026-10-12', $this->pix);
    expect($aluno->fresh()->can('curso.conteudo', $curso))->toBeTrue();
});
