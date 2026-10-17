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
        abort_if($disponiveis === [], 403);

        return Inertia::render('relatorios/index', [
            'relatorios' => array_map(fn (Relatorio $relatorio) => ['chave' => $relatorio->chave(), 'titulo' => $relatorio->titulo(), 'descricao' => $relatorio->descricao()], $disponiveis),
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
        $colunas = $relatorio->colunas();

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

        return [$relatorio, $periodo, $relatorio->linhas($periodo, $request->user())];
    }

    /**
     * O que a tela e a impressão recebem.
     *
     * @param  list<array<string, mixed>>  $linhas
     * @return array<string, mixed>
     */
    private function dados(Relatorio $relatorio, Periodo $periodo, array $linhas): array
    {
        return [
            'relatorio' => ['chave' => $relatorio->chave(), 'titulo' => $relatorio->titulo(), 'descricao' => $relatorio->descricao()],
            'colunas' => $relatorio->colunas(),
            'linhas' => $linhas,
            'totais' => $relatorio->totais($linhas),
            'periodo' => $periodo->paraTela(),
        ];
    }

    /**
     * Anota a exportação na auditoria (só códigos e datas).
     */
    private function auditar(Relatorio $relatorio, string $formato, Periodo $periodo): void
    {
        Auditoria::registrar('RELATORIO_EXPORTADO', ['relatorio' => $relatorio->chave(), 'formato' => $formato, ...$periodo->paraTela()]);
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
