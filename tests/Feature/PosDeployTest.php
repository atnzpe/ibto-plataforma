<?php

use App\Console\Commands\PosDeploy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

// Roda o comando direto (sem o kernel), para o Artisan poder ser simulado:
// nos testes, o migrate e o optimize de verdade nunca rodam.
function rodarPosDeploy(): int
{
    $comando = app(PosDeploy::class);
    $comando->setLaravel(app());

    return $comando->run(new ArrayInput([]), new BufferedOutput);
}

beforeEach(function () {
    $this->publicada = base_path('VERSAO_DEPLOY');
    $this->aplicada = storage_path('app/versao-aplicada');
    File::delete([$this->publicada, $this->aplicada]);
});

afterEach(function () {
    File::delete([$this->publicada, $this->aplicada]);
});

test('sem VERSAO_DEPLOY (ambiente local) não faz nada', function () {
    Artisan::shouldReceive('call')->never();

    expect(rodarPosDeploy())->toBe(0)
        ->and(File::exists($this->aplicada))->toBeFalse();
});

test('versão nova roda migrations e caches e marca como aplicada', function () {
    File::put($this->publicada, "abc123\n");
    Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true], Mockery::any())->ordered();
    Artisan::shouldReceive('call')->once()->with('optimize:clear', [], Mockery::any())->ordered();
    Artisan::shouldReceive('call')->once()->with('optimize', [], Mockery::any())->ordered();

    expect(rodarPosDeploy())->toBe(0)
        ->and(File::get($this->aplicada))->toBe('abc123');
});

test('versão já aplicada não roda nada de novo', function () {
    File::put($this->publicada, 'abc123');
    File::put($this->aplicada, 'abc123');
    Artisan::shouldReceive('call')->never();

    expect(rodarPosDeploy())->toBe(0);
});

test('falha na migration registra o código e tenta de novo na próxima rodada', function () {
    File::put($this->publicada, 'def456');
    File::put($this->aplicada, 'abc123');
    Artisan::shouldReceive('call')->once()->andThrow(new RuntimeException('detalhe interno'));
    Log::spy();

    expect(rodarPosDeploy())->toBe(1)
        ->and(File::get($this->aplicada))->toBe('abc123');

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($mensagem, $contexto) => $mensagem === PosDeploy::CODIGO_FALHA
            && $contexto === ['versao' => 'def456', 'excecao' => RuntimeException::class])
        ->once();
});
