<?php

/*
 * Testes da CONTA COMPLETA DO PILOTO (17/10/2026): a conta ganha os papéis,
 * a ficha de filiado SEM dado pessoal inventado e dados em todos os módulos
 * (aluno, instrutor, chefe de equipe e fotos); rodar de novo não duplica;
 * conta inexistente não é criada. Dados INVENTADOS.
 */

use App\Enums\SituacaoMatricula;
use App\Models\CursoCertificado;
use App\Models\CursoMatricula;
use App\Models\FotoAlbumVenda;
use App\Models\PagamentoConfig;
use App\Models\PedidoFoto;
use App\Models\User;
use App\Support\FotoComMarcaDagua;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake(FotoComMarcaDagua::DISCO);
    PagamentoConfig::atual()->update(['pix_chave' => 'financeiro@ibto.test', 'favorecido' => 'IBTO Teste']);
    $this->conta = User::factory()->create(['email' => 'conta.completa@ibto.test', 'name' => 'Fulana Completa']);
});

test('a conta ganha os papéis, a ficha sem dado pessoal e dados em todos os módulos, sem duplicar', function () {
    $this->artisan('plataforma:conta-completa', ['email' => 'conta.completa@ibto.test'])->assertSuccessful();
    $this->artisan('plataforma:conta-completa', ['email' => 'conta.completa@ibto.test'])->assertSuccessful();

    $conta = $this->conta->fresh();
    expect($conta->papeis()->pluck('slug')->sort()->values()->all())->toBe(['admin_equipe', 'aluno', 'filiado', 'fotografo', 'instrutor'])
        // Ficha de filiado sem CPF, telefone ou nascimento inventados.
        ->and($conta->filiado->cpf)->toBeNull()->and($conta->filiado->telefone)->toBeNull()->and($conta->filiado->nascimento)->toBeNull();

    // Como aluno: 4 matrículas (paga, aguardando, gratuita, concluída) e 1 certificado.
    $minhas = CursoMatricula::where('user_id', $conta->id)->get();
    expect($minhas)->toHaveCount(4)
        ->and($minhas->where('situacao', SituacaoMatricula::AguardandoPagamento))->toHaveCount(1)
        ->and(CursoCertificado::whereIn('matricula_id', $minhas->pluck('id'))->count())->toBe(1);

    // Como instrutor: o curso dela tem 2 alunos pagos e 1 aguardando o Pix.
    $this->actingAs($conta)->get(route('meu-painel'))->assertOk()->assertInertia(fn ($tela) => $tela
        ->where('cursos.0.confirmados', 2)
        ->where('cursos.0.aguardando', 1));

    // Chefe de equipe: uma solicitação esperando.
    expect(User::where('situacao_cadastro', 'em_analise')->where('equipe_solicitada_id', $conta->filiado->equipe_id)->count())->toBe(1);

    // Fotos: ela comprou (1 pago + 1 aguardando) e vendeu (1 pago).
    expect(PedidoFoto::where('user_id', $conta->id)->pluck('situacao')->sort()->values()->all())->toBe(['aguardando_pagamento', 'pago'])
        ->and(PedidoFoto::whereHas('album', fn ($a) => $a->where('fotografo_id', $conta->id))->where('situacao', 'pago')->count())->toBe(1);
});

test('a conta completa consegue comprar mais fotos no álbum em que é compradora', function () {
    $this->artisan('plataforma:conta-completa', ['email' => 'conta.completa@ibto.test'])->assertSuccessful();
    $conta = $this->conta->fresh();
    $album = FotoAlbumVenda::where('titulo', 'like', '%(você compra)')->sole();
    $livre = $album->fotos()->orderByDesc('id')->value('id');

    $this->actingAs($conta)->post(route('fotos.comprar', $album), ['fotos' => [$livre]])
        ->assertSessionHasNoErrors();
});

test('conta que não existe: não cria e devolve erro', function () {
    $this->artisan('plataforma:conta-completa', ['email' => 'ninguem@ibto.test'])->assertFailed();
    expect(User::where('email', 'ninguem@ibto.test')->exists())->toBeFalse();
});
