<?php

/*
 * Testes dos LOGS ESTRUTURADOS (#74): toda resposta leva o identificador do
 * pedido (X-Request-Id); o log sai em JSON, com o identificador e o código
 * estável; CPF, e-mail e senha nunca chegam ao arquivo. Dados INVENTADOS.
 */

use App\Http\Middleware\IdentificadorDoPedido;
use App\Logging\FormatoIbto;
use App\Logging\MascararDadosPessoais;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;

test('toda resposta leva o identificador do pedido; um código de fora estranho é trocado', function () {
    $codigo = $this->get(route('catalogo.index'))->assertOk()->headers->get(IdentificadorDoPedido::CABECALHO);
    expect($codigo)->toMatch('/^[0-9a-f-]{36}$/');

    // Código "limpo" vindo de um proxy: é aproveitado.
    $this->get(route('catalogo.index'), [IdentificadorDoPedido::CABECALHO => 'proxy-12345678'])
        ->assertHeader(IdentificadorDoPedido::CABECALHO, 'proxy-12345678');

    // Código com caracteres perigosos (quebra de linha, <script>): ignorado, sai um novo.
    $novo = $this->get(route('catalogo.index'), [IdentificadorDoPedido::CABECALHO => "<script>\nx"])
        ->headers->get(IdentificadorDoPedido::CABECALHO);
    expect($novo)->not->toContain('<script>');
});

test('a máscara esconde CPF, e-mail, senha e telefone, inclusive dentro de listas e no texto', function () {
    $registro = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'teste',
        level: Level::Error,
        message: 'PIX_FALHOU para fulano@ibto.test com CPF 529.982.247-25',
        context: ['usuario_id' => 7, 'cpf_hash' => 'abc', 'dados' => ['nova_senha' => 'segredo123', 'telefone' => '81999990000', 'obs' => 'cpf 52998224725'], 'cargo' => 'instrutor'],
    );

    $limpo = (new MascararDadosPessoais)($registro);

    expect($limpo->message)->toBe('PIX_FALHOU para [oculto] com CPF [oculto]')
        ->and($limpo->context['usuario_id'])->toBe(7)
        ->and($limpo->context['cpf_hash'])->toBe('[oculto]')
        ->and($limpo->context['dados']['nova_senha'])->toBe('[oculto]')
        ->and($limpo->context['dados']['telefone'])->toBe('[oculto]')
        ->and($limpo->context['dados']['obs'])->toBe('cpf [oculto]')
        // 'cargo' tem 'rg' no meio, mas não é dado pessoal: fica.
        ->and($limpo->context['cargo'])->toBe('instrutor');
});

test('o arquivo de log sai em JSON, com o identificador do pedido e sem o dado pessoal', function () {
    $arquivo = storage_path('logs/teste-estruturado-'.uniqid().'.log');
    config(['logging.channels.teste_ibto' => [
        'driver' => 'single', 'path' => $arquivo, 'level' => 'debug', 'tap' => [FormatoIbto::class],
    ]]);
    Context::add('pedido_id', 'pedido-de-teste-123');

    Log::channel('teste_ibto')->error('CADASTRO_FALHOU', ['email' => 'fulano@ibto.test', 'usuario_id' => 3]);

    $linha = json_decode(trim((string) file_get_contents($arquivo)), true);
    @unlink($arquivo);

    expect($linha['message'])->toBe('CADASTRO_FALHOU')
        ->and($linha['level_name'])->toBe('ERROR')
        ->and($linha['context']['email'])->toBe('[oculto]')
        ->and($linha['context']['usuario_id'])->toBe(3)
        ->and($linha['extra']['pedido_id'])->toBe('pedido-de-teste-123');
});
