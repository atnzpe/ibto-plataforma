<?php

/*
 * Testes do CRONOGRAMA CLICÁVEL (#38, bloco C4d): o arquivo de agenda (.ics)
 * do encontro (horário em UTC, dia inteiro, avisos 1 dia e 1 hora antes,
 * escape e linhas dobradas), quem pode baixar e os dados da janela na
 * página do curso (introdução pública e link do Google Agenda).
 * Dados INVENTADOS.
 */

use App\Models\Curso;
use App\Models\CursoModulo;
use App\Support\Calendario;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->curso = Curso::create([
        'titulo' => 'Combate Corpo a Corpo', 'modalidade' => 'presencial', 'acesso' => 'aberto',
        'valor_centavos' => 25000, 'data_inicio' => '2026-10-10', 'local' => 'Recife, PE',
    ]);
    // A situação não entra pelo create (só pelo fluxo da chancela): forceFill.
    $this->curso->forceFill(['situacao' => 'publicado'])->save();

    $modulo = CursoModulo::create(['curso_id' => $this->curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
    $this->encontro = $modulo->aulas()->create([
        'titulo' => 'Aula 1', 'ordem' => 1, 'ativa' => true,
        'data' => '2026-10-10', 'hora_inicio' => '09:00', 'hora_fim' => '12:00',
        'quem_aplica' => 'Instrutor Trinca', 'introducao' => 'Postura, guarda e deslocamento.',
    ]);
    $this->semData = $modulo->aulas()->create(['titulo' => 'Aula online', 'ordem' => 2, 'ativa' => true]);
});

test('o .ics tem o horário em UTC (09:00 de Brasília = 12:00Z) e os dois avisos', function () {
    $resposta = $this->get(route('catalogo.lembrete', ['curso' => $this->curso->id, 'aula' => $this->encontro->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    $ics = $resposta->getContent();
    expect($ics)
        ->toContain("DTSTART:20261010T120000Z\r\n")
        ->toContain("DTEND:20261010T150000Z\r\n")
        // Vírgula escapada no local.
        ->toContain('LOCATION:Recife\\, PE')
        ->toContain('TRIGGER:-P1D')
        ->toContain('TRIGGER:-PT1H')
        ->toContain('Instrutor Trinca');
});

test('sem hora: o encontro ocupa o dia inteiro', function () {
    $ics = Calendario::evento('x@teste', 'Encontro', Carbon::parse('2026-10-10'), Carbon::parse('2026-10-11'), diaInteiro: true);

    expect($ics)->toContain("DTSTART;VALUE=DATE:20261010\r\n")->toContain("DTEND;VALUE=DATE:20261011\r\n");
});

test('linha longa é dobrada em pedaços de até 75 bytes, sem cortar acento', function () {
    $ics = Calendario::evento('x@teste', str_repeat('Ação ', 40), Carbon::parse('2026-10-10 09:00'), Carbon::parse('2026-10-10 10:00'));

    foreach (explode("\r\n", $ics) as $linha) {
        expect(strlen($linha))->toBeLessThanOrEqual(75);
        // mb_check_encoding: a linha continua sendo texto válido (nenhum "ç" partido ao meio).
        expect(mb_check_encoding($linha, 'UTF-8'))->toBeTrue();
    }
});

test('lembrete: aula sem data, de outro curso ou curso em rascunho = 404', function () {
    $this->get(route('catalogo.lembrete', ['curso' => $this->curso->id, 'aula' => $this->semData->id]))->assertNotFound();

    $outro = Curso::create(['titulo' => 'Outro', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 0]);
    $outro->forceFill(['situacao' => 'publicado'])->save();
    $this->get(route('catalogo.lembrete', ['curso' => $outro->id, 'aula' => $this->encontro->id]))->assertNotFound();

    $this->curso->forceFill(['situacao' => 'rascunho'])->save();
    $this->get(route('catalogo.lembrete', ['curso' => $this->curso->id, 'aula' => $this->encontro->id]))->assertNotFound();
});

test('página do curso: a janela recebe a introdução e os links; aula sem data, sem links', function () {
    $this->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela
            ->where('curso.modulos.0.aulas.0.introducao', 'Postura, guarda e deslocamento.')
            ->where('curso.modulos.0.aulas.0.lembrete_url', route('catalogo.lembrete', ['curso' => $this->curso->id, 'aula' => $this->encontro->id]))
            ->where('curso.modulos.0.aulas.0.google_agenda_url', fn (string $url) => str_contains($url, 'dates=20261010T120000Z%2F20261010T150000Z'))
            ->where('curso.modulos.0.aulas.1.lembrete_url', null)
            ->where('curso.modulos.0.aulas.1.google_agenda_url', null));
});
