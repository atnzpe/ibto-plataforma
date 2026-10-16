<?php

/*
 * CARGA DA BASE DO PILOTO (decisão do Gleyson, 16/10/2026).
 *
 * Cadastra os cursos REAIS dos cartazes do IBTO (com a data real; os que já
 * aconteceram ficam "Encerrado", como histórico; turma futura sem valor no
 * cartaz fica "Rascunho" até o IBTO pôr o preço), uma TURMA DE TESTE dos cinco
 * cursos principais (data futura, título começando com [TESTE], para os stakeholders se
 * inscreverem e pagarem no piloto), dois cursos online de teste (um gravado e
 * um ao vivo) e a trilha "Formação Operacional".
 *
 * - Pode rodar quantas vezes quiser: curso que já existe (pelo título) é pulado.
 * - Instrutores entram só como TEXTO (nome e especialidade): nenhuma conta é
 *   criada para quem não pediu acesso.
 * - As capas são os cartazes (já públicos no Instagram do IBTO), em WebP, na
 *   pasta database/seeders/piloto/capas.
 * - Antes da produção, as turmas [TESTE] são apagadas (ver README).
 *
 * No servidor (sem SSH): PILOTO_CARREGAR=true no .env; o Cron roda este comando
 * UMA vez pelo plataforma:pos-deploy e grava a marca storage/app/piloto-carregado.
 * Uso local: php artisan plataforma:carregar-piloto
 */

namespace App\Console\Commands;

use App\Enums\SituacaoCurso;
use App\Models\AreaAtuacao;
use App\Models\Curso;
use App\Models\CursoModulo;
use App\Models\Trilha;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CarregarPiloto extends Command
{
    // Marca que o título é de uma turma de teste (some antes da produção).
    public const PREFIXO_TESTE = '[TESTE] ';

    protected $signature = 'plataforma:carregar-piloto';

    protected $description = 'Cadastra os cursos reais dos cartazes, as turmas [TESTE] e a trilha do piloto (sem duplicar)';

    /**
     * Roda a carga inteira numa transação: ou entra tudo, ou nada.
     *
     * @return int 0 = deu certo.
     */
    public function handle(): int
    {
        DB::transaction(function (): void {
            // Os 5 cursos reais e a turma de teste de cada um.
            $turmasDeTeste = [];
            foreach ($this->cursosReais() as $dados) {
                $this->curso($dados, $dados['situacao']);

                // Só os cinco cursos principais ganham turma de teste (os outros são só histórico ou rascunho).
                if ($dados['teste'] ?? true) {
                    $turmasDeTeste[$dados['slug']] = $this->curso($this->turmaDeTeste($dados), SituacaoCurso::Publicado);
                }
            }

            // Os dois cursos online de teste (todas as modalidades no piloto).
            foreach ($this->cursosOnlineDeTeste() as $dados) {
                $this->curso($dados, SituacaoCurso::Publicado);
            }

            // A trilha, na ordem do mais básico ao mais completo (com as turmas de teste, que aceitam inscrição).
            $this->trilha($turmasDeTeste);
        });

        $this->info('Base do piloto carregada.');

        return self::SUCCESS;
    }

    /**
     * Os cursos REAIS, como estão nos cartazes do IBTO.
     *
     * @return list<array<string, mixed>>
     */
    private function cursosReais(): array
    {
        return [
            [
                'slug' => 'bastao-tatico',
                'titulo' => 'Seminário Bastão Tático',
                'descricao' => "Domine. Defenda. Proteja.\n\nFundamentos do bastão tático, defesa pessoal, técnicas de controle e imobilização, aplicações práticas para segurança e proteção pessoal e treinamento técnico e tático.",
                'data_inicio' => '2026-08-29', 'hora_inicio' => '18:00', 'hora_fim' => null,
                'local' => 'Sede da FIGAM: Rua Ângelo Belloni, 987, Rio Branco, Caxias do Sul-RS',
                'valor_centavos' => 15000, 'carga_horaria' => null,
                'material_incluso' => 'Bastão tático, Certificado de participação',
                'instrutor_nome' => 'Mestre Rodrigo dos Santos',
                'instrutor_especialidade' => 'Faixa preta em Krav Magá, Kapap e Kenjutsu',
                'area' => ['defesa', 'combate', 'bastão', 'bastao'],
                'situacao' => SituacaoCurso::Encerrado,
            ],
            [
                'slug' => 'combate-com-faca',
                'titulo' => 'Treinamento para Combate com Faca',
                'descricao' => 'Treinamento de combate e defesa com faca, com base no Kali filipino: empunhadura, ataques, defesas e desarmes em situação real.',
                'data_inicio' => '2026-10-10', 'hora_inicio' => '14:00', 'hora_fim' => '18:00',
                'local' => 'Recife-PE',
                'valor_centavos' => 25000, 'carga_horaria' => 4,
                'material_incluso' => 'Certificado, Camisa do evento',
                'instrutor_nome' => 'Marcelo',
                'instrutor_especialidade' => 'Especialista em Kali',
                'area' => ['faca', 'combate', 'defesa'],
                'situacao' => SituacaoCurso::Encerrado,
            ],
            [
                'slug' => 'combate-corpo-a-corpo',
                'titulo' => 'Treinamento para Combate Corpo a Corpo',
                'descricao' => 'Combate corpo a corpo para a realidade: postura, golpes, defesas, controle e imobilização, com a disciplina das artes marciais.',
                'data_inicio' => '2026-10-10', 'hora_inicio' => '09:00', 'hora_fim' => '13:00',
                'local' => 'Recife-PE',
                'valor_centavos' => 25000, 'carga_horaria' => 4,
                'material_incluso' => 'Certificado, Camisa do evento',
                'instrutor_nome' => 'Trinca',
                'instrutor_especialidade' => 'Especialista em artes marciais',
                'area' => ['corpo', 'combate', 'defesa'],
                'situacao' => SituacaoCurso::Encerrado,
            ],
            [
                'slug' => 'operador-arma-de-fogo',
                'titulo' => 'Treinamento para Operador de Arma de Fogo',
                'descricao' => "Mais que treinamento: preparação para a vida real.\n\n1. Segurança e conduta em estande\n2. Conhecimento do armamento e da munição\n3. Fundamentos do tiro\n4. Manejo operacional e solução de pane\n5. Prática do tiro e manutenção",
                'data_inicio' => '2026-10-11', 'hora_inicio' => '09:00', 'hora_fim' => '17:00',
                'local' => 'Recife-PE: Centro de Treinamento Tático de Pernambuco (CTTPE)',
                'valor_centavos' => 65000, 'carga_horaria' => 8,
                'material_incluso' => 'Certificado pelo ITB (Instituto de Tiro do Brasil), Certificado de conclusão, Camisa do evento, Munições, Estande de tiro',
                'instrutor_nome' => 'Caetano',
                'instrutor_especialidade' => 'Especialista em tiro',
                'area' => ['tiro', 'arma'],
                'situacao' => SituacaoCurso::Encerrado,
            ],
            [
                'slug' => 'sobrevivencia-urbana-n1',
                'titulo' => 'Curso Sobrevivência Urbana: Nível 1',
                'descricao' => "Torne-se preparado. Sobreviva.\n\nCombate corpo a corpo, combate e defesa com facas, sobrevivência urbana, arma curta e longa e preparação para emergências. Testes de resistência física e mental avaliam todo o conhecimento adquirido nas instruções.",
                'data_inicio' => null, 'hora_inicio' => null, 'hora_fim' => null,
                'local' => null,
                'valor_centavos' => 0, 'carga_horaria' => 50,
                'material_incluso' => 'Certificado',
                'instrutor_nome' => null, 'instrutor_especialidade' => null,
                'area' => ['sobreviv', 'combate'],
                // Sem data nem valor ainda: rascunho, até o IBTO definir a turma.
                'situacao' => SituacaoCurso::Rascunho,
            ],
            [
                'slug' => 'operacao-avancada',
                'titulo' => 'Curso de Operação Avançada',
                'descricao' => 'Turma realizada em 11/10/2026. O Comando Geral completa o conteúdo, o local e o valor das próximas turmas.',
                'data_inicio' => '2026-10-11', 'hora_inicio' => null, 'hora_fim' => null,
                'local' => null,
                'valor_centavos' => 0, 'carga_horaria' => null,
                'material_incluso' => 'Certificado',
                'instrutor_nome' => null, 'instrutor_especialidade' => null,
                'area' => ['tiro', 'arma', 'tát', 'tat'],
                'situacao' => SituacaoCurso::Encerrado,
                // Só histórico: sem turma de teste.
                'teste' => false,
            ],
            // Treinamento de Combate Real (Course Ragnar Ukraine): três turmas em Piracicaba-SP.
            [
                'slug' => 'combate-real-set',
                'capa' => 'combate-real-ragnar',
                'titulo' => 'Treinamento de Combate Real: turma de 12 e 13/09/2026',
                'descricao' => "Formação Ucrânia e Espanha (Course Ragnar Ukraine).\n\nO que você vai dominar: táticas avançadas, combate em ambiente urbano (CQB), patrulha e progressão.\n\nPara quem é: operadores, atiradores, airsofters e profissionais que buscam evolução real em campo.\n\nDisciplina. Foco. Resiliência. Vitória.",
                'data_inicio' => '2026-09-12', 'data_fim' => '2026-09-13', 'hora_inicio' => null, 'hora_fim' => null,
                'local' => 'Piracicaba-SP',
                'valor_centavos' => 0, 'carga_horaria' => null,
                'material_incluso' => 'Certificado',
                'instrutor_nome' => 'Course Ragnar Ukraine', 'instrutor_especialidade' => 'Formação Ucrânia e Espanha',
                'area' => ['tát', 'tat', 'tiro', 'arma'],
                'situacao' => SituacaoCurso::Encerrado,
                'teste' => false,
            ],
            [
                'slug' => 'combate-real-out',
                'capa' => 'combate-real-ragnar',
                'titulo' => 'Treinamento de Combate Real: turma de 10 e 11/10/2026',
                'descricao' => "Formação Ucrânia e Espanha (Course Ragnar Ukraine).\n\nO que você vai dominar: táticas avançadas, combate em ambiente urbano (CQB), patrulha e progressão.\n\nPara quem é: operadores, atiradores, airsofters e profissionais que buscam evolução real em campo.\n\nDisciplina. Foco. Resiliência. Vitória.",
                'data_inicio' => '2026-10-10', 'data_fim' => '2026-10-11', 'hora_inicio' => null, 'hora_fim' => null,
                'local' => 'Piracicaba-SP',
                'valor_centavos' => 0, 'carga_horaria' => null,
                'material_incluso' => 'Certificado',
                'instrutor_nome' => 'Course Ragnar Ukraine', 'instrutor_especialidade' => 'Formação Ucrânia e Espanha',
                'area' => ['tát', 'tat', 'tiro', 'arma'],
                'situacao' => SituacaoCurso::Encerrado,
                'teste' => false,
            ],
            [
                'slug' => 'combate-real-nov',
                'capa' => 'combate-real-ragnar',
                'titulo' => 'Treinamento de Combate Real: turma de 14 e 15/11/2026',
                'descricao' => "Formação Ucrânia e Espanha (Course Ragnar Ukraine).\n\nO que você vai dominar: táticas avançadas, combate em ambiente urbano (CQB), patrulha e progressão.\n\nPara quem é: operadores, atiradores, airsofters e profissionais que buscam evolução real em campo.\n\nDisciplina. Foco. Resiliência. Vitória.",
                'data_inicio' => '2026-11-14', 'data_fim' => '2026-11-15', 'hora_inicio' => null, 'hora_fim' => null,
                'local' => 'Piracicaba-SP',
                'valor_centavos' => 0, 'carga_horaria' => null,
                'material_incluso' => 'Certificado',
                'instrutor_nome' => 'Course Ragnar Ukraine', 'instrutor_especialidade' => 'Formação Ucrânia e Espanha',
                'area' => ['tát', 'tat', 'tiro', 'arma'],
                // Turma real e futura, mas o cartaz não traz o valor: rascunho até o IBTO pôr o preço e publicar.
                'situacao' => SituacaoCurso::Rascunho,
                'teste' => false,
            ],
        ];
    }

    /**
     * A turma de teste de um curso real: mesmo conteúdo, data futura e título [TESTE].
     *
     * @param  array<string, mixed>  $real  O curso real.
     * @return array<string, mixed>
     */
    private function turmaDeTeste(array $real): array
    {
        // Datas futuras espalhadas (20 a 60 dias a partir de hoje), para testar a agenda.
        $dias = ['combate-corpo-a-corpo' => 20, 'combate-com-faca' => 21, 'operador-arma-de-fogo' => 28, 'bastao-tatico' => 35, 'sobrevivencia-urbana-n1' => 60];

        return [
            ...$real,
            'titulo' => self::PREFIXO_TESTE.$real['titulo'],
            'data_inicio' => CarbonImmutable::today()->addDays($dias[$real['slug']])->toDateString(),
            'hora_inicio' => $real['hora_inicio'] ?? '08:00',
            'hora_fim' => $real['hora_fim'] ?? '17:00',
            'local' => $real['local'] ?? 'Recife-PE',
            // Sobrevivência Urbana ainda não tem valor: R$ 100 só para testar o Pix.
            'valor_centavos' => $real['valor_centavos'] > 0 ? $real['valor_centavos'] : 10000,
            'vagas' => 20,
            'descricao' => "Turma de TESTE do piloto (some antes da produção).\n\n".$real['descricao'],
        ];
    }

    /**
     * Os dois cursos online de teste: um gravado (certificado automático) e um ao vivo.
     *
     * @return list<array<string, mixed>>
     */
    private function cursosOnlineDeTeste(): array
    {
        return [
            [
                'slug' => 'online-gravado',
                'titulo' => self::PREFIXO_TESTE.'Comunicação Operacional (curso gravado)',
                'descricao' => "Turma de TESTE do piloto (some antes da produção).\n\nCurso gravado: assista às aulas no seu tempo. Ao marcar a última aula como assistida, o certificado sai sozinho.",
                'modalidade' => 'online',
                'valor_centavos' => 5000, 'carga_horaria' => 1,
                'material_incluso' => 'Certificado',
                'instrutor_nome' => 'Comando Geral do IBTO', 'instrutor_especialidade' => null,
                'area' => ['comunica', 'defesa'],
                'aulas' => [
                    ['titulo' => 'Aula 1: + ou ++? Confirmação no chat', 'conteudo' => '"+" é confirmação simples: entendido, de acordo, confirmado. "++" é confirmação reforçada: entendido com ênfase, plenamente de acordo, recebido e entendido.', 'duracao_minutos' => 10],
                    ['titulo' => 'Aula 2: Disciplina na comunicação da equipe', 'conteudo' => 'Mensagem curta, clara e com confirmação. Quem pergunta espera o "+"; quem recebe confirma assim que entende.', 'duracao_minutos' => 15],
                    ['titulo' => 'Aula 3: Revisão e próximos passos', 'conteudo' => 'Revise as duas aulas e marque esta como assistida para receber o certificado.', 'duracao_minutos' => 5],
                ],
            ],
            [
                'slug' => 'online-ao-vivo',
                'titulo' => self::PREFIXO_TESTE.'Encontro ao vivo: apresentação da plataforma',
                'descricao' => "Turma de TESTE do piloto (some antes da produção).\n\nEncontro online ao vivo. O botão \"Entrar na sala\" aparece 30 minutos antes do início para quem está confirmado.",
                'modalidade' => 'online',
                'valor_centavos' => 0, 'carga_horaria' => 1,
                'material_incluso' => 'Certificado de participação',
                'instrutor_nome' => 'Comando Geral do IBTO', 'instrutor_especialidade' => null,
                'data_inicio' => CarbonImmutable::today()->addDays(7)->toDateString(),
                'hora_inicio' => '19:00', 'hora_fim' => '20:00',
                'area' => ['comunica', 'defesa'],
            ],
        ];
    }

    /**
     * Cria o curso (se ainda não existe pelo título), com capa, área e o conteúdo.
     *
     * @param  array<string, mixed>  $dados  Os dados do curso (ver cursosReais()).
     * @param  SituacaoCurso  $situacao  Ex.: SituacaoCurso::Encerrado.
     * @return Curso O curso (novo ou o que já existia).
     */
    private function curso(array $dados, SituacaoCurso $situacao): Curso
    {
        // Já existe (rodou antes): devolve o mesmo, sem mexer em nada que o IBTO tenha ajustado.
        $existente = Curso::where('titulo', $dados['titulo'])->first();
        if ($existente !== null) {
            return $existente;
        }

        $curso = Curso::create([
            'titulo' => $dados['titulo'],
            'descricao' => $dados['descricao'],
            'area_atuacao_id' => $this->area($dados['area']),
            'modalidade' => $dados['modalidade'] ?? 'presencial',
            'acesso' => 'aberto',
            'valor_centavos' => $dados['valor_centavos'],
            'vagas' => $dados['vagas'] ?? null,
            'data_inicio' => $dados['data_inicio'] ?? null,
            'data_fim' => $dados['data_fim'] ?? null,
            'hora_inicio' => $dados['hora_inicio'] ?? null,
            'hora_fim' => $dados['hora_fim'] ?? null,
            'local' => $dados['local'] ?? null,
            'carga_horaria' => $dados['carga_horaria'],
            'material_incluso' => $dados['material_incluso'],
            'instrutor_nome' => $dados['instrutor_nome'],
            'instrutor_especialidade' => $dados['instrutor_especialidade'],
        ]);
        // Situação e capa não entram pelo formulário (só o IBTO muda): forceFill.
        // A capa: o cartaz do próprio curso, ou o cartaz compartilhado pelas turmas (ex.: Combate Real).
        $curso->forceFill(['situacao' => $situacao, 'imagem' => $this->capa($dados['capa'] ?? $dados['slug'])])->save();

        $this->conteudo($curso, $dados);

        return $curso;
    }

    /**
     * O conteúdo do curso: o encontro presencial (para a chamada), as aulas gravadas ou o encontro ao vivo.
     *
     * @param  Curso  $curso  O curso recém-criado.
     * @param  array<string, mixed>  $dados  Os dados (aulas, data e horário).
     */
    private function conteudo(Curso $curso, array $dados): void
    {
        $modulo = CursoModulo::create(['curso_id' => $curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);

        // Curso gravado: as aulas em texto (o vídeo o IBTO acrescenta depois, por link ou arquivo).
        if (isset($dados['aulas'])) {
            foreach ($dados['aulas'] as $ordem => $aula) {
                $modulo->aulas()->create([...$aula, 'ordem' => $ordem + 1, 'ativa' => true]);
            }

            return;
        }

        // Sem data: nada a criar (o IBTO monta os encontros quando definir a turma).
        if (($dados['data_inicio'] ?? null) === null) {
            return;
        }

        // Presencial ou ao vivo: um encontro por dia (é neles que o instrutor faz a chamada).
        // Ex.: turma de 14 e 15/11 → "Encontro 1" (14/11) e "Encontro 2" (15/11).
        $dia = CarbonImmutable::parse($dados['data_inicio']);
        $ultimo = CarbonImmutable::parse($dados['data_fim'] ?? $dados['data_inicio']);
        for ($ordem = 1; $dia->lessThanOrEqualTo($ultimo); $ordem++, $dia = $dia->addDay()) {
            $modulo->aulas()->create([
                'titulo' => $dia->equalTo($ultimo) && $ordem === 1 ? 'Encontro' : "Encontro {$ordem}",
                'ordem' => $ordem,
                'ativa' => true,
                'data' => $dia->toDateString(),
                'hora_inicio' => $dados['hora_inicio'] ?? null,
                'hora_fim' => $dados['hora_fim'] ?? null,
                'quem_aplica' => $dados['instrutor_nome'] ?? null,
            ]);
        }
    }

    /**
     * Procura a área de atuação pelo nome (as áreas são cadastradas pelo IBTO).
     *
     * @param  list<string>  $palavras  Pedaços do nome, na ordem de preferência. Ex.: ['tiro', 'arma'].
     * @return int|null O número da área, ou null se nenhuma bater.
     */
    private function area(array $palavras): ?int
    {
        foreach ($palavras as $palavra) {
            $id = AreaAtuacao::where('nome', 'like', '%'.$palavra.'%')->value('id');
            // Achou: usa essa. Senão, tenta a próxima palavra.
            if ($id !== null) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Copia o cartaz para o disco público (de onde a capa é entregue) e devolve o caminho.
     *
     * @param  string  $slug  Ex.: 'combate-com-faca'.
     * @return string|null Ex.: 'cursos/piloto-combate-com-faca.webp', ou null se não há cartaz.
     */
    private function capa(string $slug): ?string
    {
        $origem = database_path("seeders/piloto/capas/{$slug}.webp");
        // Cursos online de teste não têm cartaz: ficam sem capa.
        if (! is_file($origem)) {
            return null;
        }

        $destino = "cursos/piloto-{$slug}.webp";
        Storage::disk('public')->put($destino, (string) file_get_contents($origem));

        return $destino;
    }

    /**
     * Cria a trilha "Formação Operacional" com as turmas de teste, na ordem.
     *
     * @param  array<string, Curso>  $turmas  As turmas de teste, pelo slug.
     */
    private function trilha(array $turmas): void
    {
        $trilha = Trilha::firstOrCreate(
            ['nome' => 'Formação Operacional'],
            ['descricao' => 'Do combate corpo a corpo à sobrevivência urbana: a sequência recomendada pelo IBTO para formar o operador.', 'ativa' => true],
        );

        // Ordem: corpo a corpo → faca → arma de fogo → sobrevivência urbana.
        $ordem = ['combate-corpo-a-corpo', 'combate-com-faca', 'operador-arma-de-fogo', 'sobrevivencia-urbana-n1'];
        $ligacoes = [];
        foreach ($ordem as $posicao => $slug) {
            $ligacoes[$turmas[$slug]->id] = ['ordem' => $posicao + 1];
        }
        // syncWithoutDetaching: acrescenta sem tirar cursos que o IBTO já tenha posto na trilha.
        $trilha->cursos()->syncWithoutDetaching($ligacoes);
    }
}
