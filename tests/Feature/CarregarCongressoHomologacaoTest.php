<?php

/*
 * Testes da CARGA DO CONGRESSO NACIONAL IBTO na homologação (15/11/2026):
 * cria o congresso publicado, presencial, de um dia, com 10 encontros e a capa;
 * não duplica nem apaga o que a equipe ajustou ao rodar de novo.
 */

use App\Console\Commands\CarregarCongressoHomologacao;
use App\Enums\ModalidadeCurso;
use App\Enums\SituacaoCurso;
use App\Models\Curso;
use Illuminate\Support\Facades\Storage;

// Antes de cada teste: um disco de imagens "de mentira", para não gravar arquivos de verdade.
beforeEach(function () {
    Storage::fake('public');
});

test('cria o congresso publicado, de um dia, com 10 encontros e a capa', function () {
    $this->artisan('plataforma:carregar-congresso')->assertSuccessful();

    // sole(): falha se não houver exatamente um congresso.
    $curso = Curso::where('titulo', CarregarCongressoHomologacao::TITULO)->sole();

    // A capa (o cartaz) foi copiada para o disco público.
    Storage::disk('public')->assertExists($curso->imagem);

    expect($curso->situacao)->toBe(SituacaoCurso::Publicado)
        ->and($curso->modalidade)->toBe(ModalidadeCurso::Presencial)
        // Um dia só: começa e termina em 21/02/2027.
        ->and($curso->data_inicio->toDateString())->toBe('2027-02-21')
        ->and($curso->data_fim->toDateString())->toBe('2027-02-21')
        // O título avisa que é teste (sai na limpeza antes da produção).
        ->and($curso->titulo)->toStartWith('[TESTE] ')
        // A descrição avisa que valor, vagas e horários são exemplo.
        ->and($curso->descricao)->toContain('EXEMPLOS')
        ->and($curso->modulos()->first()->aulas()->count())->toBe(10);
});

test('rodar de novo não duplica nem desfaz o que a equipe ajustou', function () {
    $this->artisan('plataforma:carregar-congresso');

    // A equipe muda o valor na tela (simulado aqui direto no banco).
    Curso::where('titulo', CarregarCongressoHomologacao::TITULO)->update(['valor_centavos' => 30000]);

    $this->artisan('plataforma:carregar-congresso')->assertSuccessful();

    // Continua um só, e com o valor que a equipe escolheu.
    expect(Curso::where('titulo', CarregarCongressoHomologacao::TITULO)->count())->toBe(1)
        ->and(Curso::where('titulo', CarregarCongressoHomologacao::TITULO)->value('valor_centavos'))->toBe(30000);
});
