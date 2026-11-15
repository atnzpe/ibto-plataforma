<?php

/*
 * CONGRESSO NACIONAL IBTO NA HOMOLOGAÇÃO (pedido do Gleyson em 15/11/2026).
 *
 * Cadastra o "Congresso Nacional IBTO" (21/02/2027, Limeira-SP) como um curso
 * PRESENCIAL, para a equipe ver na homologação como a plataforma trata um evento
 * grande. A plataforma ainda não tem um tipo "evento": o congresso entra como
 * curso, e cada tema do cartaz vira um ENCONTRO do cronograma.
 *
 * O que vem do cartaz (real): nome, frase de chamada, data, cidade e os temas.
 * O que é FICTÍCIO (o Mestre Eduardo ainda pode não ter os detalhes): valor,
 * vagas, horários, local exato e quem aplica cada tema. A descrição avisa isso
 * para quem abrir a página.
 *
 * Segurança contra produção: o título começa com "[TESTE] " (sai na limpeza
 * antes da produção, #56) e a migration que chama este comando só roda na
 * homologação ou no computador local (ver a migration 2026_11_15_000001).
 *
 * Pode rodar quantas vezes quiser: se o congresso já existe (pelo título), não
 * mexe em nada (respeita o que a equipe ajustou na tela).
 * Uso: php artisan plataforma:carregar-congresso
 */

namespace App\Console\Commands;

use App\Enums\SituacaoCurso;
use App\Models\AreaAtuacao;
use App\Models\Curso;
use App\Models\CursoModulo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CarregarCongressoHomologacao extends Command
{
    // O nome do comando no terminal (php artisan plataforma:carregar-congresso).
    protected $signature = 'plataforma:carregar-congresso';

    // A frase que aparece na lista de comandos (php artisan list).
    protected $description = 'Cadastra o Congresso Nacional IBTO (21/02/2027) com dados de exemplo, para a homologação';

    // O título do congresso. Começa com "[TESTE] " (o mesmo marcador do piloto)
    // para sair na limpeza antes da produção e ninguém confundir com o real.
    public const TITULO = CarregarPiloto::PREFIXO_TESTE.'Congresso Nacional IBTO: 21/02/2027, Limeira-SP';

    // O dia do congresso (do cartaz). Formato do banco: ano-mês-dia.
    private const DIA = '2027-02-21';

    // Onde está a imagem do cartaz dentro do projeto (vai junto no deploy).
    private const CARTAZ = 'seeders/piloto/capas/congresso-nacional-2027.webp';

    /**
     * Cadastra o congresso e o cronograma numa transação: ou entra tudo, ou nada
     * (se der erro no meio, não fica um congresso sem cronograma).
     *
     * @return int 0 = deu certo.
     */
    public function handle(): int
    {
        // Já cadastrado: não mexe (a equipe pode ter mudado valor, vagas etc. na tela).
        if (Curso::where('titulo', self::TITULO)->exists()) {
            $this->info('O congresso já estava cadastrado: nada foi alterado.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            $curso = $this->congresso();
            $this->cronograma($curso);
        });

        $this->info('Congresso Nacional IBTO cadastrado (dados de exemplo).');

        return self::SUCCESS;
    }

    /**
     * Cria o congresso como curso presencial PUBLICADO (aparece na vitrine da
     * homologação para a equipe ver e testar a inscrição).
     *
     * @return Curso O congresso recém-criado.
     */
    private function congresso(): Curso
    {
        $curso = Curso::create([
            'titulo' => self::TITULO,
            // A frase do cartaz, logo abaixo do nome.
            'chamada' => 'Integração · Capacitação · Atualização Operacional',
            // O texto da página. O primeiro parágrafo avisa o que é exemplo.
            'descricao' => "ATENÇÃO: página de TESTE. Valor, vagas, horários, local exato e instrutores são EXEMPLOS e serão trocados pelos dados oficiais do IBTO.\n\n"
                ."Um encontro para profissionais que valorizam conhecimento aplicado, experiência real e preparo operacional.\n\n"
                ."Temas do congresso:\n- Treinamento operacional\n- Ações táticas\n- Atualização técnica\n- Tiro com munição real\n- APH tático\n"
                ."- Defesa pessoal e combate corpo a corpo\n- Drones e tecnologia aplicada\n- Sobrevivência e ambiente operacional\n"
                ."- Painéis técnicos\n- Demonstrações práticas\n- Instrutores convidados",
            // A área de atuação mais parecida que existir no banco (tática ou tiro); se não achar, fica vazia.
            'area_atuacao_id' => $this->area(['tát', 'tat', 'operac', 'tiro']),
            'modalidade' => 'presencial',
            // Aberto ao público (filiados e não filiados), como sugere o cartaz.
            'acesso' => 'aberto',
            // EXEMPLO: R$ 250,00 (o banco guarda em centavos, sem vírgula).
            'valor_centavos' => 25000,
            // EXEMPLO: 200 vagas.
            'vagas' => 200,
            // Um dia só: começa e termina em 21/02/2027.
            'data_inicio' => self::DIA,
            'data_fim' => self::DIA,
            // EXEMPLO: das 8h às 18h.
            'hora_inicio' => '08:00',
            'hora_fim' => '18:00',
            // A cidade é do cartaz; o endereço exato ainda não foi divulgado.
            'local' => 'Limeira - SP (endereço a confirmar)',
            // EXEMPLO: 10 horas (das 8h às 18h).
            'carga_horaria' => 10,
            // EXEMPLO: o que o inscrito recebe (cada item vira um selo na tela).
            'material_incluso' => 'Certificado, Credencial do evento',
            // O cartaz não diz quem coordena: fica genérico até a confirmação.
            'instrutor_nome' => 'Instrutores convidados do IBTO',
            'instrutor_especialidade' => 'Equipe a confirmar',
        ]);

        // A capa: o cartaz já divulgado pelo IBTO, copiado para a pasta pública de imagens.
        $arquivo = database_path(self::CARTAZ);
        $capa = null;
        // Só copia se o arquivo existe; se não existir, o curso fica sem capa (não dá erro).
        if (is_file($arquivo)) {
            $capa = 'cursos/piloto-congresso-nacional-2027.webp';
            Storage::disk('public')->put($capa, (string) file_get_contents($arquivo));
        }

        // Situação e imagem ficam fora do "create" (o Model protege esses campos),
        // por isso o forceFill: PUBLICADO para aparecer na vitrine da homologação.
        $curso->forceFill(['situacao' => SituacaoCurso::Publicado, 'imagem' => $capa])->save();

        return $curso;
    }

    /**
     * O cronograma: um encontro por tema do cartaz, todos no dia 21/02/2027.
     * Os HORÁRIOS são exemplo. A plataforma ainda não tem trilhas em paralelo,
     * então os temas ficam um depois do outro.
     *
     * @param  Curso  $curso  O congresso que acabou de ser criado.
     */
    private function cronograma(Curso $curso): void
    {
        // Um módulo só, com todos os encontros do dia.
        $modulo = CursoModulo::create(['curso_id' => $curso->id, 'titulo' => 'Programação do dia', 'ordem' => 1, 'ativo' => true]);

        // [tema, início, fim]. Horários de EXEMPLO; almoço das 12h30 às 13h30.
        $temas = [
            ['Abertura e credenciamento', '08:00', '08:30'],
            ['Treinamento operacional', '08:30', '09:30'],
            ['Ações táticas', '09:30', '10:30'],
            ['Atualização técnica', '10:30', '11:30'],
            ['APH tático', '11:30', '12:30'],
            ['Defesa pessoal e combate corpo a corpo', '13:30', '14:30'],
            ['Drones e tecnologia aplicada', '14:30', '15:30'],
            ['Sobrevivência e ambiente operacional', '15:30', '16:30'],
            ['Tiro com munição real (demonstração)', '16:30', '17:30'],
            ['Painel técnico e encerramento', '17:30', '18:00'],
        ];

        // Cria cada encontro na ordem da lista (ordem 1, 2, 3...).
        foreach ($temas as $posicao => [$tema, $inicio, $fim]) {
            $modulo->aulas()->create([
                'titulo' => $tema,
                'ordem' => $posicao + 1,
                'ativa' => true,
                'data' => self::DIA,
                'hora_inicio' => $inicio,
                'hora_fim' => $fim,
                // Quem aplica ainda não foi definido: o texto deixa isso claro na tela.
                'quem_aplica' => 'A confirmar (exemplo)',
            ]);
        }
    }

    /**
     * Procura a área de atuação pelo nome, tentando cada palavra na ordem.
     *
     * @param  list<string>  $palavras  Pedaços do nome. Ex.: ['tát', 'tiro'].
     * @return int|null O número (id) da primeira área encontrada, ou null se nenhuma servir.
     */
    private function area(array $palavras): ?int
    {
        foreach ($palavras as $palavra) {
            $id = AreaAtuacao::where('nome', 'like', '%'.$palavra.'%')->value('id');
            // Achou: usa essa; senão, tenta a próxima palavra.
            if ($id !== null) {
                return (int) $id;
            }
        }

        // Nenhuma área parecida: o curso fica sem área (o campo aceita vazio).
        return null;
    }
}
