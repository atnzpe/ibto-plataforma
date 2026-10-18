<?php

/*
 * Testes das TELAS DAS AULAS REGULARES (#80): o Comando Geral cria o programa
 * (valor em reais vira centavos) e a turma (link só de Meet/Zoom/Teams/YouTube);
 * o aluno vê os programas, assina e vê o Pix em "Minhas aulas"; o link da sala só
 * aparece com o acesso liberado; o Comando Geral confirma a mensalidade; quem não
 * tem permissão recebe 403; ninguém cancela a assinatura de outra pessoa.
 * Dados INVENTADOS.
 */

use App\Models\Assinatura;
use App\Models\PagamentoConfig;
use App\Models\Papel;
use App\Models\Programa;
use App\Models\ProgramaTurma;
use App\Models\User;

beforeEach(function () {
    PagamentoConfig::atual()->update(['pix_chave' => 'financeiro@ibto.test', 'favorecido' => 'IBTO Teste']);
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->aluno = User::factory()->create();
});

test('o Comando Geral cria o programa (reais → centavos) e uma turma online', function () {
    $this->actingAs($this->admin)->post(route('comando-geral.programas.store'), [
        'titulo' => 'Kapap online', 'modalidade' => 'online', 'valor' => '150,00', 'percentual_ibto' => 20, 'dia_vencimento' => 5, 'ativo' => true, 'so_filiados' => false,
    ])->assertRedirect();
    $programa = Programa::sole();
    expect($programa->valor_mensal_centavos)->toBe(15000);

    $this->actingAs($this->admin)->post(route('comando-geral.programas.turmas.store', $programa), [
        'nome' => 'Noite', 'dias_semana' => 'ter,qui', 'hora_inicio' => '19:00', 'hora_fim' => '20:30', 'link_sala' => 'https://site-falso.com/sala',
    ])->assertSessionHasErrors('link_sala');
    $this->actingAs($this->admin)->post(route('comando-geral.programas.turmas.store', $programa), [
        'nome' => 'Noite', 'dias_semana' => 'ter,qui', 'hora_inicio' => '19:00', 'hora_fim' => '20:30', 'link_sala' => 'https://meet.google.com/abc-defg-hij',
    ])->assertSessionHasNoErrors();
    expect(ProgramaTurma::sole()->horarioPorExtenso())->toBe('Terça e Quinta, 19:00–20:30');
});

test('o aluno assina, vê o Pix em Minhas aulas e o link só depois de pago', function () {
    $programa = Programa::create(['titulo' => 'Krav Maga online', 'modalidade' => 'online', 'valor_mensal_centavos' => 12000, 'percentual_ibto' => 20, 'dia_vencimento' => 10, 'ativo' => true, 'so_filiados' => false]);
    $turma = new ProgramaTurma(['nome' => 'Noite', 'dias_semana' => 'ter', 'hora_inicio' => '19:00', 'link_sala' => 'https://meet.google.com/abc-defg-hij', 'ativa' => true]);
    $turma->forceFill(['programa_id' => $programa->id])->save();

    $this->actingAs($this->aluno)->get(route('aulas.index'))->assertOk()->assertInertia(fn ($tela) => $tela->has('programas', 1));
    $this->actingAs($this->aluno)->post(route('aulas.assinar', $programa), ['turma_id' => $turma->id])->assertRedirect(route('aulas.minhas'));

    $this->actingAs($this->aluno)->get(route('aulas.minhas'))->assertInertia(fn ($tela) => $tela
        ->where('assinaturas.0.acesso.acesso', 'aguardando')
        ->where('assinaturas.0.link_sala', null)
        ->where('assinaturas.0.em_aberto.pix.txid', fn ($txid) => str_starts_with($txid, 'IBTOAUL')));

    // O Comando Geral confirma: o link da sala aparece.
    $pagamento = Assinatura::sole()->pagamentos()->sole();
    $this->actingAs($this->admin)->post(route('comando-geral.mensalidades.confirmar', $pagamento), ['pago_em' => today()->toDateString()])->assertSessionHasNoErrors();
    $this->actingAs($this->aluno)->get(route('aulas.minhas'))->assertInertia(fn ($tela) => $tela
        ->where('assinaturas.0.acesso.acesso', 'liberado')
        ->where('assinaturas.0.link_sala', 'https://meet.google.com/abc-defg-hij'));
});

test('sem permissão: 403 no Comando Geral; ninguém cancela a assinatura de outra pessoa', function () {
    $this->actingAs($this->aluno)->get(route('comando-geral.programas.index'))->assertForbidden();
    $this->actingAs($this->aluno)->get(route('comando-geral.mensalidades.index'))->assertForbidden();

    $programa = Programa::create(['titulo' => 'Defesa pessoal', 'modalidade' => 'presencial', 'valor_mensal_centavos' => 9000, 'percentual_ibto' => 20, 'dia_vencimento' => 10, 'ativo' => true, 'so_filiados' => false]);
    $this->actingAs($this->aluno)->post(route('aulas.assinar', $programa));
    $assinatura = Assinatura::sole();

    $this->actingAs(User::factory()->create())->post(route('aulas.cancelar', $assinatura))->assertNotFound();
    expect($assinatura->fresh()->situacao)->toBe('aguardando_pagamento');
});
