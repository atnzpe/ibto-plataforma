<?php

/*
 * Testes das REGRAS DAS ASSINATURAS (#80): assinar cria a 1ª mensalidade;
 * "só filiados", vagas e assinatura repetida são recusados; confirmar o Pix
 * lança a receita e o repasse (20/80) e deixa paga até o vencimento do mês
 * seguinte; acesso: em dia, carência de 5 dias e travado; gerar a mensalidade
 * 3 dias antes avisa por e-mail uma vez; cancelar mantém o histórico.
 * Dados INVENTADOS.
 */

use App\Actions\AulasRegulares\Assinaturas;
use App\Exceptions\RegraCurso;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\Programa;
use App\Models\User;
use App\Notifications\AvisoMensalidade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Carbon::setTestNow('2026-10-17 10:00:00');
    $this->programa = Programa::create(['titulo' => 'Krav Maga online', 'modalidade' => 'online', 'valor_mensal_centavos' => 12000, 'percentual_ibto' => 20, 'vagas' => 2, 'so_filiados' => false, 'dia_vencimento' => 10, 'ativo' => true, 'instrutor_nome' => 'Instrutor Teste']);
    $this->acoes = app(Assinaturas::class);
    $this->aluno = User::factory()->create();
    $this->pix = (int) FinFormaPagamento::where('nome', 'PIX')->value('id');
});

afterEach(fn () => Carbon::setTestNow());

test('assinar cria a assinatura aguardando e a 1ª mensalidade (mês atual, vence hoje)', function () {
    $assinatura = $this->acoes->assinar($this->programa, $this->aluno);

    $mensalidade = $assinatura->pagamentos()->sole();
    expect($assinatura->situacao)->toBe('aguardando_pagamento')
        ->and($assinatura->acesso()['acesso'])->toBe('aguardando')
        ->and($mensalidade->referencia)->toBe('2026-10')
        ->and($mensalidade->valor_centavos)->toBe(12000)
        ->and($mensalidade->vence_em->toDateString())->toBe('2026-10-17');
});

test('recusa: só filiados, sem vagas e assinatura repetida', function () {
    $this->programa->update(['so_filiados' => true]);
    expect(fn () => $this->acoes->assinar($this->programa, $this->aluno))
        ->toThrow(fn (RegraCurso $e) => expect($e->codigo)->toBe('PROGRAMA_SO_FILIADOS'));

    $this->programa->update(['so_filiados' => false]);
    $this->acoes->assinar($this->programa, $this->aluno);
    expect(fn () => $this->acoes->assinar($this->programa, $this->aluno))
        ->toThrow(fn (RegraCurso $e) => expect($e->codigo)->toBe('JA_ASSINA'));

    $this->acoes->assinar($this->programa, User::factory()->create());
    expect(fn () => $this->acoes->assinar($this->programa, User::factory()->create()))
        ->toThrow(fn (RegraCurso $e) => expect($e->codigo)->toBe('PROGRAMA_SEM_VAGAS'));
});

test('confirmar o Pix lança receita e repasse 20/80 e deixa paga até 10/11', function () {
    $assinatura = $this->acoes->assinar($this->programa, $this->aluno);

    $this->acoes->confirmarPagamento($assinatura->pagamentos()->sole(), '2026-10-17', $this->pix);

    $assinatura->refresh();
    expect($assinatura->situacao)->toBe('ativa')
        ->and($assinatura->pago_ate->toDateString())->toBe('2026-11-10')
        ->and($assinatura->acesso()['acesso'])->toBe('liberado')
        ->and(FinLancamento::where('origem', 'assinatura')->sole()->valor_centavos)->toBe(12000)
        ->and(FinLancamento::where('origem', 'repasse_assinatura')->sole()->valor_centavos)->toBe(9600);

    // Dois cliques: não lança de novo.
    expect(fn () => $this->acoes->confirmarPagamento($assinatura->pagamentos()->sole(), '2026-10-17', $this->pix))
        ->toThrow(fn (RegraCurso $e) => expect($e->codigo)->toBe('MENSALIDADE_NAO_AGUARDANDO'));
});

test('acesso: em dia, carência de 5 dias e travado', function () {
    $assinatura = $this->acoes->assinar($this->programa, $this->aluno);
    $this->acoes->confirmarPagamento($assinatura->pagamentos()->sole(), '2026-10-17', $this->pix);
    $assinatura->refresh();

    Carbon::setTestNow('2026-11-10');
    expect($assinatura->acesso()['acesso'])->toBe('liberado');
    Carbon::setTestNow('2026-11-15');
    expect($assinatura->acesso()['acesso'])->toBe('carencia')->and($assinatura->liberada())->toBeTrue();
    Carbon::setTestNow('2026-11-16');
    expect($assinatura->acesso()['acesso'])->toBe('travado')->and($assinatura->liberada())->toBeFalse();
});

test('3 dias antes do vencimento gera a mensalidade e avisa por e-mail, uma vez só', function () {
    $assinatura = $this->acoes->assinar($this->programa, $this->aluno);
    $this->acoes->confirmarPagamento($assinatura->pagamentos()->sole(), '2026-10-17', $this->pix);

    Carbon::setTestNow('2026-11-06');
    expect($this->acoes->gerarMensalidades())->toBe(0);
    Carbon::setTestNow('2026-11-07');
    expect($this->acoes->gerarMensalidades())->toBe(1)
        ->and($this->acoes->gerarMensalidades())->toBe(0);

    $nova = $assinatura->pagamentos()->where('referencia', '2026-11')->sole();
    expect($nova->vence_em->toDateString())->toBe('2026-11-10');
    Notification::assertSentToTimes($this->aluno, AvisoMensalidade::class, 1);

    // Paga a de novembro: fica paga até 10/12.
    $this->acoes->confirmarPagamento($nova, '2026-11-08', $this->pix);
    expect($assinatura->refresh()->pago_ate->toDateString())->toBe('2026-12-10');
});

test('cancelar mantém o histórico e cancela a mensalidade em aberto', function () {
    $assinatura = $this->acoes->assinar($this->programa, $this->aluno);

    $this->acoes->cancelar($assinatura);

    expect($assinatura->refresh()->situacao)->toBe('cancelada')
        ->and($assinatura->pagamentos()->sole()->situacao)->toBe('cancelado')
        ->and($assinatura->acesso()['acesso'])->toBe('cancelada');
});
