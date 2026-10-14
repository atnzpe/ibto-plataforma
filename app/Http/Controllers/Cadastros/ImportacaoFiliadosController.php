<?php

/*
 * Telas da IMPORTAÇÃO de filiados (#37, F4b; pedido do Gleyson em 08/10/2026):
 * 1) enviar o CSV → 2) relacionar as colunas com os campos → 3) relacionar os
 * valores fora do padrão (pulada se tudo bate) → 4) prévia → confirmar.
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
        // O arquivo veio, mas o PHP não conseguiu recebê-lo (ex.: sem pasta
        // temporária, arquivo maior que o limite do servidor). Erro transparente
        // (pedido do Gleyson, 08/10/2026): mensagem em português com um código, e o
        // motivo técnico exato no log (sem dados pessoais, #58).
        $arquivo = $request->file('planilha');
        if ($arquivo !== null && ! $arquivo->isValid()) {
            logger()->error('IMPORTACAO_UPLOAD_FALHOU', [
                // Código do PHP (ex.: 7 = não conseguiu gravar no disco) e o texto dele.
                'codigo_php' => $arquivo->getError(),
                'motivo' => $arquivo->getErrorMessage(),
            ]);

            return back()->withErrors(['planilha' => 'O arquivo não chegou ao servidor: '
                .$this->motivoDoUpload($arquivo->getError())
                .' (código IMPORTACAO_UPLOAD_FALHOU, detalhes no log).']);
        }

        $request->validate(
            ['planilha' => ['required', 'file', 'mimes:csv,txt', 'max:2048']],
            [
                'planilha.required' => 'Escolha o arquivo da planilha.',
                'planilha.uploaded' => 'O arquivo não chegou ao servidor. Tente de novo (código IMPORTACAO_UPLOAD_FALHOU).',
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
     * Grava o mapa das colunas e segue: para "relacionar os valores", se algum
     * valor não bate com o cadastro; senão, direto para a prévia.
     *
     * @param  string  $codigo  O código da importação.
     * @return RedirectResponse Vai para a tela de valores ou para a prévia.
     */
    public function salvarColunas(Request $request, string $codigo, ImportarFiliados $importar): RedirectResponse
    {
        $dados = $this->recuperar($request, $codigo);
        $mapa = $this->validarMapa($request, $dados['colunas']);

        // Mapa novo = relações antigas não valem mais (podem ser de outra coluna).
        $this->guardar($request, $codigo, [...$dados, 'mapa' => $mapa, 'relacoes' => []]);

        $precisaRelacionar = $importar->valoresParaRelacionar($dados['linhas'], $mapa)['valores'] !== [];

        return to_route(
            $precisaRelacionar ? 'cadastros.filiados.importar.valores' : 'cadastros.filiados.importar.previa',
            $codigo,
        );
    }

    /**
     * Tela 3: relacionar os valores que não batem com o cadastro (equipe, nível,
     * áreas), cada um uma vez só, com a sugestão já marcada.
     *
     * @param  string  $codigo  O código da importação.
     */
    public function valores(Request $request, string $codigo, ImportarFiliados $importar): Response
    {
        $dados = $this->recuperar($request, $codigo);
        abort_unless(isset($dados['mapa']), 404);

        $relacao = $importar->valoresParaRelacionar($dados['linhas'], $dados['mapa']);

        return Inertia::render('cadastros/filiados/importar-valores', [
            'codigo' => $codigo,
            'valores' => $relacao['valores'],
            'opcoes' => $relacao['opcoes'],
            'campos' => ImportarFiliados::CAMPOS,
        ]);
    }

    /**
     * Grava as escolhas da tela de valores e vai para a prévia.
     * A tela manda "escolhas" na MESMA ordem da lista de valores (escolhas[0] é do 1º valor).
     *
     * @param  string  $codigo  O código da importação.
     * @return RedirectResponse Vai para a prévia.
     */
    public function salvarValores(Request $request, string $codigo, ImportarFiliados $importar): RedirectResponse
    {
        $dados = $this->recuperar($request, $codigo);
        abort_unless(isset($dados['mapa']), 404);

        $relacao = $importar->valoresParaRelacionar($dados['linhas'], $dados['mapa']);
        /** @var array<int|string, mixed> $escolhas */
        $escolhas = (array) $request->input('escolhas', []);
        $relacoes = [];

        foreach ($relacao['valores'] as $posicao => $valor) {
            $escolha = $escolhas[$posicao] ?? null;
            // Só vale uma das opções oferecidas para aquele campo; outra coisa é recusada (422).
            $permitidas = array_column($relacao['opcoes'][$valor['campo']], 'valor');

            // Comparação ESTRITA (true): tipo e valor iguais; o texto "1" não passa
            // por igual ao número 1 (como o código de barras que precisa ser exato).
            abort_unless(is_string($escolha) && in_array($escolha, $permitidas, true), 422);

            $relacoes[$valor['campo']][Str::lower(Str::ascii(trim($valor['valor'])))] = $escolha;
        }

        $this->guardar($request, $codigo, [...$dados, 'relacoes' => $relacoes]);

        return to_route('cadastros.filiados.importar.previa', $codigo);
    }

    /**
     * Tela 4: prévia. Confere as linhas com o mapa e as relações escolhidas.
     *
     * @param  string  $codigo  O código da importação.
     */
    public function previa(Request $request, string $codigo, ImportarFiliados $importar): Response
    {
        $dados = $this->recuperar($request, $codigo);
        abort_unless(isset($dados['mapa']), 404);

        $resultado = $importar->conferir($dados['linhas'], $dados['mapa'], $dados['relacoes'] ?? []);

        return Inertia::render('cadastros/filiados/importar-previa', [
            'codigo' => $codigo,
            'validas' => count($resultado['validas']),
            // Linhas a que só FALTA dado: entram com o "importar mesmo assim".
            // Só a linha e o que falta (LGPD: os dados da pessoa não vão para a tela).
            'incompletas' => array_map(fn (array $incompleta) => ['linha' => $incompleta['linha'], 'faltas' => $incompleta['faltas']], $resultado['incompletas']),
            // Só linha e motivo (LGPD).
            'recusadas' => $resultado['recusadas'],
            // Se houve a etapa de valores (para o botão "voltar" ir para ela).
            'teve_valores' => $importar->valoresParaRelacionar($dados['linhas'], $dados['mapa'])['valores'] !== [],
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

        $resultado = $importar->conferir($dados['linhas'], $dados['mapa'], $dados['relacoes'] ?? []);

        // "Importar mesmo assim" marcado na prévia: as incompletas entram junto.
        // Desmarcado: só as válidas entram, e as incompletas contam como não importadas.
        $mesmoAssim = $request->boolean('mesmo_assim');
        $paraGravar = $mesmoAssim
            ? [...$resultado['validas'], ...array_column($resultado['incompletas'], 'dados')]
            : $resultado['validas'];
        $gravados = $importar->importar($paraGravar);

        // O arquivo some da memória temporária de vez.
        Cache::forget($this->chave($codigo));

        $recusadas = count($resultado['recusadas']) + $gravados['falhas'] + ($mesmoAssim ? 0 : count($resultado['incompletas']));
        // Quantas entraram com dado faltando (para lembrar de completar).
        $incompletas = $mesmoAssim ? count($resultado['incompletas']) : 0;

        return to_route('cadastros.filiados.index')->with(
            'sucesso',
            "Importação concluída: {$gravados['importados']} filiado(s) cadastrado(s); {$recusadas} linha(s) não importada(s)."
                .($incompletas > 0 ? " {$incompletas} cadastro(s) entraram incompletos: complete na lista (selo \"Incompleto\")." : ''),
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
     * @return array{colunas: list<string>, linhas: list<array<string, string>>, mapa?: array<string, string>, relacoes?: array<string, array<string, string>>}
     */
    private function recuperar(Request $request, string $codigo): array
    {
        $guardado = Cache::get($this->chave($codigo));
        abort_unless(is_string($guardado), 404, 'Importação vencida. Envie o arquivo de novo.');

        /** @var array{usuario_id: int|null, colunas: list<string>, linhas: list<array<string, string>>, mapa?: array<string, string>, relacoes?: array<string, array<string, string>>} $pacote */
        $pacote = json_decode(Crypt::decryptString($guardado), true);

        // A importação é de quem a começou: outra pessoa (mesmo administradora)
        // recebe 404, como se a importação não existisse.
        abort_if($pacote['usuario_id'] !== $request->user()?->id, 404);

        unset($pacote['usuario_id']);

        return $pacote;
    }

    /**
     * Explica, em português, por que o PHP não recebeu o arquivo.
     * Os números são os códigos oficiais de erro de upload do PHP (UPLOAD_ERR_*).
     *
     * @param  int  $codigo  Ex.: 7 (UPLOAD_ERR_CANT_WRITE)
     * @return string Ex.: 'o servidor não conseguiu gravar o arquivo temporário'
     */
    private function motivoDoUpload(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'o arquivo é maior que o limite do servidor',
            UPLOAD_ERR_PARTIAL => 'o envio foi interrompido no meio (conexão caiu?)',
            UPLOAD_ERR_NO_FILE => 'nenhum arquivo foi escolhido',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'o servidor não conseguiu gravar o arquivo temporário',
            UPLOAD_ERR_EXTENSION => 'uma configuração do servidor bloqueou o envio',
            default => 'motivo desconhecido',
        };
    }

    /**
     * Nome da "gaveta" no cache para esta importação.
     */
    private function chave(string $codigo): string
    {
        return "importacao-filiados:{$codigo}";
    }
}
