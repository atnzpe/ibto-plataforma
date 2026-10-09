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

use App\Enums\CategoriaFiliado;
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
        'categoria' => 'Categoria (função: Aluno, Instrutor...)',
        'nivel' => 'Nível (ex.: Instrutor Expert IV)',
        'abrangencia' => 'Abrangência (Nacional ou Internacional)',
        'areas' => 'Áreas de atuação (separadas por vírgula)',
    ];

    // Campos cujos valores são escolhas de um cadastro e podem ser RELACIONADOS
    // na etapa "relacionar os valores" (ex.: "Expert Nível 3" → Instrutor Expert III).
    public const CAMPOS_RELACIONAVEIS = ['equipe', 'categoria', 'nivel', 'areas'];

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
        'categoria' => ['categoria', 'funcao'],
        'nivel' => ['nivel', 'graduacao'],
        'abrangencia' => ['abrangencia'],
        'areas' => ['area de atuacao', 'areas de atuacao', 'area', 'areas'],
    ];

    /**
     * Nomes de equipes e áreas já carregados nesta importação: [modelo => [nome em minúsculas => id]].
     *
     * @var array<string, array<string, int>>
     */
    private array $nomesCarregados = [];

    /**
     * Escolhas da etapa "relacionar os valores" da importação atual (ver conferir()).
     *
     * @var array<string, array<string, string>>
     */
    private array $relacoes = [];

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
     * Etapa "relacionar os valores": acha, nas colunas de equipe, nível e áreas,
     * os valores que NÃO batem com o cadastro, cada um uma vez só, com uma sugestão.
     *
     * @param  list<array<string, string>>  $linhas  As linhas do CSV.
     * @param  array<string, string>  $mapa  [coluna => campo].
     * @return array{valores: list<array{campo: string, valor: string, sugestao: string|null}>, opcoes: array<string, list<array{valor: string, rotulo: string}>>}
     */
    public function valoresParaRelacionar(array $linhas, array $mapa): array
    {
        $opcoes = $this->opcoesDeRelacao();
        $achados = [];

        foreach ($linhas as $linha) {
            foreach ($mapa as $coluna => $campo) {
                if (! in_array($campo, self::CAMPOS_RELACIONAVEIS, true) || ($linha[$coluna] ?? '') === '') {
                    continue;
                }

                // Áreas vêm várias numa célula, separadas por vírgula.
                $valores = $campo === 'areas' ? array_map('trim', explode(',', $linha[$coluna])) : [$linha[$coluna]];

                foreach (array_filter($valores, fn (string $v) => $v !== '' && $v !== '-') as $valor) {
                    // Já bate com o cadastro (sem diferenciar maiúsculas): não precisa relacionar.
                    if ($this->idDireto($campo, $valor) !== null) {
                        continue;
                    }
                    // Cada valor diferente aparece uma vez só (pela "chave": sem acento e minúsculo).
                    $achados[$campo.'|'.$this->chave($valor)] ??= [
                        'campo' => $campo,
                        'valor' => $valor,
                        'sugestao' => $this->sugerir($valor, $opcoes[$campo]),
                    ];
                }
            }
        }

        return ['valores' => array_values($achados), 'opcoes' => $opcoes];
    }

    /**
     * Confere todas as linhas com o mapa escolhido.
     *
     * @param  list<array<string, string>>  $linhas  As linhas do CSV ([coluna => valor]).
     * @param  array<string, string>  $mapa  [coluna => campo], vindo da tela.
     * @param  array<string, array<string, string>>  $relacoes  Escolhas da etapa "relacionar os valores":
     *                                                          [campo => [chave do valor => escolha]]. Ex.: ['nivel' => ['expert nivel 3' => 'nivel:12']]
     * @return array{validas: list<array<string, mixed>>, recusadas: list<array{linha: int, motivos: list<string>}>}
     */
    public function conferir(array $linhas, array $mapa, array $relacoes = []): array
    {
        $this->relacoes = $relacoes;

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

        // Abrangência numa coluna própria (planilhas futuras): nacional ou internacional.
        $dados['abrangencia'] = null;
        if (isset($bruto['abrangencia'])) {
            $abrangencia = $this->chave($bruto['abrangencia']);
            in_array($abrangencia, ['nacional', 'internacional'], true)
                ? $dados['abrangencia'] = $abrangencia
                : $motivos[] = "Abrangência \"{$bruto['abrangencia']}\" inválida (use Nacional ou Internacional).";
        }

        // Equipe: pelo nome, ou pela escolha da etapa "relacionar os valores". "-" = sem equipe.
        $dados['equipe_id'] = null;
        if (isset($bruto['equipe']) && $bruto['equipe'] !== '-') {
            $escolha = $this->escolha('equipe', $bruto['equipe']);
            match (true) {
                $escolha === 'vazio' => null,
                $escolha === 'recusar' => $motivos[] = "Equipe \"{$bruto['equipe']}\" recusada na relação dos valores.",
                str_starts_with($escolha, 'equipe:') => $dados['equipe_id'] = (int) substr($escolha, 7),
                default => $motivos[] = "Equipe \"{$bruto['equipe']}\" não cadastrada.",
            };
        }

        // Categoria (função): pelo nome (ex.: "INSTRUTOR EXPERT"), ou pela escolha da
        // relação (ex.: "PRATICANTE" → Aluno). Vazia = sem categoria.
        $dados['categoria'] = null;
        if (isset($bruto['categoria'])) {
            $escolha = $this->escolha('categoria', $bruto['categoria']);
            match (true) {
                $escolha === 'vazio' => null,
                $escolha === 'recusar' => $motivos[] = "Categoria \"{$bruto['categoria']}\" recusada na relação dos valores.",
                str_starts_with($escolha, 'categoria:') => $dados['categoria'] = substr($escolha, 10),
                default => $motivos[] = "Categoria \"{$bruto['categoria']}\" não existe (relacione na etapa dos valores).",
            };
        }

        // Nível: pelo nome montado (ex.: "Instrutor Expert IV"), ou pela escolha da
        // relação. A escolha pode ser uma ABRANGÊNCIA (o "Nacional" que a planilha
        // do Canva punha na coluna Nível): aí a linha entra sem nível.
        $dados['nivel_id'] = null;
        if (isset($bruto['nivel'])) {
            $escolha = $this->escolha('nivel', $bruto['nivel']);
            match (true) {
                $escolha === 'vazio' => null,
                $escolha === 'recusar' => $motivos[] = "Nível \"{$bruto['nivel']}\" recusado na relação dos valores.",
                str_starts_with($escolha, 'nivel:') => $dados['nivel_id'] = (int) substr($escolha, 6),
                str_starts_with($escolha, 'abrangencia:') => $dados['abrangencia'] = substr($escolha, 12),
                default => $motivos[] = "Nível \"{$bruto['nivel']}\" não existe (relacione na etapa dos valores).",
            };
        }

        // Áreas: nomes separados por vírgula; cada um pelo nome ou pela escolha da relação.
        $dados['areas'] = [];
        if (isset($bruto['areas'])) {
            foreach (array_filter(array_map('trim', explode(',', $bruto['areas']))) as $nomeArea) {
                $escolha = $this->escolha('areas', $nomeArea);
                match (true) {
                    $escolha === 'ignorar' => null,
                    $escolha === 'recusar' => $motivos[] = "Área \"{$nomeArea}\" recusada na relação dos valores.",
                    str_starts_with($escolha, 'area:') => $dados['areas'][] = (int) substr($escolha, 5),
                    default => $motivos[] = "Área \"{$nomeArea}\" não cadastrada.",
                };
            }
            // A mesma área escolhida duas vezes (ex.: "Kapap" e "KAPAP") entra uma vez só.
            $dados['areas'] = array_values(array_unique($dados['areas']));
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
     * A escolha valendo para um valor: a da etapa "relacionar os valores", se houver;
     * senão, o cadastro com o mesmo nome; senão, '' (não encontrado).
     *
     * @param  string  $campo  'equipe', 'categoria', 'nivel' ou 'areas'.
     * @param  string  $valor  Como veio da planilha. Ex.: 'Expert Nível 3'
     * @return string Ex.: 'nivel:12', 'abrangencia:nacional', 'recusar', 'vazio', 'ignorar' ou ''.
     */
    private function escolha(string $campo, string $valor): string
    {
        $relacionada = $this->relacoes[$campo][$this->chave($valor)] ?? null;
        if (is_string($relacionada) && $relacionada !== '') {
            return $relacionada;
        }

        $id = $this->idDireto($campo, $valor);
        $prefixo = ['equipe' => 'equipe:', 'categoria' => 'categoria:', 'nivel' => 'nivel:', 'areas' => 'area:'][$campo];

        return $id !== null ? $prefixo.$id : '';
    }

    /**
     * O item do cadastro com EXATAMENTE este nome (sem diferenciar maiúsculas e acentos).
     *
     * @param  string  $campo  'equipe', 'categoria', 'nivel' ou 'areas'.
     * @return int|string|null O número (equipe, nível, área) ou o valor da categoria
     *                         (ex.: 'instrutor_expert'); null se não existe.
     */
    private function idDireto(string $campo, string $valor): int|string|null
    {
        return match ($campo) {
            'equipe' => $this->idPorNome(Equipe::class, $valor),
            'areas' => $this->idPorNome(AreaAtuacao::class, $valor),
            'nivel' => Nivel::all()->first(fn (Nivel $n) => $this->chave($n->nome()) === $this->chave($valor))?->id,
            // "INSTRUTOR EXPERT" bate com o nome "Instrutor Expert" da categoria.
            'categoria' => collect(CategoriaFiliado::cases())
                ->first(fn (CategoriaFiliado $c) => $this->chave($c->rotulo()) === $this->chave($valor))?->value,
            default => null,
        };
    }

    /**
     * Opções de cada campo na etapa "relacionar os valores".
     *
     * @return array<string, list<array{valor: string, rotulo: string}>>
     */
    private function opcoesDeRelacao(): array
    {
        $equipes = Equipe::query()->orderBy('nome')->get()
            ->map(fn (Equipe $e) => ['valor' => "equipe:{$e->id}", 'rotulo' => $e->nome])->all();
        $niveis = Nivel::emOrdem(Nivel::all())
            ->map(fn (Nivel $n) => ['valor' => "nivel:{$n->id}", 'rotulo' => $n->nome()])->all();
        $areas = AreaAtuacao::query()->orderBy('nome')->get()
            ->map(fn (AreaAtuacao $a) => ['valor' => "area:{$a->id}", 'rotulo' => $a->nome])->all();
        $categorias = array_map(
            fn (CategoriaFiliado $c) => ['valor' => "categoria:{$c->value}", 'rotulo' => $c->rotulo()],
            CategoriaFiliado::cases(),
        );

        return [
            'equipe' => [...$equipes, ['valor' => 'vazio', 'rotulo' => '(sem equipe)'], ['valor' => 'recusar', 'rotulo' => 'Recusar a linha']],
            'categoria' => [...$categorias, ['valor' => 'vazio', 'rotulo' => '(sem categoria)'], ['valor' => 'recusar', 'rotulo' => 'Recusar a linha']],
            'nivel' => [
                ...$niveis,
                ['valor' => 'abrangencia:nacional', 'rotulo' => 'Abrangência: Nacional (sem nível)'],
                ['valor' => 'abrangencia:internacional', 'rotulo' => 'Abrangência: Internacional (sem nível)'],
                ['valor' => 'vazio', 'rotulo' => '(sem nível)'],
                ['valor' => 'recusar', 'rotulo' => 'Recusar a linha'],
            ],
            'areas' => [...$areas, ['valor' => 'ignorar', 'rotulo' => 'Ignorar só esta área'], ['valor' => 'recusar', 'rotulo' => 'Recusar a linha']],
        ];
    }

    /**
     * Sugere a opção mais parecida com o valor da planilha.
     *
     * @param  string  $valor  Ex.: 'Expert Nível 3'
     * @param  list<array{valor: string, rotulo: string}>  $opcoes
     * @return string|null O "valor" da opção sugerida (ex.: 'nivel:12'), ou null se nenhuma parece.
     */
    private function sugerir(string $valor, array $opcoes): ?string
    {
        $procurado = $this->normalizarParaComparar($valor);

        // "Nacional" / "Internacional" na coluna Nível: é abrangência.
        if (in_array($procurado, ['nacional', 'internacional'], true)) {
            return "abrangencia:{$procurado}";
        }

        $melhor = null;
        $maiorParecenca = 0.0;

        foreach ($opcoes as $opcao) {
            $candidato = $this->normalizarParaComparar($opcao['rotulo']);

            // Termina igual (ex.: "expert iii" no fim de "instrutor expert iii"): sugere direto.
            if ($procurado !== '' && str_ends_with($candidato, ' '.$procurado)) {
                return $opcao['valor'];
            }

            // Quanto os dois nomes se parecem, de 0 a 100% ("Facas" × "Faca" ≈ 97%).
            similar_text($procurado, $candidato, $parecenca);
            if ($parecenca > $maiorParecenca) {
                $maiorParecenca = $parecenca;
                $melhor = $opcao['valor'];
            }
        }

        // Sugere a partir de 80% de parecença (Gleyson, 08/10/2026): "Kraaav Maga"
        // (90%) e "Facas" (97%) ganham sugestão; "Operador de Drones" (74%) fica para
        // a pessoa escolher na tela. Abaixo disso, sem sugestão (evita besteira).
        return $maiorParecenca >= 80 ? $melhor : null;
    }

    /**
     * "Chave" de um texto: sem acento, minúsculas e sem espaços nas pontas.
     *
     * @return string Ex.: 'Expert Nível 3' vira 'expert nivel 3'
     */
    private function chave(string $texto): string
    {
        return Str::lower(Str::ascii(trim($texto)));
    }

    /**
     * Texto pronto para comparar nomes de nível: tira a palavra "nível" e troca
     * o número do grau pelo algarismo romano.
     *
     * @return string Ex.: 'Expert Nível 3' vira 'expert iii'
     */
    private function normalizarParaComparar(string $texto): string
    {
        $texto = preg_replace('/\bnivel\b/', '', $this->chave($texto)) ?? '';
        $texto = strtr($texto, ['1' => 'i', '2' => 'ii', '3' => 'iii', '4' => 'iv', '5' => 'v']);

        // Junta espaços repetidos (sobram onde a palavra "nível" saiu).
        return trim((string) preg_replace('/\s+/', ' ', $texto));
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
