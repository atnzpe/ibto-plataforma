<?php

/*
 * Testes da CARGA DA BASE DO PILOTO (16/10/2026): os cursos reais com a data
 * real (Encerrado), as turmas [TESTE] publicadas com data futura, os dois online
 * de teste, a trilha na ordem e rodar duas vezes sem duplicar. Também confere que
 * o pos-deploy só roda a carga com PILOTO_CARREGAR ligado, e uma vez só.
 */

use App\Console\Commands\CarregarPiloto;
use App\Console\Commands\PosDeploy;
use App\Enums\SituacaoCurso;
use App\Models\Curso;
use App\Models\Trilha;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function () {
    Storage::fake('public');
});

test('carrega os cursos reais, as turmas de teste, os online e a trilha', function () {
    $this->artisan('plataforma:carregar-piloto')->assertSuccessful();

    // Real e já aconteceu: Encerrado, com a data e o valor do cartaz e a capa.
    $faca = Curso::where('titulo', 'Treinamento para Combate com Faca')->firstOrFail();
    expect($faca->situacao)->toBe(SituacaoCurso::Encerrado)
        ->and($faca->data_inicio->toDateString())->toBe('2026-10-10')
        ->and($faca->valor_centavos)->toBe(25000)
        ->and($faca->instrutor_nome)->toBe('Marcelo');
    Storage::disk('public')->assertExists($faca->imagem);

    // A turma de teste: publicada, data futura, com o encontro para a chamada.
    $teste = Curso::where('titulo', CarregarPiloto::PREFIXO_TESTE.'Treinamento para Combate com Faca')->firstOrFail();
    expect($teste->situacao)->toBe(SituacaoCurso::Publicado)
        ->and($teste->data_inicio->isFuture())->toBeTrue()
        ->and($teste->modulos()->first()->aulas()->whereNotNull('data')->count())->toBe(1);

    // 9 reais (5 principais, Operação Avançada e 3 turmas do Combate Real) + 5 de teste + 2 online de teste.
    expect(Curso::count())->toBe(16)
        ->and(Curso::where('titulo', 'like', '[TESTE]%')->count())->toBe(7);

    // Nenhuma conta criada para os instrutores (entram só como texto).
    expect(User::count())->toBe(0);

    // A trilha, na ordem: corpo a corpo, faca, arma de fogo, sobrevivência.
    $ordem = Trilha::where('nome', 'Formação Operacional')->firstOrFail()->cursos->pluck('titulo')->all();
    expect($ordem)->toBe([
        '[TESTE] Treinamento para Combate Corpo a Corpo',
        '[TESTE] Treinamento para Combate com Faca',
        '[TESTE] Treinamento para Operador de Arma de Fogo',
        '[TESTE] Curso Sobrevivência Urbana: Nível 1',
    ]);
});

test('Combate Real: duas turmas encerradas e a de novembro em rascunho, com um encontro por dia', function () {
    $this->artisan('plataforma:carregar-piloto')->assertSuccessful();

    $novembro = Curso::where('titulo', 'Treinamento de Combate Real: turma de 14 e 15/11/2026')->firstOrFail();
    expect($novembro->situacao)->toBe(SituacaoCurso::Rascunho)
        ->and($novembro->local)->toBe('Piracicaba-SP')
        ->and($novembro->modulos()->first()->aulas()->pluck('data')->map->toDateString()->all())->toBe(['2026-11-14', '2026-11-15']);

    expect(Curso::where('titulo', 'like', 'Treinamento de Combate Real%')->where('situacao', SituacaoCurso::Encerrado)->count())->toBe(2);
});

test('rodar duas vezes não duplica nada', function () {
    $this->artisan('plataforma:carregar-piloto')->assertSuccessful();
    $this->artisan('plataforma:carregar-piloto')->assertSuccessful();

    expect(Curso::count())->toBe(16)
        ->and(Trilha::count())->toBe(1)
        ->and(Trilha::first()->cursos()->count())->toBe(4);
});

test('o pos-deploy só carrega com PILOTO_CARREGAR ligado, e uma vez só', function () {
    $marca = storage_path('app/piloto-carregado');
    $publicada = base_path('VERSAO_DEPLOY');
    $aplicada = storage_path('app/versao-aplicada');
    File::delete([$marca, $publicada, $aplicada]);
    // Versão já aplicada: o pos-deploy só cuida da placa e da carga.
    File::put($publicada, 'abc');
    File::put($aplicada, 'abc');

    $rodar = function (): void {
        $comando = app(PosDeploy::class);
        $comando->setLaravel(app());
        $comando->run(new ArrayInput([]), new BufferedOutput);
    };

    // Desligado: nada é carregado.
    config(['app.piloto_carregar' => false]);
    $rodar();
    expect(Curso::count())->toBe(0);

    // Ligado: carrega e grava a marca; rodar de novo não repete.
    config(['app.piloto_carregar' => true]);
    $rodar();
    expect(Curso::count())->toBe(16)->and(File::exists($marca))->toBeTrue();

    // Renomeia um curso: se a carga rodasse de novo, recriaria o original (seriam 17).
    Curso::where('titulo', 'Seminário Bastão Tático')->update(['titulo' => 'Bastão (renomeado pelo IBTO)']);
    $rodar();
    expect(Curso::count())->toBe(16);

    File::delete([$marca, $publicada, $aplicada]);
});
