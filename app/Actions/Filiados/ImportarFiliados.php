<?php

/*
 * IMPORTAÇÃO de filiados a partir da planilha (#37, F4; pedido do Gleyson em
 * 08/10/2026: relacionar as colunas da planilha com os campos do banco).
 *
 * 1) sugerirMapa(): olha os nomes das colunas e sugere o campo de cada uma;
 * 2) conferir(): aplica o mapa escolhido e confere cada linha com as MESMAS
 *    regras do formulário, separando o que entra do que é recusado;
 * 3) importar(): grava as linhas boas pela mesma ação do formulário
 *    (CriarFiliado), que cria a conta e manda o convite.
 *
 * LGPD: o relatório de recusas mostra só o número da linha e o motivo, nunca
 * nome, CPF ou e-mail.
 */

namespace App\Actions\Filiados;

use App\Http\Requests\Cadastros\EquipeRequest;
use App\Models\AreaAtuacao;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\Nivel;
use App\Models\User;
use App\Support\Documento;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Throwable;

class ImportarFiliados
{
    /**
     * Campos do banco que uma coluna pode alimentar, com o nome para a tela.
     * "ignorar" = a coluna não vai para lugar nenhum.
     */
    public const CAMPOS = [
        'ignorar' => '— Ignorar esta coluna —',
        'registro' => 'Número de registro',
        'nome' => 'Nome completo',
        'email' => 'E-mail',
        'cpf' => 'CPF',
        'nascimento' => 'Nascimento',
        'telefone' => 'Telefone',
        'municipio' => 'Município',
        'uf' => 'UF (estado)',
        'equipe' => 'Equipe (nome)',
        'nivel' => 'Nível (ex.: Instrutor Expert IV)',
        'areas' => 'Áreas de atuação (separadas por vírgula)',
    ];

    // Palavras que, no nome da coluna, indicam cada campo (para a sugestão).
    private const PISTAS = [
        'registro' => ['registro', 'numero de registro', 'matricula'],
        'nome' => ['nome', 'nome completo'],
        'email' => ['email', 'e-mail'],
        'cpf' => ['cpf'],
        'nascimento' => ['nascimento', 'data de nascimento', 'nasc'],
        'telefone' => ['telefone', 'celular', 'whatsapp', 'fone'],
        'municipio' => ['municipio', 'cidade'],
        'uf' => ['uf', 'estado'],
        'equipe' => ['equipe', 'academia', 'time'],
        'nivel' => ['nivel', 'graduacao'],
        'areas' => ['area de atuacao', 'areas de atuacao', 'area', 'areas'],
    ];

    /**
     * Nomes de equipes e áreas já carregados nesta importação: [modelo => [nome em minúsculas => id]].
     *
     * @var array<string, array<string, int>>
     */
    private array $nomesCarregados = [];

    public function __construct(private CriarFiliado $criar) {}

    /**
     * Sugere o campo de cada coluna pelo nome dela (sem acento e em minúsculas).
     *
     * @param  list<string>  $colunas  Ex.: ['Número de registro', 'Nome completo', 'CPF']
     * @return array<string, string> [coluna => campo]. Ex.: ['CPF' => 'cpf']; sem pista = 'ignorar'.
     */
    public function sugerirMapa(array $colunas): array
    {
        $mapa = [];
        foreach ($colunas as $coluna) {
            $normal = Str::lower(Str::ascii(trim($coluna)));
            $mapa[$coluna] = 'ignorar';
            foreach (self::PISTAS as $campo => $pistas) {
                if (in_array($normal, $pistas, true)) {
                    $mapa[$coluna] = $campo;
                    break;
                }
            }
        }

        return $mapa;
    }

    /**
     * Confere todas as linhas com o mapa escolhido.
     *
     * @param  list<array<string, string>>  $linhas  As linhas do CSV ([coluna => valor]).
     * @param  array<string, string>  $mapa  [coluna => campo], vindo da tela.
     * @return array{validas: list<array<string, mixed>>, recusadas: list<array{linha: int, motivos: list<string>}>}
     */
    public function conferir(array $linhas, array $mapa): array
    {
        $validas = [];
        $recusadas = [];
        // CPFs e e-mails já vistos NESTE arquivo (para pegar repetição dentro da própria planilha).
        $cpfsVistos = [];
        $emailsVistos = [];

        foreach ($linhas as $posicao => $linha) {
            // Linha 1 é o cabeçalho, então a primeira linha de dados é a 2 (como no Excel).
            $numero = $posicao + 2;
            [$dados, $motivos] = $this->montarLinha($linha, $mapa);

            $cpfHash = isset($dados['cpf']) ? Filiado::hashDoCpf($dados['cpf']) : null;
            if ($cpfHash !== null && isset($cpfsVistos[$cpfHash])) {
                $motivos[] = 'CPF repetido dentro da planilha.';
            }
            if (isset($dados['email']) && isset($emailsVistos[$dados['email']])) {
                $motivos[] = 'E-mail repetido dentro da planilha.';
            }

            if ($motivos !== []) {
                $recusadas[] = ['linha' => $numero, 'motivos' => $motivos];

                continue;
            }

            $cpfsVistos[(string) $cpfHash] = true;
            $emailsVistos[$dados['email']] = true;
            $validas[] = $dados;
        }

        return ['validas' => $validas, 'recusadas' => $recusadas];
    }

    /**
     * Grava as linhas válidas (cada uma cria conta + filiado + convite).
     * Uma linha que falhar não derruba as outras: ela vai para as recusadas.
     *
     * @param  list<array<string, mixed>>  $validas  O resultado de conferir()['validas'].
     * @return array{importados: int, falhas: int}
     */
    public function importar(array $validas): array
    {
        $importados = 0;
        $falhas = 0;

        foreach ($validas as $dados) {
            try {
                /** @var array{nome: string, email: string, cpf: string, nascimento: string, telefone?: string|null, municipio: string, uf: string, equipe_id?: int|null, nivel_id?: int|null, areas?: list<int>, registro?: string|null} $dados */
                $this->criar->executar($dados);
                $importados++;
            } catch (Throwable $erro) {
                // Ex.: alguém cadastrou o mesmo CPF pela tela entre a prévia e a confirmação.
                // Não para a importação; anota no log, sem dados pessoais (#58).
                logger()->error('IMPORTACAO_LINHA_FALHOU', ['excecao' => $erro::class]);
                $falhas++;
            }
        }

        return ['importados' => $importados, 'falhas' => $falhas];
    }

    /**
     * Transforma uma linha do CSV nos dados do filiado e lista o que está errado.
     *
     * @param  array<string, string>  $linha  [coluna => valor]
     * @param  array<string, string>  $mapa  [coluna => campo]
     * @return array{0: array<string, mixed>, 1: list<string>} [dados, motivos de recusa]
     */
    private function montarLinha(array $linha, array $mapa): array
    {
        // Junta os valores por campo (ex.: o valor da coluna "CPF" vai para 'cpf').
        $bruto = [];
        foreach ($mapa as $coluna => $campo) {
            if ($campo !== 'ignorar' && isset($linha[$coluna]) && $linha[$coluna] !== '') {
                $bruto[$campo] = $linha[$coluna];
            }
        }

        $dados = [];
        $motivos = [];

        // Obrigatórios.
        foreach (['nome', 'email', 'cpf', 'nascimento', 'municipio', 'uf'] as $obrigatorio) {
            if (! isset($bruto[$obrigatorio])) {
                $motivos[] = 'Falta '.Str::lower(self::CAMPOS[$obrigatorio]).'.';
            }
        }

        $dados['nome'] = $bruto['nome'] ?? '';
        $dados['municipio'] = $bruto['municipio'] ?? '';

        // E-mail: formato e conta já existente.
        $email = Str::lower($bruto['email'] ?? '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $motivos[] = 'E-mail inválido.';
        } elseif ($email !== '' && User::where('email', $email)->exists()) {
            $motivos[] = 'Já existe uma conta com esse e-mail.';
        }
        $dados['email'] = $email;

        // CPF: só números (a planilha às vezes perde o zero da frente: completa até 11).
        $cpf = str_pad(Documento::apenasDigitos($bruto['cpf'] ?? ''), 11, '0', STR_PAD_LEFT);
        if (isset($bruto['cpf']) && ! Documento::valido($cpf)) {
            $motivos[] = 'CPF inválido.';
        } elseif (isset($bruto['cpf']) && Filiado::where('cpf_hash', Filiado::hashDoCpf($cpf))->exists()) {
            $motivos[] = 'Já existe um filiado com esse CPF.';
        }
        $dados['cpf'] = $cpf;

        // Nascimento: aceita 20/05/1990 (planilha) e 1990-05-20.
        $nascimento = $this->converterData($bruto['nascimento'] ?? '');
        if (isset($bruto['nascimento']) && $nascimento === null) {
            $motivos[] = 'Data de nascimento inválida (use dd/mm/aaaa).';
        }
        $dados['nascimento'] = $nascimento ?? '';

        // Telefone: opcional, 10 ou 11 números.
        $telefone = Documento::apenasDigitos($bruto['telefone'] ?? '');
        if ($telefone !== '' && ! in_array(strlen($telefone), [10, 11], true)) {
            $motivos[] = 'Telefone com DDD: 10 ou 11 números.';
        }
        $dados['telefone'] = $telefone !== '' ? $telefone : null;

        // UF: uma das 27 siglas.
        $uf = Str::upper($bruto['uf'] ?? '');
        if ($uf !== '' && ! in_array($uf, EquipeRequest::UFS, true)) {
            $motivos[] = "UF \"{$uf}\" não existe.";
        }
        $dados['uf'] = $uf;

        // Equipe: pelo nome (sem diferenciar maiúsculas). Vazia = sem equipe.
        $dados['equipe_id'] = null;
        if (isset($bruto['equipe']) && $bruto['equipe'] !== '-') {
            $equipe = $this->idPorNome(Equipe::class, $bruto['equipe']);
            $equipe === null
                ? $motivos[] = "Equipe \"{$bruto['equipe']}\" não cadastrada."
                : $dados['equipe_id'] = $equipe;
        }

        // Nível: pelo nome montado (ex.: "Instrutor Expert IV").
        $dados['nivel_id'] = null;
        if (isset($bruto['nivel'])) {
            $nivel = Nivel::all()->first(fn (Nivel $n) => Str::lower($n->nome()) === Str::lower($bruto['nivel']));
            $nivel === null
                ? $motivos[] = "Nível \"{$bruto['nivel']}\" não existe (use, por ex., \"Instrutor Expert IV\")."
                : $dados['nivel_id'] = $nivel->id;
        }

        // Áreas: nomes separados por vírgula; as que não existirem são avisadas.
        $dados['areas'] = [];
        if (isset($bruto['areas'])) {
            foreach (array_filter(array_map('trim', explode(',', $bruto['areas']))) as $nomeArea) {
                $area = $this->idPorNome(AreaAtuacao::class, $nomeArea);
                $area === null
                    ? $motivos[] = "Área \"{$nomeArea}\" não cadastrada."
                    : $dados['areas'][] = $area;
            }
        }

        // Registro: se a planilha já traz o número, ele é mantido (a carteirinha não muda).
        $dados['registro'] = null;
        if (isset($bruto['registro'])) {
            $registro = Str::upper($bruto['registro']);
            if (preg_match('/^BR\d{8}[A-Z]{2}$/', $registro) !== 1) {
                $motivos[] = "Registro \"{$registro}\" fora do formato BR + 8 números + UF.";
            } elseif (Filiado::where('registro', $registro)->exists()) {
                $motivos[] = "Registro {$registro} já cadastrado.";
            } else {
                $dados['registro'] = $registro;
            }
        }

        return [$dados, $motivos];
    }

    /**
     * Acha o número de uma equipe ou área pelo nome, sem diferenciar maiúsculas
     * de minúsculas, INCLUSIVE com acento ("TÁTICOS" = "Táticos").
     * A comparação é feita no PHP (Str::lower entende acentos), e não no banco:
     * o LOWER do SQLite (computador) não passa "Á" para "á".
     * Os nomes são carregados uma vez só por importação (como a tabela de preços do PDV).
     *
     * @param  class-string<Equipe|AreaAtuacao>  $modelo  Equipe::class ou AreaAtuacao::class.
     * @param  string  $nome  O nome como veio da planilha. Ex.: 'ALFA COMBAT TREINAMENTOS TÁTICOS'
     * @return int|null O número encontrado, ou null se não existe.
     */
    private function idPorNome(string $modelo, string $nome): ?int
    {
        $this->nomesCarregados[$modelo] ??= $modelo::query()->pluck('id', 'nome')
            ->mapWithKeys(fn (int $id, string $nomeCadastrado) => [Str::lower(trim($nomeCadastrado)) => $id])
            ->all();

        return $this->nomesCarregados[$modelo][Str::lower(trim($nome))] ?? null;
    }

    /**
     * Converte a data da planilha para o formato do banco.
     *
     * @param  string  $valor  Ex.: '20/05/1990' ou '1990-05-20'
     * @return string|null Ex.: '1990-05-20'; null se não for uma data válida.
     */
    private function converterData(string $valor): ?string
    {
        // Já no formato do banco (aaaa-mm-dd): confere se é uma data de verdade.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1) {
            $data = Carbon::createFromFormat('Y-m-d', $valor);

            // "Conferência de volta" (ver abaixo) e nascimento no passado.
            return $data !== null && $data->format('Y-m-d') === $valor && $data->isPast() ? $valor : null;
        }

        // Formato da planilha (dd/mm/aaaa). Molde do Carbon: d = dia, m = mês,
        // Y = ano com 4 números, separados por barra.
        $molde = 'd/m/Y';

        // Fora do molde (ex.: "20-05-1990"): devolve null.
        if (! Carbon::hasFormat($valor, $molde)) {
            return null;
        }

        $data = Carbon::createFromFormat($molde, $valor);

        // "Conferência de volta": o Carbon aceita 31/02/1990 e transforma em
        // 03/03/1990 sem avisar. Escrevendo a data de novo no molde, ela precisa
        // ser IGUAL ao que veio; se não for, a data era impossível.
        if ($data === null || $data->format($molde) !== $valor) {
            return null;
        }

        // Nascimento no futuro também é recusado.
        return $data->isPast() ? $data->format('Y-m-d') : null;
    }
}
