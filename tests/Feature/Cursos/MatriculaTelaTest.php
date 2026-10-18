<?php

/*
 * Testes da MATRÍCULA pelo lado do aluno (#41, bloco C5b): garantir a vaga
 * pela página do curso, o quadro com o PIX (só para quem aguarda pagamento),
 * o comprovante no disco PRIVADO (só a dona e quem confirma veem), desistir
 * antes de pagar e as mensagens com código quando a regra não deixa.
 * Dados INVENTADOS.
 */

use App\Enums\SituacaoMatricula;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\PagamentoConfig;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    // Dados para pagamento do IBTO (tela do Comando Geral).
    PagamentoConfig::atual()->update(['pix_chave' => 'financeiro@ibto.test', 'favorecido' => 'IBTO Teste']);

    $this->curso = Curso::create([
        'titulo' => 'Tiro Defensivo', 'modalidade' => 'online', 'acesso' => 'aberto',
        'valor_centavos' => 25000, 'vagas' => 2,
    ]);
    // A situação não entra pelo create (só pelo fluxo da chancela): forceFill.
    $this->curso->forceFill(['situacao' => 'publicado'])->save();
    $this->aluno = User::factory()->create();
});

test('garantir a vaga: aguardando pagamento, com o PIX no quadro e as vagas restantes', function () {
    $this->actingAs($this->aluno)
        ->post(route('matriculas.store', $this->curso))
        ->assertRedirect(route('catalogo.curso', $this->curso->id))
        ->assertSessionHas('sucesso');

    $this->actingAs($this->aluno)->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela
            ->where('vagas_restantes', 1)
            ->where('matricula.situacao', 'aguardando_pagamento')
            ->where('matricula.pagamento.pix_chave', 'financeiro@ibto.test'));
});

test('visitante não vê matrícula nem PIX; quem não está logado não matricula', function () {
    $this->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela->where('matricula', null));

    $this->post(route('matriculas.store', $this->curso))->assertRedirect(route('login'));
});

test('regra que não deixa volta com a mensagem e o código', function () {
    $this->actingAs($this->aluno)->post(route('matriculas.store', $this->curso));

    $this->actingAs($this->aluno)
        ->post(route('matriculas.store', $this->curso))
        ->assertSessionHasErrors(['matricula' => 'Você já está matriculado neste curso. (código MATRICULA_JA_EXISTE)']);
});

test('comprovante: vai para o disco privado; só a dona e quem confirma pagamentos veem', function () {
    $this->actingAs($this->aluno)->post(route('matriculas.store', $this->curso));
    $matricula = CursoMatricula::firstOrFail();

    $this->actingAs($this->aluno)
        ->post(route('matriculas.comprovante', $matricula), ['comprovante' => UploadedFile::fake()->create('pix.pdf', 100, 'application/pdf')])
        ->assertRedirect(route('catalogo.curso', $this->curso->id));

    $caminho = $matricula->fresh()->comprovante;
    Storage::disk('local')->assertExists($caminho);

    // A dona vê; outra pessoa, não (403); quem confirma pagamentos, sim.
    $this->actingAs($this->aluno)->get(route('matriculas.comprovante.ver', $matricula))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('matriculas.comprovante.ver', $matricula))->assertForbidden();
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin)->get(route('matriculas.comprovante.ver', $matricula))->assertOk();

    // Outra pessoa não envia comprovante na matrícula alheia.
    $this->actingAs(User::factory()->create())
        ->post(route('matriculas.comprovante', $matricula), ['comprovante' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertForbidden();
});

test('comprovante que não é PDF nem foto é recusado', function () {
    $this->actingAs($this->aluno)->post(route('matriculas.store', $this->curso));

    $this->actingAs($this->aluno)
        ->post(route('matriculas.comprovante', CursoMatricula::firstOrFail()), ['comprovante' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors('comprovante');
});

test('desistir antes de pagar: cancela sem apagar e libera a vaga', function () {
    $this->actingAs($this->aluno)->post(route('matriculas.store', $this->curso));
    $matricula = CursoMatricula::firstOrFail();

    $this->actingAs($this->aluno)->post(route('matriculas.desistir', $matricula))
        ->assertRedirect(route('catalogo.curso', $this->curso->id));

    expect($matricula->fresh()->situacao)->toBe(SituacaoMatricula::Cancelada);
    $this->actingAs($this->aluno)->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela->where('vagas_restantes', 2)->where('matricula', null));
});

// Pix copia e cola com QR code (#83).
test('aguardando pagamento: Pix copia e cola com o valor exato, o identificador e o QR; sem dado do aluno', function () {
    $this->actingAs($this->aluno)->post(route('matriculas.store', $this->curso));
    $matricula = CursoMatricula::where('user_id', $this->aluno->id)->firstOrFail();

    $this->actingAs($this->aluno)->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela
            ->where('matricula.pix.txid', 'IBTOMAT'.$matricula->id)
            ->where('matricula.pix.copia_e_cola', fn ($codigo) => str_contains($codigo, 'financeiro@ibto.test')
                && str_contains($codigo, '5406250.00')
                && str_contains($codigo, 'IBTOMAT'.$matricula->id)
                // Nenhum dado pessoal do aluno dentro do código.
                && ! str_contains($codigo, $this->aluno->email)
                && ! str_contains($codigo, $this->aluno->name))
            ->where('matricula.pix.qr_svg', fn ($svg) => str_starts_with($svg, '<svg')));
});

test('sem chave Pix cadastrada, não há código (a tela orienta pelo suporte)', function () {
    PagamentoConfig::atual()->update(['pix_chave' => '']);
    $this->actingAs($this->aluno)->post(route('matriculas.store', $this->curso));

    $this->actingAs($this->aluno)->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela->where('matricula.pix', null));
});

test('comprovante enviado: a tela avisa "aguardando conferência" e o aluno não pode se inscrever de novo', function () {
    $this->actingAs($this->aluno)->post(route('matriculas.store', $this->curso));
    $matricula = CursoMatricula::firstOrFail();
    $this->actingAs($this->aluno)->post(route('matriculas.comprovante', $matricula), ['comprovante' => UploadedFile::fake()->create('pix.pdf', 100, 'application/pdf')]);

    expect($matricula->fresh()->comprovante_enviado_em)->not->toBeNull();
    $this->actingAs($this->aluno)->get(route('catalogo.curso', $this->curso->id))
        ->assertInertia(fn ($tela) => $tela->where('matricula.tem_comprovante', true)->whereNot('matricula.comprovante_enviado_em', null));
    $this->actingAs($this->aluno)->get(route('meus-cursos'))
        ->assertInertia(fn ($tela) => $tela->where('cursos.0.comprovante_enviado', true));

    // Tentar se inscrever de novo não cria outra matrícula (a vaga já é dele).
    $this->actingAs($this->aluno)->post(route('matriculas.store', $this->curso));
    expect(CursoMatricula::count())->toBe(1);
});

test('o Comando Geral recusa o comprovante: arquivo apagado, motivo gravado, e-mail ao aluno e vaga mantida', function () {
    Illuminate\Support\Facades\Notification::fake();
    $this->actingAs($this->aluno)->post(route('matriculas.store', $this->curso));
    $matricula = CursoMatricula::firstOrFail();
    $this->actingAs($this->aluno)->post(route('matriculas.comprovante', $matricula), ['comprovante' => UploadedFile::fake()->create('pix.pdf', 100, 'application/pdf')]);
    $caminho = $matricula->fresh()->comprovante;

    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    // Sem motivo: recusado.
    $this->actingAs($admin)->post(route('comando-geral.matriculas.recusar-comprovante', $matricula), ['motivo' => ''])->assertSessionHasErrors('motivo');
    $this->actingAs($admin)->post(route('comando-geral.matriculas.recusar-comprovante', $matricula), ['motivo' => 'Comprovante ilegível'])->assertSessionHasNoErrors();

    $matricula->refresh();
    expect($matricula->comprovante)->toBeNull()
        ->and($matricula->comprovante_recusa_motivo)->toBe('Comprovante ilegível')
        ->and($matricula->situacao)->toBe(SituacaoMatricula::AguardandoPagamento);
    Storage::disk('local')->assertMissing($caminho);
    Illuminate\Support\Facades\Notification::assertSentTo($this->aluno, App\Notifications\ComprovanteRecusado::class);

    // O aluno vê o motivo; ao mandar outro, a recusa some.
    $this->actingAs($this->aluno)->get(route('meus-cursos'))->assertInertia(fn ($tela) => $tela->where('cursos.0.comprovante_recusado', true));
    $this->actingAs($this->aluno)->post(route('matriculas.comprovante', $matricula), ['comprovante' => UploadedFile::fake()->create('pix2.pdf', 100, 'application/pdf')]);
    expect($matricula->fresh()->comprovante_recusa_motivo)->toBeNull();

    // Aluno comum não recusa comprovante (sem a permissão).
    $this->actingAs($this->aluno)->post(route('comando-geral.matriculas.recusar-comprovante', $matricula), ['motivo' => 'x'])->assertForbidden();
});
