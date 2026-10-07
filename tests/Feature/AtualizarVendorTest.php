<?php

/**
 * Testes do deploy/atualizar-vendor.php, o script que o Cron roda para trocar
 * a pasta vendor/ no servidor quando chega um vendor.zip novo.
 *
 * Cada teste monta um "servidor de mentira" numa pasta temporária (nunca mexe
 * no vendor de verdade deste projeto) e cria o zip na hora.
 */

use Illuminate\Support\Facades\File;

// Carrega só as funções do script (a parte que roda sozinha fica de fora,
// porque quem chama aqui é o Pest, não o Cron). Caminho a partir desta pasta
// (__DIR__), porque o base_path() do Laravel ainda não existe neste ponto.
require_once __DIR__.'/../../deploy/atualizar-vendor.php';

/**
 * Cria um vendor.zip de teste dentro da pasta indicada.
 *
 * @param  string  $raiz  Pasta do "servidor de mentira". Ex.: '/tmp/vendor-teste-abc'.
 * @param  array<string, string>  $arquivos  Nome do arquivo => conteúdo.
 *                                           Ex.: ['autoload.php' => '<?php // novo'].
 */
function criarVendorZip(string $raiz, array $arquivos): void
{
    $zip = new ZipArchive;
    $zip->open($raiz.'/vendor.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
    // Cada item vira um arquivo dentro do zip.
    foreach ($arquivos as $nome => $conteudo) {
        $zip->addFromString($nome, $conteudo);
    }
    $zip->close();
}

beforeEach(function () {
    // Pasta temporária única para cada teste, com as subpastas que o servidor tem.
    $this->raiz = sys_get_temp_dir().'/vendor-teste-'.uniqid();
    File::ensureDirectoryExists($this->raiz.'/storage/app');
    File::ensureDirectoryExists($this->raiz.'/storage/logs');
    // O vendor "atual" do servidor, que funciona.
    File::ensureDirectoryExists($this->raiz.'/vendor');
    File::put($this->raiz.'/vendor/autoload.php', '<?php // antigo');
});

afterEach(function () {
    // Apaga o "servidor de mentira" inteiro.
    File::deleteDirectory($this->raiz);
});

test('sem VENDOR_VERSAO (ambiente local) não faz nada', function () {
    expect(atualizarVendor($this->raiz))->toBe('VENDOR_NADA_A_FAZER')
        ->and(File::get($this->raiz.'/vendor/autoload.php'))->toBe('<?php // antigo');
});

test('versão igual à já aplicada não descompacta de novo', function () {
    File::put($this->raiz.'/VENDOR_VERSAO', "abc123\n");
    File::put($this->raiz.'/storage/app/vendor-aplicado', 'abc123');
    criarVendorZip($this->raiz, ['autoload.php' => '<?php // novo']);

    expect(atualizarVendor($this->raiz))->toBe('VENDOR_NADA_A_FAZER')
        ->and(File::get($this->raiz.'/vendor/autoload.php'))->toBe('<?php // antigo');
});

test('VENDOR_VERSAO vazio não faz nada', function () {
    File::put($this->raiz.'/VENDOR_VERSAO', "  \n");
    criarVendorZip($this->raiz, ['autoload.php' => '<?php // novo']);

    expect(atualizarVendor($this->raiz))->toBe('VENDOR_NADA_A_FAZER')
        ->and(File::get($this->raiz.'/vendor/autoload.php'))->toBe('<?php // antigo');
});

test('versão nova troca o vendor e marca como aplicada', function () {
    File::put($this->raiz.'/VENDOR_VERSAO', "def456\n");
    File::put($this->raiz.'/storage/app/vendor-aplicado', 'abc123');
    criarVendorZip($this->raiz, [
        'autoload.php' => '<?php // novo',
        'laravel/framework/README.md' => 'subpasta',
    ]);

    expect(atualizarVendor($this->raiz))->toBe('VENDOR_APLICADO')
        ->and(File::get($this->raiz.'/vendor/autoload.php'))->toBe('<?php // novo')
        ->and(File::exists($this->raiz.'/vendor/laravel/framework/README.md'))->toBeTrue()
        ->and(File::get($this->raiz.'/storage/app/vendor-aplicado'))->toBe('def456')
        // As pastas de trabalho não podem sobrar.
        ->and(File::exists($this->raiz.'/vendor-novo'))->toBeFalse()
        ->and(File::exists($this->raiz.'/vendor-antigo'))->toBeFalse();
});

test('primeira vez (sem vendor-aplicado) também aplica', function () {
    File::put($this->raiz.'/VENDOR_VERSAO', 'def456');
    criarVendorZip($this->raiz, ['autoload.php' => '<?php // novo']);

    expect(atualizarVendor($this->raiz))->toBe('VENDOR_APLICADO')
        ->and(File::get($this->raiz.'/storage/app/vendor-aplicado'))->toBe('def456');
});

test('zip corrompido mantém o vendor antigo e registra VENDOR_FALHOU', function () {
    File::put($this->raiz.'/VENDOR_VERSAO', 'def456');
    File::put($this->raiz.'/vendor.zip', 'isto não é um zip');

    expect(atualizarVendor($this->raiz))->toBe('VENDOR_FALHOU')
        ->and(File::get($this->raiz.'/vendor/autoload.php'))->toBe('<?php // antigo')
        // Não marcou como aplicada: o próximo Cron tenta de novo.
        ->and(File::exists($this->raiz.'/storage/app/vendor-aplicado'))->toBeFalse()
        ->and(File::get($this->raiz.'/storage/logs/vendor-'.date('Y-m-d').'.log'))->toContain('VENDOR_FALHOU');
});

test('zip sem autoload.php mantém o vendor antigo', function () {
    File::put($this->raiz.'/VENDOR_VERSAO', 'def456');
    criarVendorZip($this->raiz, ['outro-arquivo.php' => '<?php']);

    expect(atualizarVendor($this->raiz))->toBe('VENDOR_FALHOU')
        ->and(File::get($this->raiz.'/vendor/autoload.php'))->toBe('<?php // antigo')
        ->and(File::exists($this->raiz.'/vendor-novo'))->toBeFalse();
});
