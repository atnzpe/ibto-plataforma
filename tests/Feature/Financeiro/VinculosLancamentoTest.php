<?php

/*
 * Testes dos VÍNCULOS DO LIVRO-CAIXA (18/10/2026): cada lançamento automático
 * guarda a quem se refere — na receita, quem pagou; no repasse, quem recebe —
 * e o curso ou o programa. Estorno herda os vínculos. Dados INVENTADOS.
 */

use App\Actions\AulasRegulares\Assinaturas;
use App\Actions\Cursos\Matriculas;
use App\Models\Curso;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\Programa;
use App\Models\User;
use App\Services\LivroCaixa;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->pix = (int) FinFormaPagamento::where('nome', 'PIX')->value('id');
    $this->instrutor = User::factory()->create();
    $this->aluno = User::factory()->create();
});

test('inscrição: a receita é do aluno e o repasse é do instrutor, ambos no curso', function () {
    $curso = new Curso(['titulo' => 'Combate com Faca', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 25000, 'data_inicio' => today()->toDateString(), 'local' => 'Recife']);
    $curso->forceFill(['situacao' => 'publicado', 'instrutor_id' => $this->instrutor->id, 'percentual_ibto' => 20])->save();
    $matriculas = app(Matriculas::class);
    $matriculas->confirmarPagamento($matriculas->matricular($curso, $this->aluno), today()->toDateString(), $this->pix);

    $receita = FinLancamento::where('origem', 'matricula')->sole();
    $repasse = FinLancamento::where('origem', 'repasse')->sole();
    expect($receita->pessoa_id)->toBe($this->aluno->id)->and($receita->curso_id)->toBe($curso->id)
        ->and($repasse->pessoa_id)->toBe($this->instrutor->id)->and($repasse->curso_id)->toBe($curso->id);

    // O estorno herda os vínculos do original.
    $estorno = app(LivroCaixa::class)->estornar($receita, 'Teste de estorno', today()->toDateString());
    expect($estorno->pessoa_id)->toBe($this->aluno->id)->and($estorno->curso_id)->toBe($curso->id);
});

test('mensalidade: a receita é do aluno e o repasse é do instrutor, ambos no programa', function () {
    $programa = Programa::create(['titulo' => 'Krav Maga', 'modalidade' => 'presencial', 'valor_mensal_centavos' => 10000, 'percentual_ibto' => 20, 'dia_vencimento' => 10, 'ativo' => true, 'so_filiados' => false, 'instrutor_id' => $this->instrutor->id]);
    $acoes = app(Assinaturas::class);
    $assinatura = $acoes->assinar($programa, $this->aluno);
    $acoes->confirmarPagamento($assinatura->pagamentos()->sole(), today()->toDateString(), $this->pix);

    $receita = FinLancamento::where('origem', 'assinatura')->sole();
    $repasse = FinLancamento::where('origem', 'repasse_assinatura')->sole();
    expect($receita->pessoa_id)->toBe($this->aluno->id)->and($receita->programa_id)->toBe($programa->id)
        ->and($repasse->pessoa_id)->toBe($this->instrutor->id)->and($repasse->programa_id)->toBe($programa->id);
});
