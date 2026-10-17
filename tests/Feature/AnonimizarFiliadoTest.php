<?php

/*
 * Testes da ANONIMIZAÇÃO (LGPD, #47): apaga nome, e-mail, CPF, nascimento,
 * telefone e cidade; mantém o registro; desativa a conta; invalida o QR; pede
 * senha, registro e motivo; só quem cuida de TODOS os filiados. Dados INVENTADOS.
 */

use App\Actions\Filiados\CriarFiliado;
use App\Models\Auditoria;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Fulano Para Anonimizar', 'email' => 'anonimo@ibto.test', 'cpf' => '529.982.247-25',
        'nascimento' => '1990-01-01', 'telefone' => '81999990000', 'municipio' => 'Recife', 'uf' => 'PE',
        'equipe_id' => Equipe::factory()->create()->id,
    ]);
    $this->admin = User::factory()->create(['password' => 'senha-do-admin']);
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
});

/**
 * Envia o pedido de anonimização com os dados certos (ou trocados no $extra).
 *
 * @param  array<string, string>  $extra  Ex.: ['senha' => 'errada'].
 */
function pedirAnonimizacao($teste, User $quem, array $extra = [])
{
    return $teste->actingAs($quem)->post(route('cadastros.filiados.anonimizar', $teste->filiado), [
        'senha' => 'senha-do-admin', 'registro' => $teste->filiado->registro, 'motivo' => 'Pedido do titular por e-mail', ...$extra,
    ]);
}

test('anonimiza: some o que identifica, fica o registro, a conta é desativada e o QR muda', function () {
    $codigoAntigo = $this->filiado->codigo_validacao;
    $registro = $this->filiado->registro;

    pedirAnonimizacao($this, $this->admin)->assertRedirect(route('cadastros.filiados.index'));

    $filiado = Filiado::with('usuario')->find($this->filiado->id);
    expect($filiado->registro)->toBe($registro)
        ->and($filiado->cpf)->toBeNull()->and($filiado->cpf_hash)->toBeNull()
        ->and($filiado->nascimento)->toBeNull()->and($filiado->telefone)->toBeNull()
        ->and($filiado->municipio)->toBeNull()->and($filiado->status)->toBe('inativo')
        ->and($filiado->codigo_validacao)->not->toBe($codigoAntigo)
        ->and($filiado->usuario->name)->toBe("Filiado anonimizado ({$registro})")
        ->and($filiado->usuario->email)->toBeNull()
        ->and($filiado->usuario->desativada_em)->not->toBeNull();

    expect(Auditoria::where('acao', 'FILIADO_ANONIMIZADO')->sole()->detalhes['filiado_id'])->toBe($this->filiado->id);
});

test('senha errada, registro trocado ou motivo curto: recusa e não apaga nada', function () {
    pedirAnonimizacao($this, $this->admin, ['senha' => 'errada'])->assertSessionHasErrors('senha');
    pedirAnonimizacao($this, $this->admin, ['registro' => 'IBTO-9999'])->assertSessionHasErrors('registro');
    pedirAnonimizacao($this, $this->admin, ['motivo' => 'curto'])->assertSessionHasErrors('motivo');

    expect(Filiado::find($this->filiado->id)->cpf)->not->toBeNull();
});

test('quem não cuida de todos os filiados não anonimiza', function () {
    $qualquer = User::factory()->create(['password' => 'senha-do-admin']);

    pedirAnonimizacao($this, $qualquer)->assertForbidden();
    expect(Filiado::find($this->filiado->id)->cpf)->not->toBeNull();
});
