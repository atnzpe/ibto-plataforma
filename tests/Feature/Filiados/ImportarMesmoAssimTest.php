<?php

/*
 * Testes do "IMPORTAR MESMO ASSIM" (#37; pedido do Gleyson em 10/10/2026):
 * - linha a que só FALTA dado vai para as "incompletas" (não é recusada);
 * - dado ERRADO continua recusado, mesmo com a caixa marcada;
 * - sem o nome, ou sem UF e sem registro, a linha é recusada;
 * - na confirmação, as incompletas só entram com "mesmo_assim";
 * - sem e-mail: conta sem e-mail e sem convite; o convite sai quando o
 *   e-mail é completado na edição; a lista mostra o selo "Incompleto".
 * Dados INVENTADOS (CPFs de exemplo público).
 */

use App\Actions\Filiados\ImportarFiliados;
use App\Models\Filiado;
use App\Models\Papel;
use App\Models\User;
use App\Notifications\ConviteFiliado;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

// O mapa de colunas destes testes: o nome da coluna já é o campo.
const MAPA_MESMO_ASSIM = ['nome' => 'nome', 'email' => 'email', 'cpf' => 'cpf', 'nascimento' => 'nascimento', 'municipio' => 'municipio', 'uf' => 'uf', 'registro' => 'registro'];

/**
 * Uma linha inventada completa, com trocas.
 *
 * @param  array<string, string>  $troca  Ex.: ['email' => ''] (célula vazia)
 * @return array<string, string>
 */
function linhaInventada(array $troca = []): array
{
    return [
        'nome' => 'Aluno Inventado', 'email' => 'inventado@ibto.test', 'cpf' => '529.982.247-25',
        'nascimento' => '20/05/1990', 'municipio' => 'Recife', 'uf' => 'PE', 'registro' => '',
        ...$troca,
    ];
}

test('linha só com dado FALTANDO vai para as incompletas, com o que falta', function () {
    $resultado = app(ImportarFiliados::class)->conferir(
        [linhaInventada(['email' => '', 'cpf' => '', 'nascimento' => ''])],
        MAPA_MESMO_ASSIM,
    );

    expect($resultado['validas'])->toBe([]);
    expect($resultado['recusadas'])->toBe([]);
    expect($resultado['incompletas'][0]['linha'])->toBe(2);
    expect($resultado['incompletas'][0]['faltas'])->toBe(['Falta e-mail.', 'Falta cpf.', 'Falta nascimento.']);
    // O CPF vazio NÃO vira "00000000000".
    expect($resultado['incompletas'][0]['dados']['cpf'])->toBeNull();
});

test('dado ERRADO continua recusado; sem nome, ou sem UF e sem registro, também', function () {
    $resultado = app(ImportarFiliados::class)->conferir([
        linhaInventada(['email' => '', 'cpf' => '123']),
        linhaInventada(['nome' => '', 'cpf' => '111.444.777-35', 'email' => 'b@ibto.test']),
        linhaInventada(['uf' => '', 'cpf' => '390.533.447-05', 'email' => 'c@ibto.test']),
    ], MAPA_MESMO_ASSIM);

    expect(array_column($resultado['recusadas'], 'linha'))->toBe([2, 3, 4]);
    expect($resultado['recusadas'][0]['motivos'])->toContain('CPF inválido.');
    expect($resultado['recusadas'][2]['motivos'][0])->toContain('Sem UF e sem registro');
});

test('sem UF, mas com registro: a UF vem do fim do registro e não conta como falta', function () {
    $resultado = app(ImportarFiliados::class)->conferir([linhaInventada(['uf' => '', 'registro' => 'BR20260046PE'])], MAPA_MESMO_ASSIM);

    expect($resultado['validas'][0]['uf'])->toBe('PE');
    expect($resultado['incompletas'])->toBe([]);
});

test('duas linhas sem e-mail e sem CPF não contam como repetidas', function () {
    $resultado = app(ImportarFiliados::class)->conferir([
        linhaInventada(['email' => '', 'cpf' => '']),
        linhaInventada(['email' => '', 'cpf' => '', 'nome' => 'Outro Aluno']),
    ], MAPA_MESMO_ASSIM);

    expect($resultado['incompletas'])->toHaveCount(2);
    expect($resultado['recusadas'])->toBe([]);
});

describe('pelas telas', function () {
    beforeEach(function () {
        Notification::fake();
        $this->admin = User::factory()->create();
        $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
        $this->actingAs($this->admin);
    });

    /**
     * Envia um CSV com 1 linha certa e 1 sem e-mail, e vai até a prévia.
     *
     * @return string O código da importação.
     */
    function irAtePrevia($teste): string
    {
        $csv = UploadedFile::fake()->createWithContent('filiados.csv', implode("\n", [
            'Nome;E-mail;CPF;Nascimento;Município;UF',
            'Aluno Certo;certo@ibto.test;529.982.247-25;20/05/1990;Recife;PE',
            'Aluno Sem Email;;111.444.777-35;01/01/1980;Olinda;PE',
        ]));
        $resposta = $teste->post(route('cadastros.filiados.importar.store'), ['planilha' => $csv]);
        $codigo = basename(dirname((string) $resposta->headers->get('Location')));
        // Mapa pela posição da coluna (como a tela manda).
        $teste->post(route('cadastros.filiados.importar.colunas.salvar', $codigo), ['mapa' => ['nome', 'email', 'cpf', 'nascimento', 'municipio', 'uf']]);

        return $codigo;
    }

    test('a prévia mostra a incompleta só com a linha e o que falta (LGPD)', function () {
        $codigo = irAtePrevia($this);

        $this->get(route('cadastros.filiados.importar.previa', $codigo))
            ->assertInertia(fn ($tela) => $tela
                ->where('validas', 1)
                ->where('incompletas', [['linha' => 3, 'faltas' => ['Falta e-mail.']]]));
    });

    test('sem marcar "mesmo assim", só a certa entra', function () {
        $codigo = irAtePrevia($this);

        $this->post(route('cadastros.filiados.importar.confirmar', $codigo))
            ->assertRedirect(route('cadastros.filiados.index'));

        expect(Filiado::count())->toBe(1);
    });

    test('marcando "mesmo assim", a incompleta entra sem convite; completar o e-mail manda o convite', function () {
        $codigo = irAtePrevia($this);

        $this->post(route('cadastros.filiados.importar.confirmar', $codigo), ['mesmo_assim' => '1'])
            ->assertSessionHas('sucesso', fn (string $mensagem) => str_contains($mensagem, '1 cadastro(s) entraram incompletos'));

        expect(Filiado::count())->toBe(2);
        $semEmail = User::where('name', 'Aluno Sem Email')->firstOrFail();
        expect($semEmail->email)->toBeNull();
        // Convite só para quem tem e-mail.
        Notification::assertSentTo(User::where('email', 'certo@ibto.test')->first(), ConviteFiliado::class);
        Notification::assertNotSentTo($semEmail, ConviteFiliado::class);

        // A lista mostra o selo "Incompleto" só no que está sem e-mail.
        $this->get(route('cadastros.filiados.index'))
            ->assertInertia(fn ($tela) => $tela
                ->where('filiados.data.0.incompleto', false)
                ->where('filiados.data.1.incompleto', true));

        // Completar o e-mail na edição manda o convite que ficou pendente.
        $filiado = $semEmail->filiado;
        $this->put(route('cadastros.filiados.update', $filiado), [
            'nome' => 'Aluno Sem Email', 'email' => 'agora@ibto.test', 'cpf' => '11144477735',
            'nascimento' => '1980-01-01', 'municipio' => 'Olinda', 'uf' => 'PE', 'status' => 'ativo',
        ])->assertSessionHasNoErrors()->assertRedirect(route('cadastros.filiados.index'));

        Notification::assertSentTo($semEmail->fresh(), ConviteFiliado::class);
    });
});
