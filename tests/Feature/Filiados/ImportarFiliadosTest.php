<?php

/*
 * Testes da importação de filiados (#37, F4a): leitura do CSV, sugestão de
 * colunas, conferência linha a linha (com os problemas reais da planilha do
 * Canva: nível "Nacional", equipe "-", CPF sem o zero da frente) e gravação.
 * Planilha INVENTADA: nenhum dado real (CPFs de exemplo público).
 */

use App\Actions\Filiados\ImportarFiliados;
use App\Models\AreaAtuacao;
use App\Models\Filiado;
use App\Models\Nivel;
use App\Notifications\ConviteFiliado;
use App\Support\Importacao\LeitorCsv;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

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

test('sugere o campo de cada coluna pelo nome, inclusive a Categoria', function () {
    $colunas = LeitorCsv::ler(planilhaInventada())['colunas'];
    $mapa = app(ImportarFiliados::class)->sugerirMapa($colunas);

    expect($mapa['Número de registro'])->toBe('registro');
    expect($mapa['Área de Atuação'])->toBe('areas');
    expect($mapa['Município'])->toBe('municipio');
    expect($mapa['E-mail'])->toBe('email');
    // "Categoria" = a função (Aluno, Instrutor...): campo próprio desde 09/10/2026.
    expect($mapa['Categoria'])->toBe('categoria');
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

test('CSV salvo pelo Excel em português (acentuação antiga, Windows-1252) é convertido', function () {
    // Monta o texto em UTF-8 e converte para a acentuação antiga, como o Excel salva.
    $windows = mb_convert_encoding("Município;Equipe\nRecife;Dragão Negro", 'Windows-1252', 'UTF-8');

    $csv = LeitorCsv::ler($windows);

    expect($csv['colunas'])->toBe(['Município', 'Equipe']);
    expect($csv['linhas'][0]['Equipe'])->toBe('Dragão Negro');
});

test('célula entre aspas com quebra de linha dentro (UF "RJ" + Enter) não parte a linha', function () {
    // Mesmo formato da planilha do Canva: separador "," e a UF com Enter dentro das aspas.
    $csv = LeitorCsv::ler("Nome completo,UF,Município\nTeste Um,\"RJ\n\",Niterói\nTeste Dois,SP ,Campinas");

    expect($csv['linhas'])->toHaveCount(2);
    expect($csv['linhas'][0]['UF'])->toBe('RJ');
    expect($csv['linhas'][0]['Município'])->toBe('Niterói');
    // Espaço sobrando nas pontas ("SP ") também sai.
    expect($csv['linhas'][1]['UF'])->toBe('SP');
});

test('equipe em MAIÚSCULAS com acento é encontrada ("TÁTICOS" = "Táticos")', function () {
    $importar = app(ImportarFiliados::class);
    $linhas = [[
        'Nome' => 'Teste Maiusculo', 'E-mail' => 'maiusculo@ibto.test', 'CPF' => '529.982.247-25',
        'Nascimento' => '20/05/1990', 'Município' => 'Recife', 'UF' => 'PE',
        'Equipe' => 'ALFA COMBAT TREINAMENTOS TÁTICOS', 'Área de Atuação' => 'KAPAP, krav maga',
    ]];

    $resultado = $importar->conferir($linhas, $importar->sugerirMapa(array_keys($linhas[0])));

    expect($resultado['recusadas'])->toBe([]);
    expect($resultado['validas'][0]['equipe_id'])->not->toBeNull();
    expect($resultado['validas'][0]['areas'])->toHaveCount(2);
});

/**
 * Linhas inventadas com os mesmos tipos de valor fora do padrão da planilha do Canva.
 *
 * @return list<array<string, string>>
 */
function linhasComValoresForaDoPadrao(): array
{
    $base = ['Nascimento' => '20/05/1990', 'Município' => 'Recife', 'UF' => 'PE', 'Equipe' => 'Vulcan'];

    return [
        [...$base, 'Nome' => 'Teste A', 'E-mail' => 'a@ibto.test', 'CPF' => '529.982.247-25', 'Nível' => 'Expert Nível 3', 'Área de Atuação' => 'Combate com Facas, Krav Maga'],
        [...$base, 'Nome' => 'Teste B', 'E-mail' => 'b@ibto.test', 'CPF' => '111.444.777-35', 'Nível' => 'Nacional', 'Área de Atuação' => 'Kraaav Maga'],
        [...$base, 'Nome' => 'Teste C', 'E-mail' => 'c@ibto.test', 'CPF' => '390.533.447-05', 'Nível' => 'Instrutor Nivel 2', 'Área de Atuação' => 'TCCC'],
    ];
}

test('acha cada valor fora do padrão UMA vez, com a sugestão certa', function () {
    $importar = app(ImportarFiliados::class);
    $linhas = linhasComValoresForaDoPadrao();

    $resultado = $importar->valoresParaRelacionar($linhas, $importar->sugerirMapa(array_keys($linhas[0])));

    // [campo|valor => sugestão], para conferir fácil.
    $sugestoes = collect($resultado['valores'])->mapWithKeys(fn ($v) => [$v['campo'].'|'.$v['valor'] => $v['sugestao']]);
    $nivelId = fn (string $nome) => 'nivel:'.Nivel::all()->first(fn (Nivel $n) => $n->nome() === $nome)->id;
    $areaId = fn (string $nome) => 'area:'.AreaAtuacao::where('nome', $nome)->value('id');

    expect($sugestoes['nivel|Expert Nível 3'])->toBe($nivelId('Instrutor Expert III'));
    expect($sugestoes['nivel|Instrutor Nivel 2'])->toBe($nivelId('Instrutor II'));
    expect($sugestoes['nivel|Nacional'])->toBe('abrangencia:nacional');
    expect($sugestoes['areas|Combate com Facas'])->toBe($areaId('Combate com Faca'));
    expect($sugestoes['areas|Kraaav Maga'])->toBe($areaId('Krav Maga'));
    // "TCCC" não se parece com o nome completo: sem sugestão, quem escolhe é a pessoa.
    expect($sugestoes['areas|TCCC'])->toBeNull();
    // "Krav Maga" e "Vulcan" já batem com o cadastro: não aparecem para relacionar.
    expect($sugestoes->has('areas|Krav Maga'))->toBeFalse();
    expect($sugestoes->has('equipe|Vulcan'))->toBeFalse();
});

test('com os valores relacionados, as linhas entram (e "Nacional" vira abrangência)', function () {
    $importar = app(ImportarFiliados::class);
    $linhas = linhasComValoresForaDoPadrao();
    $mapa = $importar->sugerirMapa(array_keys($linhas[0]));

    // Sem relacionar: as 3 são recusadas.
    expect($importar->conferir($linhas, $mapa)['validas'])->toHaveCount(0);

    // Aceita todas as sugestões e escolhe a área do "TCCC" à mão.
    $relacoes = [];
    foreach ($importar->valoresParaRelacionar($linhas, $mapa)['valores'] as $valor) {
        $relacoes[$valor['campo']][Str::lower(Str::ascii($valor['valor']))] = $valor['sugestao']
            ?? 'area:'.AreaAtuacao::where('nome', 'Tactical Combat Casualty Care')->value('id');
    }

    $resultado = $importar->conferir($linhas, $mapa, $relacoes);

    expect($resultado['recusadas'])->toBe([]);
    expect($resultado['validas'][1]['abrangencia'])->toBe('nacional');
    expect($resultado['validas'][1]['nivel_id'])->toBeNull();
});

test('categoria: "INSTRUTOR EXPERT" e "PRATICANTE" batem sozinhos; "ALUNO" pede relação', function () {
    $importar = app(ImportarFiliados::class);
    $base = ['Nascimento' => '20/05/1990', 'Município' => 'Recife', 'UF' => 'PE'];
    $linhas = [
        [...$base, 'Nome' => 'Teste A', 'E-mail' => 'a@ibto.test', 'CPF' => '529.982.247-25', 'Categoria' => 'INSTRUTOR EXPERT'],
        [...$base, 'Nome' => 'Teste B', 'E-mail' => 'b@ibto.test', 'CPF' => '111.444.777-35', 'Categoria' => 'PRATICANTE'],
        [...$base, 'Nome' => 'Teste C', 'E-mail' => 'c@ibto.test', 'CPF' => '390.533.447-05', 'Categoria' => 'ALUNO'],
    ];
    $mapa = $importar->sugerirMapa(array_keys($linhas[0]));

    // Só o "ALUNO" aparece para relacionar: não é uma das 5 categorias (Aluno é PAPEL,
    // de quem não é filiado). "PRATICANTE" é categoria desde 09/10/2026.
    $valores = $importar->valoresParaRelacionar($linhas, $mapa)['valores'];
    expect(array_column($valores, 'valor'))->toBe(['ALUNO']);

    // Relacionando "ALUNO" → Praticante, as três linhas entram.
    $resultado = $importar->conferir($linhas, $mapa, ['categoria' => ['aluno' => 'categoria:praticante']]);

    expect($resultado['recusadas'])->toBe([]);
    expect(array_column($resultado['validas'], 'categoria'))->toBe(['instrutor_expert', 'praticante', 'praticante']);
});

test('importação: coluna "Documentação" com sim/não; sem a coluna, vale o padrão', function () {
    $importar = app(ImportarFiliados::class);
    $base = ['Nascimento' => '20/05/1990', 'Município' => 'Recife', 'UF' => 'PE'];
    $linhas = [
        [...$base, 'Nome' => 'Teste A', 'E-mail' => 'a@ibto.test', 'CPF' => '529.982.247-25', 'Documentação' => 'Não'],
        [...$base, 'Nome' => 'Teste B', 'E-mail' => 'b@ibto.test', 'CPF' => '111.444.777-35', 'Documentação' => 'SIM'],
        [...$base, 'Nome' => 'Teste C', 'E-mail' => 'c@ibto.test', 'CPF' => '390.533.447-05', 'Documentação' => 'talvez'],
    ];

    $resultado = $importar->conferir($linhas, $importar->sugerirMapa(array_keys($linhas[0])));

    expect(array_column($resultado['validas'], 'documentacao_emitida'))->toBe([false, true]);
    expect($resultado['recusadas'][0]['motivos'])->toContain('Documentação "talvez" inválida (use sim ou não).');
});
