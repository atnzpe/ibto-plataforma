<?php

/*
 * Testes da importação de filiados (#37, F4a): leitura do CSV, sugestão de
 * colunas, conferência linha a linha (com os problemas reais da planilha do
 * Canva: nível "Nacional", equipe "-", CPF sem o zero da frente) e gravação.
 * Planilha INVENTADA: nenhum dado real (CPFs de exemplo público).
 */

use App\Actions\Filiados\ImportarFiliados;
use App\Models\Filiado;
use App\Notifications\ConviteFiliado;
use App\Support\Importacao\LeitorCsv;
use Illuminate\Support\Facades\Notification;

/**
 * Uma planilha inventada, com o cabeçalho igual ao da planilha do Canva (+ E-mail).
 */
function planilhaInventada(): string
{
    // \xEF\xBB\xBF = o BOM que o Excel põe no começo; separador ";" (Excel em português).
    return "\xEF\xBB\xBF".implode("\n", [
        'Número de registro;Nome completo;Nascimento;UF;Município;Categoria;CPF;Área de Atuação;Nível;Equipe;Telefone;E-mail',
        // Linha 2: tudo certo.
        'BR20260001PE;Aluno Um;20/05/1990;PE;Recife;Instrutor;529.982.247-25;Krav Maga, KAPAP;Instrutor Expert IV;Vulcan;(81) 99999-0000;um@ibto.test',
        // Linha 3: nível "Nacional" (problema real da planilha) e data impossível.
        'BR20260002SP;Aluno Dois;31/02/1985;SP;Campinas;Instrutor;111.444.777-35;Krav Maga;Nacional;Vulcan;;dois@ibto.test',
        // Linha 4: sem equipe ("-") e sem registro: entra, e ganha um registro novo.
        ';Aluno Tres;01/01/2000;RJ;Niterói;Praticante;390.533.447-05;;Praticante;-;;tres@ibto.test',
        // Linha 5: CPF repetido dentro da própria planilha (o mesmo da linha 2).
        'BR20260004MG;Aluno Quatro;02/02/1992;MG;BH;Praticante;52998224725;;Praticante;Vulcan;;quatro@ibto.test',
    ]);
}

test('o leitor entende o BOM, o separador ";" e o cabeçalho', function () {
    $csv = LeitorCsv::ler(planilhaInventada());

    expect($csv['colunas'][0])->toBe('Número de registro');
    expect($csv['linhas'])->toHaveCount(4);
    expect($csv['linhas'][0]['Nome completo'])->toBe('Aluno Um');
});

test('sugere o campo de cada coluna pelo nome; "Categoria" fica para ignorar', function () {
    $colunas = LeitorCsv::ler(planilhaInventada())['colunas'];
    $mapa = app(ImportarFiliados::class)->sugerirMapa($colunas);

    expect($mapa['Número de registro'])->toBe('registro');
    expect($mapa['Área de Atuação'])->toBe('areas');
    expect($mapa['Município'])->toBe('municipio');
    expect($mapa['E-mail'])->toBe('email');
    // A categoria já vem no nível (ex.: "Instrutor Expert IV"): não vai para nenhum campo.
    expect($mapa['Categoria'])->toBe('ignorar');
});

test('confere linha a linha: entram 2, recusa 2 com o motivo e sem dados pessoais', function () {
    $importar = app(ImportarFiliados::class);
    $csv = LeitorCsv::ler(planilhaInventada());

    $resultado = $importar->conferir($csv['linhas'], $importar->sugerirMapa($csv['colunas']));

    expect($resultado['validas'])->toHaveCount(2);
    expect(array_column($resultado['recusadas'], 'linha'))->toBe([3, 5]);

    $motivosLinha3 = $resultado['recusadas'][0]['motivos'];
    expect($motivosLinha3)->toContain('Data de nascimento inválida (use dd/mm/aaaa).');
    expect(implode(' ', $motivosLinha3))->toContain('Nível "Nacional" não existe');
    expect($resultado['recusadas'][1]['motivos'])->toContain('CPF repetido dentro da planilha.');

    // LGPD: o relatório não traz nome, CPF nem e-mail.
    $relatorio = json_encode($resultado['recusadas']);
    expect($relatorio)->not->toContain('Aluno')->not->toContain('111.444')->not->toContain('@ibto.test');
});

test('CPF que perdeu o zero da frente na planilha é completado', function () {
    $importar = app(ImportarFiliados::class);
    // 01210000016 é um CPF de teste (inventado, válido) com zero na frente; na planilha virou 1210000016.
    $linhas = [['Nome' => 'Aluno Zero', 'E-mail' => 'zero@ibto.test', 'CPF' => '1210000016', 'Nascimento' => '10/10/1980', 'Município' => 'Recife', 'UF' => 'PE']];

    $resultado = $importar->conferir($linhas, $importar->sugerirMapa(array_keys($linhas[0])));

    expect($resultado['recusadas'])->toBe([]);
    expect($resultado['validas'][0]['cpf'])->toBe('01210000016');
});

test('importar grava as válidas, mantém o registro da planilha e manda os convites', function () {
    Notification::fake();
    $importar = app(ImportarFiliados::class);
    $csv = LeitorCsv::ler(planilhaInventada());
    $validas = $importar->conferir($csv['linhas'], $importar->sugerirMapa($csv['colunas']))['validas'];

    expect($importar->importar($validas))->toBe(['importados' => 2, 'falhas' => 0]);

    // A carteirinha da planilha continua com o mesmo número.
    $um = Filiado::where('registro', 'BR20260001PE')->firstOrFail();
    expect($um->nivel->nome())->toBe('Instrutor Expert IV');
    expect($um->areas()->pluck('nome')->sort()->values()->all())->toBe(['KAPAP', 'Krav Maga']);

    // Quem não tinha registro ganhou um novo, e sem equipe ("-").
    $tres = Filiado::whereHas('usuario', fn ($q) => $q->where('email', 'tres@ibto.test'))->firstOrFail();
    expect($tres->registro)->toStartWith('BR'.now()->format('Y'));
    expect($tres->equipe_id)->toBeNull();

    Notification::assertSentTimes(ConviteFiliado::class, 2);
});
