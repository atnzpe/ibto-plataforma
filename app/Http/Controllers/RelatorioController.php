<?php

/*
 * MÓDULO DE RELATÓRIOS (#73): a mesma tela, o mesmo CSV e a mesma versão para
 * impressão (PDF pelo "Salvar como PDF" do navegador) para todos os relatórios.
 *
 * - Cada relatório tem a sua permissão (relatorio.filiados, relatorio.cursos…);
 *   quem não tem recebe 403.
 * - Filtro De/Até (regra da casa); sem filtro, o ano atual.
 * - Toda exportação (CSV ou impressão) vai para a auditoria: quem, qual, filtros.
 * - LGPD: nenhum relatório traz CPF, nascimento, endereço ou telefone.
 */

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Relatorios\AuditoriaRelatorio;
use App\Relatorios\CursosRelatorio;
use App\Relatorios\FiliadosRelatorio;
use App\Relatorios\FinanceiroRelatorio;
use App\Relatorios\FrequenciaRelatorio;
use App\Relatorios\Relatorio;
use App\Support\Periodo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioController extends Controller
{
    /**
     * As opções de cada filtro (calculadas em montar()). Ex.: ['equipe' => ['IBTO', 'Recife']]
     *
     * @var array<string, list<string>>
     */
    private array $opcoes = [];

    /**
     * Os filtros aplicados (busca, f_<chave>, modo e agrupar), para devolver à tela.
     *
     * @var array<string, string>
     */
    private array $filtros = [];

    /**
     * Todos os relatórios, na ordem do índice.
     *
     * @return list<Relatorio>
     */
    public static function todos(): array
    {
        return [new FiliadosRelatorio, new CursosRelatorio, new FrequenciaRelatorio, new FinanceiroRelatorio, new AuditoriaRelatorio];
    }

    /**
     * O índice: só os relatórios que esta pessoa pode ver.
     */
    public function index(Request $request): Response
    {
        $disponiveis = array_values(array_filter(self::todos(), fn (Relatorio $relatorio) => $request->user()->temPermissao($relatorio->permissao())));
        // Ninguém chega aqui sem pelo menos um relatório (o menu esconde); mesmo assim, 403.
        abort_if($disponiveis === [] && ! $request->user()->temPermissao('relatorio.aulas'), 403);

        $itens = array_map(fn (Relatorio $relatorio) => ['chave' => $relatorio->chave(), 'titulo' => $relatorio->titulo(), 'descricao' => $relatorio->descricao(), 'href' => '/relatorios/'.$relatorio->chave()], $disponiveis);
        // O painel das aulas regulares (17/10/2026) tem tela própria, com mais filtros;
        // entra na lista só para quem tem a permissão dele.
        if ($request->user()->temPermissao('relatorio.aulas')) {
            $itens[] = ['chave' => 'aulas-regulares', 'titulo' => 'Aulas regulares (financeiro)', 'descricao' => 'Quem treina, previsto, recebido, a receber, em atraso e a divisão IBTO/instrutor.', 'href' => '/relatorios/aulas-regulares'];
        }

        return Inertia::render('relatorios/index', [
            'relatorios' => $itens,
        ]);
    }

    /**
     * Um relatório na tela (tabela que vira cartões abaixo de 1024 px).
     */
    public function show(Request $request, string $chave): Response
    {
        [$relatorio, $periodo, $linhas] = $this->montar($request, $chave);

        return Inertia::render('relatorios/ver', [
            ...$this->dados($relatorio, $periodo, $linhas),
        ]);
    }

    /**
     * A versão para impressão (com a marca, a data/hora e quem gerou): o "Salvar como PDF".
     */
    public function imprimir(Request $request, string $chave): Response
    {
        [$relatorio, $periodo, $linhas] = $this->montar($request, $chave);
        $this->auditar($relatorio, 'impressao', $periodo);

        return Inertia::render('relatorios/imprimir', [
            ...$this->dados($relatorio, $periodo, $linhas),
            'gerado_em' => now()->format('d/m/Y H:i'),
            // Quem gerou: o nome abreviado e o número da conta.
            'gerado_por' => AuditoriaRelatorio::abreviado($request->user()->name).' (nº '.$request->user()->id.')',
        ]);
    }

    /**
     * O CSV (abre no Excel e no Google Planilhas), com separador ";" e os acentos certos.
     */
    public function csv(Request $request, string $chave): StreamedResponse
    {
        [$relatorio, $periodo, $linhas] = $this->montar($request, $chave);
        $this->auditar($relatorio, 'csv', $periodo);
        $colunas = $this->colunasDoModo($relatorio);

        return response()->streamDownload(function () use ($colunas, $linhas): void {
            $saida = fopen('php://output', 'w');
            // Não abriu a saída (raro): não há onde escrever.
            if ($saida === false) {
                return;
            }
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, array_column($colunas, 'rotulo'), ';');
            foreach ($linhas as $linha) {
                fputcsv($saida, array_map(fn (array $coluna) => self::paraCsv($linha[$coluna['chave']] ?? null, $coluna['tipo']), $colunas), ';');
            }
            fclose($saida);
        }, "relatorio-{$relatorio->chave()}-ibto.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Acha o relatório, confere a permissão e monta as linhas no período.
     *
     * @return array{0: Relatorio, 1: Periodo, 2: list<array<string, mixed>>}
     */
    private function montar(Request $request, string $chave): array
    {
        $relatorio = collect(self::todos())->first(fn (Relatorio $relatorio) => $relatorio->chave() === $chave);
        // Relatório que não existe: 404. Sem a permissão dele: 403.
        abort_if($relatorio === null, 404);
        abort_unless($request->user()->temPermissao($relatorio->permissao()), 403);

        $periodo = $this->periodo($request);
        $linhas = $relatorio->linhas($periodo, $request->user());
        // As opções de cada filtro saem das linhas ANTES de filtrar (todas as equipes, modalidades...).
        $this->opcoes = $this->opcoesDosFiltros($relatorio, $linhas);

        return [$relatorio, $periodo, $this->filtrar($relatorio, $linhas, $request)];
    }

    /**
     * FILTROS ROBUSTOS (18/10/2026): por dimensão (equipe, modalidade...), por
     * qualquer coluna de texto e pela busca livre. Valor que não existe é ignorado.
     *
     * @param  list<array<string, mixed>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function filtrar(Relatorio $relatorio, array $linhas, Request $request): array
    {
        $this->filtros = ['busca' => mb_substr(trim((string) $request->query('busca', '')), 0, 100)];
        foreach (array_keys($this->opcoes) as $chave) {
            $valor = (string) $request->query("f_{$chave}", '');
            // Só vale um valor que existe na lista (o resto é ignorado, sem erro).
            $this->filtros["f_{$chave}"] = in_array($valor, $this->opcoes[$chave], true) ? $valor : '';
        }

        $linhas = array_values(array_filter($linhas, function (array $linha) use ($relatorio): bool {
            foreach (array_keys($this->opcoes) as $chave) {
                $quero = $this->filtros["f_{$chave}"];
                // Filtro ligado e a linha é de outro valor: fica de fora.
                if ($quero !== '' && (string) ($linha[$chave] ?? '') !== $quero) {
                    return false;
                }
            }
            // Busca livre: parte do texto em qualquer coluna de texto (sem diferenciar maiúscula).
            if ($this->filtros['busca'] !== '') {
                $textos = array_map(fn (array $coluna) => (string) ($linha[$coluna['chave']] ?? ''), array_filter($relatorio->colunas(), fn (array $coluna) => $coluna['tipo'] === 'texto'));

                return str_contains(mb_strtolower(implode(' | ', $textos)), mb_strtolower($this->filtros['busca']));
            }

            return true;
        }));

        // MODO SINTÉTICO: soma as colunas de número e dinheiro por grupo.
        $agrupar = (string) $request->query('agrupar', '');
        $this->filtros['modo'] = $request->query('modo') === 'sintetico' && array_key_exists($agrupar, $this->opcoes) ? 'sintetico' : 'analitico';
        $this->filtros['agrupar'] = $this->filtros['modo'] === 'sintetico' ? $agrupar : '';
        if ($this->filtros['modo'] === 'analitico') {
            return $linhas;
        }

        $somaveis = array_values(array_filter($relatorio->colunas(), fn (array $coluna) => in_array($coluna['tipo'], ['numero', 'dinheiro'], true)));
        // Duas contas separadas por grupo: quantas linhas e a soma de cada coluna.
        $quantidades = [];
        $somas = [];
        foreach ($linhas as $linha) {
            $nome = (string) ($linha[$agrupar] ?? '—');
            $quantidades[$nome] = ($quantidades[$nome] ?? 0) + 1;
            foreach ($somaveis as $coluna) {
                $somas[$nome][$coluna['chave']] = ($somas[$nome][$coluna['chave']] ?? 0) + (int) ($linha[$coluna['chave']] ?? 0);
            }
        }
        ksort($quantidades);

        // Uma linha por grupo: o nome, as somas e a quantidade.
        $resultado = [];
        foreach ($quantidades as $nome => $quantidade) {
            $resultado[] = ['grupo' => (string) $nome, ...($somas[$nome] ?? []), 'quantidade' => $quantidade];
        }

        return $resultado;
    }

    /**
     * As opções dos filtros: cada dimensão e cada coluna de texto com poucos valores
     * (até 60 diferentes; texto livre demais, como "detalhes", não vira filtro).
     *
     * @param  list<array<string, mixed>>  $linhas
     * @return array<string, list<string>> Ex.: ['modalidade' => ['Online', 'Presencial']]
     */
    private function opcoesDosFiltros(Relatorio $relatorio, array $linhas): array
    {
        $opcoes = [];
        $candidatas = [...$relatorio->dimensoes(), ...array_filter($relatorio->colunas(), fn (array $coluna) => $coluna['tipo'] === 'texto')];
        foreach ($candidatas as $campo) {
            $valores = array_values(array_unique(array_map(fn (array $linha) => (string) ($linha[$campo['chave']] ?? ''), $linhas)));
            sort($valores);
            // Só vira filtro quem tem de 1 a 60 valores diferentes (e não é vazio).
            if ($valores !== [] && $valores !== [''] && count($valores) <= 60) {
                $opcoes[$campo['chave']] = $valores;
            }
        }

        return $opcoes;
    }

    /**
     * O que a tela e a impressão recebem.
     *
     * @param  list<array<string, mixed>>  $linhas
     * @return array<string, mixed>
     */
    private function dados(Relatorio $relatorio, Periodo $periodo, array $linhas): array
    {
        $colunas = $this->colunasDoModo($relatorio);
        // Os nomes dos filtros: as dimensões e as colunas de texto que viraram filtro.
        $rotulos = array_column([...$relatorio->dimensoes(), ...$relatorio->colunas()], 'rotulo', 'chave');
        $totais = [];
        foreach ($colunas as $coluna) {
            $totais[$coluna['chave']] = in_array($coluna['tipo'], ['numero', 'dinheiro'], true) ? (int) collect($linhas)->sum($coluna['chave']) : null;
        }

        return [
            'relatorio' => ['chave' => $relatorio->chave(), 'titulo' => $relatorio->titulo(), 'descricao' => $relatorio->descricao()],
            'colunas' => $colunas,
            'linhas' => $linhas,
            'totais' => $totais,
            'periodo' => $periodo->paraTela(),
            'filtros' => $this->filtros,
            // [{chave, rotulo, valores}] para os campos de escolha da tela.
            'campos_filtro' => array_map(fn (string $chave) => ['chave' => $chave, 'rotulo' => $rotulos[$chave] ?? $chave, 'valores' => $this->opcoes[$chave]], array_keys($this->opcoes)),
        ];
    }

    /**
     * As colunas conforme o modo: analítico = as do relatório; sintético = o grupo,
     * as colunas de número/dinheiro somadas e a quantidade de linhas.
     *
     * @return list<array{chave: string, rotulo: string, tipo: string}>
     */
    private function colunasDoModo(Relatorio $relatorio): array
    {
        if (($this->filtros['modo'] ?? 'analitico') !== 'sintetico') {
            return $relatorio->colunas();
        }
        $rotulos = array_column([...$relatorio->dimensoes(), ...$relatorio->colunas()], 'rotulo', 'chave');

        return [
            ['chave' => 'grupo', 'rotulo' => $rotulos[$this->filtros['agrupar']] ?? 'Grupo', 'tipo' => 'texto'],
            ...array_values(array_filter($relatorio->colunas(), fn (array $coluna) => in_array($coluna['tipo'], ['numero', 'dinheiro'], true))),
            ['chave' => 'quantidade', 'rotulo' => 'Linhas', 'tipo' => 'numero'],
        ];
    }

    /**
     * Anota a exportação na auditoria (só códigos e datas).
     */
    private function auditar(Relatorio $relatorio, string $formato, Periodo $periodo): void
    {
        Auditoria::registrar('RELATORIO_EXPORTADO', ['relatorio' => $relatorio->chave(), 'formato' => $formato, ...$periodo->paraTela(), 'filtros' => array_filter($this->filtros)]);
    }

    /**
     * O valor de uma célula no CSV: dinheiro em reais com vírgula; percentual com %.
     */
    private static function paraCsv(mixed $valor, string $tipo): string
    {
        return match (true) {
            $valor === null => '',
            $tipo === 'dinheiro' => number_format(((int) $valor) / 100, 2, ',', ''),
            $tipo === 'percentual' => $valor.'%',
            default => (string) $valor,
        };
    }

    /**
     * O período do filtro, ou o ano atual quando não veio nenhum.
     */
    private function periodo(Request $request): Periodo
    {
        if (! $request->filled('de') && ! $request->filled('ate')) {
            $hoje = CarbonImmutable::now();

            return new Periodo($hoje->startOfYear(), $hoje->endOfYear());
        }

        return Periodo::doPedido($request);
    }
}
