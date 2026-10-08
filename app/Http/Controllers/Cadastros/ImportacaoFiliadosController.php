<?php

/*
 * Telas da IMPORTAÇÃO de filiados (#37, F4b; pedido do Gleyson em 08/10/2026):
 * 1) enviar o CSV → 2) relacionar as colunas com os campos → 3) prévia → confirmar.
 *
 * Só quem cuida de TODOS os filiados ("filiados.gerenciar") importa.
 *
 * LGPD: o arquivo não é guardado. Entre uma tela e outra, as linhas ficam
 * CRIPTOGRAFADAS na memória temporária (cache) por no máximo 30 minutos, presas
 * a quem começou a importação, e são apagadas ao confirmar.
 */

namespace App\Http\Controllers\Cadastros;

use App\Actions\Filiados\ImportarFiliados;
use App\Http\Controllers\Controller;
use App\Support\Importacao\LeitorCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ImportacaoFiliadosController extends Controller
{
    // Por quantos minutos a importação fica guardada entre as telas.
    private const MINUTOS = 30;

    // Limite de linhas por arquivo (a planilha atual tem 15).
    private const MAX_LINHAS = 2000;

    /**
     * Tela 1: enviar o arquivo.
     */
    public function create(): Response
    {
        return Inertia::render('cadastros/filiados/importar');
    }

    /**
     * Recebe o arquivo, lê, guarda as linhas criptografadas e vai para a tela de colunas.
     *
     * @param  Request  $request  O formulário com o arquivo "planilha".
     * @return RedirectResponse Vai para "relacionar as colunas".
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            ['planilha' => ['required', 'file', 'mimes:csv,txt', 'max:2048']],
            [
                'planilha.required' => 'Escolha o arquivo da planilha.',
                'planilha.mimes' => 'Envie um arquivo CSV (no Excel: Salvar como → CSV).',
                'planilha.max' => 'O arquivo pode ter no máximo 2 MB.',
            ],
        );

        $csv = LeitorCsv::ler((string) file_get_contents($request->file('planilha')->getRealPath()));

        if ($csv['colunas'] === [] || $csv['linhas'] === []) {
            return back()->withErrors(['planilha' => 'A planilha está vazia ou sem cabeçalho.']);
        }
        if (count($csv['linhas']) > self::MAX_LINHAS) {
            return back()->withErrors(['planilha' => 'Até '.self::MAX_LINHAS.' linhas por arquivo. Divida a planilha.']);
        }

        // Código aleatório que identifica esta importação nas próximas telas.
        $codigo = (string) Str::uuid();
        $this->guardar($request, $codigo, ['colunas' => $csv['colunas'], 'linhas' => $csv['linhas']]);

        return to_route('cadastros.filiados.importar.colunas', $codigo);
    }

    /**
     * Tela 2: relacionar as colunas (com a sugestão já marcada).
     *
     * @param  string  $codigo  O código da importação.
     */
    public function colunas(Request $request, string $codigo, ImportarFiliados $importar): Response
    {
        $dados = $this->recuperar($request, $codigo);

        return Inertia::render('cadastros/filiados/importar-colunas', [
            'codigo' => $codigo,
            'colunas' => $dados['colunas'],
            'total_linhas' => count($dados['linhas']),
            'sugestao' => $importar->sugerirMapa($dados['colunas']),
            'campos' => ImportarFiliados::CAMPOS,
        ]);
    }

    /**
     * Tela 3: prévia. Confere as linhas com o mapa escolhido.
     *
     * @param  string  $codigo  O código da importação.
     */
    public function previa(Request $request, string $codigo, ImportarFiliados $importar): Response
    {
        $dados = $this->recuperar($request, $codigo);
        $mapa = $this->validarMapa($request, $dados['colunas']);

        // Guarda o mapa escolhido, para a confirmação usar o mesmo.
        $this->guardar($request, $codigo, [...$dados, 'mapa' => $mapa]);

        $resultado = $importar->conferir($dados['linhas'], $mapa);

        return Inertia::render('cadastros/filiados/importar-previa', [
            'codigo' => $codigo,
            'validas' => count($resultado['validas']),
            // Só linha e motivo (LGPD).
            'recusadas' => $resultado['recusadas'],
        ]);
    }

    /**
     * Confirma: grava as linhas válidas (de novo conferidas, porque o banco pode ter mudado)
     * e apaga a importação guardada.
     *
     * @param  string  $codigo  O código da importação.
     * @return RedirectResponse Volta para a lista de filiados com o resumo.
     */
    public function confirmar(Request $request, string $codigo, ImportarFiliados $importar): RedirectResponse
    {
        $dados = $this->recuperar($request, $codigo);
        abort_unless(isset($dados['mapa']), 422);

        $resultado = $importar->conferir($dados['linhas'], $dados['mapa']);
        $gravados = $importar->importar($resultado['validas']);

        // O arquivo some da memória temporária de vez.
        Cache::forget($this->chave($codigo));

        $recusadas = count($resultado['recusadas']) + $gravados['falhas'];

        return to_route('cadastros.filiados.index')->with(
            'sucesso',
            "Importação concluída: {$gravados['importados']} filiado(s) cadastrado(s) e convidado(s); {$recusadas} linha(s) recusada(s).",
        );
    }

    /**
     * Confere o mapa enviado pela tela: uma escolha para cada coluna, só campos que
     * existem, e o mesmo campo em no máximo uma coluna (menos o "ignorar").
     *
     * @param  list<string>  $colunas
     * @return array<string, string> [coluna => campo]
     */
    private function validarMapa(Request $request, array $colunas): array
    {
        // A tela manda o mapa pela POSIÇÃO da coluna (0, 1, 2...), porque os nomes
        // das colunas podem ter pontos e espaços. Ex.: [0 => 'nome', 1 => 'cpf'].
        /** @var array<int|string, mixed> $enviado */
        $enviado = (array) $request->input('mapa', []);
        $mapa = [];

        foreach ($colunas as $posicao => $coluna) {
            $campo = $enviado[$posicao] ?? 'ignorar';
            $mapa[$coluna] = is_string($campo) && array_key_exists($campo, ImportarFiliados::CAMPOS) ? $campo : 'ignorar';
        }

        $usados = array_filter($mapa, fn (string $campo) => $campo !== 'ignorar');
        if (count($usados) !== count(array_unique($usados))) {
            abort(422, 'O mesmo campo foi escolhido para duas colunas.');
        }

        return $mapa;
    }

    /**
     * Guarda os dados da importação, criptografados e presos a quem começou.
     *
     * @param  array<string, mixed>  $dados
     */
    private function guardar(Request $request, string $codigo, array $dados): void
    {
        $pacote = ['usuario_id' => $request->user()?->id, ...$dados];

        // JSON_THROW_ON_ERROR: se o texto não virar JSON (ex.: acentuação quebrada),
        // dá erro na hora, em vez de seguir com uma importação vazia (nada em silêncio, #58).
        $json = json_encode($pacote, JSON_THROW_ON_ERROR);

        Cache::put($this->chave($codigo), Crypt::encryptString($json), now()->addMinutes(self::MINUTOS));
    }

    /**
     * Recupera a importação guardada. Vencida (30 min) ou de outra pessoa: 404.
     *
     * @return array{colunas: list<string>, linhas: list<array<string, string>>, mapa?: array<string, string>}
     */
    private function recuperar(Request $request, string $codigo): array
    {
        $guardado = Cache::get($this->chave($codigo));
        abort_unless(is_string($guardado), 404, 'Importação vencida. Envie o arquivo de novo.');

        /** @var array{usuario_id: int|null, colunas: list<string>, linhas: list<array<string, string>>, mapa?: array<string, string>} $pacote */
        $pacote = json_decode(Crypt::decryptString($guardado), true);

        // A importação é de quem a começou: outra pessoa (mesmo administradora)
        // recebe 404, como se a importação não existisse.
        abort_if($pacote['usuario_id'] !== $request->user()?->id, 404);

        unset($pacote['usuario_id']);

        return $pacote;
    }

    /**
     * Nome da "gaveta" no cache para esta importação.
     */
    private function chave(string $codigo): string
    {
        return "importacao-filiados:{$codigo}";
    }
}
