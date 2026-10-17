<?php

/*
 * CONTEÚDO EXTRA DO PILOTO (pedido do Gleyson em 17/10/2026): o Curso Tático
 * Avançado do Ragnar (23 e 24/01/2027) e posts e álbuns de VÁRIOS TIPOS no blog
 * e nos eventos, para a equipe do piloto testar cenários diferentes:
 * - posts: notícia publicada, evento com pessoas (autorizado), agendado para
 *   daqui a 7 dias, rascunho, em revisão e arquivado;
 * - álbuns: publicado (com fotos descritas), rascunho com uma foto SEM descrição
 *   (não deixa publicar), com menores SEM a autorização dos responsáveis (não
 *   deixa publicar) e arquivado.
 * Tudo de teste começa com "[TESTE] " e sai antes da produção. As fotos são
 * imagens GERADAS aqui (fundo escuro com o emblema e um número): nenhuma pessoa real.
 * Pode rodar quantas vezes quiser: o que já existe (pelo título) é pulado.
 * No servidor roda sozinho pela migration 2026_10_17_000011 (sem SSH).
 */

namespace App\Console\Commands;

use App\Enums\CategoriaPost;
use App\Enums\SituacaoAlbum;
use App\Enums\SituacaoCurso;
use App\Enums\SituacaoPost;
use App\Models\AreaAtuacao;
use App\Models\Curso;
use App\Models\CursoModulo;
use App\Models\GaleriaAlbum;
use App\Models\GaleriaFoto;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CarregarConteudoPiloto extends Command
{
    protected $signature = 'plataforma:carregar-conteudo-piloto';

    protected $description = 'Cadastra o Curso Tático Avançado (Ragnar) e posts e álbuns de teste de vários tipos (sem duplicar)';

    /** O texto que marca o conteúdo de teste (sai antes da produção). */
    private const TESTE = CarregarPiloto::PREFIXO_TESTE;

    /**
     * Roda tudo numa transação: ou entra tudo, ou nada.
     *
     * @return int 0 = deu certo.
     */
    public function handle(): int
    {
        DB::transaction(function (): void {
            $curso = $this->cursoTaticoAvancado();
            $postEvento = $this->posts($curso);
            $this->albuns($postEvento);
        });

        $this->info('Conteúdo extra do piloto carregado.');

        return self::SUCCESS;
    }

    /**
     * O Curso Tático Avançado (Course Ragnar Ukraine), do cartaz de 17/10/2026.
     * O cartaz não traz valor nem local: fica RASCUNHO até o IBTO pôr o preço,
     * o local e as vagas e publicar. A capa é o próprio cartaz.
     *
     * @return Curso O curso (novo ou o que já existia).
     */
    private function cursoTaticoAvancado(): Curso
    {
        $titulo = 'Curso Tático Avançado (Course Ragnar Ukraine): 23 e 24/01/2027';
        $existente = Curso::where('titulo', $titulo)->first();
        // Já existe: não mexe no que o IBTO ajustou.
        if ($existente !== null) {
            return $existente;
        }

        $curso = Curso::create([
            'titulo' => $titulo,
            'chamada' => 'Técnica · Disciplina · Sobrevivência · Missão. Evolua com quem vive o real.',
            'descricao' => "Instrutor com 1 ano de experiência de combate, em ativa na Ucrânia, com formação no Exército Ucraniano e no Exército Espanhol.\n\n"
                ."Conteúdo do curso:\n- CQB (combate em ambiente urbano)\n- Mata (patrulha e movimentação)\n- Reconhecimento\n- Infiltração\n- Progressão em ambiente simulado\n- Franqueamento\n- Simulação de emboscada\n- EOD (explosivos e contramedidas)\n\n"
                ."Dois dias de duração. Vagas limitadas.\n\nInscrições e dúvidas pelo grupo do WhatsApp: https://chat.whatsapp.com/EEU0gfqvVXA1CuCJiINWVj",
            'area_atuacao_id' => $this->area(['tát', 'tat', 'tiro', 'arma']),
            'modalidade' => 'presencial',
            'acesso' => 'aberto',
            'valor_centavos' => 0,
            'data_inicio' => '2027-01-23',
            'data_fim' => '2027-01-24',
            'material_incluso' => 'Certificado',
            'instrutor_nome' => 'Course Ragnar Ukraine',
            'instrutor_especialidade' => 'Formação Ucrânia e Espanha',
        ]);
        // A capa: o cartaz do curso (já divulgado pelo IBTO), copiado para o disco público.
        $cartaz = database_path('seeders/piloto/capas/tatico-avancado-ragnar.webp');
        $capa = null;
        if (is_file($cartaz)) {
            $capa = 'cursos/piloto-tatico-avancado-ragnar.webp';
            Storage::disk('public')->put($capa, (string) file_get_contents($cartaz));
        }
        $curso->forceFill(['situacao' => SituacaoCurso::Rascunho, 'imagem' => $capa])->save();

        // Um encontro por dia (é neles que o instrutor faz a chamada).
        $modulo = CursoModulo::create(['curso_id' => $curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
        foreach (['2027-01-23', '2027-01-24'] as $ordem => $dia) {
            $modulo->aulas()->create(['titulo' => 'Encontro '.($ordem + 1), 'ordem' => $ordem + 1, 'ativa' => true, 'data' => $dia, 'quem_aplica' => 'Course Ragnar Ukraine']);
        }

        return $curso;
    }

    /**
     * Os posts de teste, um de cada situação.
     *
     * @param  Curso  $curso  O curso ligado à notícia.
     * @return Post O post do evento (o álbum publicado se liga a ele).
     */
    private function posts(Curso $curso): Post
    {
        $hoje = CarbonImmutable::now();
        // [título, categoria, situação, publicar_em, tem pessoas?, curso?, resumo]
        $lista = [
            ['Inscrições abertas: Curso Tático Avançado em janeiro', CategoriaPost::Noticia, SituacaoPost::Publicado, $hoje->subDay(), false, $curso->id, 'Dois dias de CQB, patrulha, infiltração e EOD com o Course Ragnar Ukraine. Vagas limitadas.'],
            ['Relato do encontro de instrutores', CategoriaPost::Evento, SituacaoPost::Publicado, $hoje->subDays(3), true, null, 'Como foi o encontro de instrutores da rede, com fotos no álbum do evento.'],
            ['Post agendado para a semana que vem', CategoriaPost::Noticia, SituacaoPost::Publicado, $hoje->addDays(7), false, null, 'Este post só aparece no site daqui a 7 dias (teste do agendamento).'],
            ['Rascunho de notícia', CategoriaPost::Noticia, SituacaoPost::Rascunho, null, false, null, 'Rascunho: não aparece para o público.'],
            ['Notícia aguardando revisão', CategoriaPost::Noticia, SituacaoPost::EmRevisao, null, false, null, 'Em revisão: espera a aprovação de quem publica.'],
            ['Evento antigo arquivado', CategoriaPost::Evento, SituacaoPost::Arquivado, $hoje->subMonths(2), false, null, 'Arquivado: saiu do site, mas continua guardado.'],
        ];

        $evento = null;
        foreach ($lista as $i => [$titulo, $categoria, $situacao, $publicarEm, $pessoas, $cursoId, $resumo]) {
            $post = Post::firstOrNew(['titulo' => self::TESTE.$titulo]);
            if (! $post->exists) {
                $post->fill([
                    'categoria' => $categoria,
                    'resumo' => $resumo,
                    'texto' => $resumo."\n\nTexto de exemplo para o teste do piloto. Este conteúdo é apagado antes da produção.",
                    'capa_descricao' => 'Imagem de exemplo com o emblema do IBTO',
                    'credito' => 'IBTO (imagem de exemplo)',
                    'curso_id' => $cursoId,
                    // Com pessoas: marcado como autorizado (o termo de exemplo), para poder publicar.
                    'tem_pessoas' => $pessoas,
                    'autorizacao_imagem' => $pessoas,
                    'autorizacao_origem' => $pessoas ? 'Termo de autorização (exemplo do piloto)' : null,
                ]);
                $post->forceFill([
                    'situacao' => $situacao,
                    'publicar_em' => $publicarEm,
                    'capa' => $this->imagemDeExemplo('blog', 'post-'.($i + 1), 'Post '.($i + 1)),
                ])->save();
            }
            // O segundo da lista (o relato do evento) é o que ganha o álbum.
            if ($i === 1) {
                $evento = $post;
            }
        }

        /** @var Post $evento */
        return $evento;
    }

    /**
     * Os álbuns de teste: publicado, rascunho com foto sem descrição, com menores sem autorização e arquivado.
     *
     * @param  Post  $postEvento  O post do evento (ligado ao álbum publicado).
     */
    private function albuns(Post $postEvento): void
    {
        // Cada álbum de teste: título, situação, nº de fotos, se a última fica SEM descrição,
        // se tem menores e o post ligado.
        /** @var list<array{titulo: string, situacao: SituacaoAlbum, fotos: int, sem_descricao: bool, menores: bool, post: int|null}> $lista */
        $lista = [
            ['titulo' => 'Encontro de instrutores', 'situacao' => SituacaoAlbum::Publicado, 'fotos' => 4, 'sem_descricao' => false, 'menores' => false, 'post' => $postEvento->id],
            ['titulo' => 'Treino de tiro (rascunho, falta descrever uma foto)', 'situacao' => SituacaoAlbum::Rascunho, 'fotos' => 3, 'sem_descricao' => true, 'menores' => false, 'post' => null],
            ['titulo' => 'Formatura com familiares (tem menores, falta autorização dos responsáveis)', 'situacao' => SituacaoAlbum::Rascunho, 'fotos' => 2, 'sem_descricao' => false, 'menores' => true, 'post' => null],
            ['titulo' => 'Evento antigo (arquivado)', 'situacao' => SituacaoAlbum::Arquivado, 'fotos' => 2, 'sem_descricao' => false, 'menores' => false, 'post' => null],
        ];

        foreach ($lista as $a => $item) {
            $titulo = $item['titulo'];
            $quantas = $item['fotos'];
            // Já existe (rodou antes): pula.
            if (GaleriaAlbum::where('titulo', self::TESTE.$titulo)->exists()) {
                continue;
            }
            $album = GaleriaAlbum::create([
                'titulo' => self::TESTE.$titulo,
                'descricao' => 'Álbum de exemplo do piloto. As fotos são imagens geradas, sem pessoas reais.',
                'data_evento' => CarbonImmutable::today()->subDays(3 + $a * 10)->toDateString(),
                'local_evento' => 'Recife - PE',
                'post_id' => $item['post'],
                'credito_padrao' => 'IBTO (imagem de exemplo)',
                'autorizacao_imagem' => true,
                'autorizacao_origem' => 'Termo de autorização (exemplo do piloto)',
                'tem_menores' => $item['menores'],
                // Com menores e SEM a autorização dos responsáveis: o caminho não deixa publicar.
                'autorizacao_responsaveis' => false,
            ]);
            $album->forceFill(['situacao' => $item['situacao']])->save();

            for ($n = 1; $n <= $quantas; $n++) {
                // No álbum "falta descrever", a ÚLTIMA foto fica sem descrição (testa a trava de publicar).
                $semDescricao = $item['sem_descricao'] && $n === $quantas;
                $foto = new GaleriaFoto([
                    'descricao' => $semDescricao ? null : "Foto de exemplo {$n} do álbum (imagem gerada)",
                    'ordem' => $n,
                ]);
                $foto->forceFill([
                    'album_id' => $album->id,
                    'caminho' => $this->imagemDeExemplo("galeria/{$album->id}", "foto-{$n}", "Foto {$n}"),
                ])->save();
            }
        }
    }

    /**
     * Gera uma imagem de exemplo (1200 × 800): fundo escuro da marca, o emblema
     * do IBTO no meio e um rótulo. Nenhuma pessoa real.
     *
     * @param  string  $pasta  Onde gravar, no disco público. Ex.: 'blog'.
     * @param  string  $nome  O nome do arquivo, sem extensão. Ex.: 'post-1'.
     * @param  string  $rotulo  O texto na imagem. Ex.: 'Foto 2'.
     * @return string O caminho gravado. Ex.: 'blog/piloto-post-1.jpg'.
     */
    private function imagemDeExemplo(string $pasta, string $nome, string $rotulo): string
    {
        $imagem = imagecreatetruecolor(1200, 800);
        // Fundo: o preto equilibrado da marca, com uma faixa dourada embaixo.
        imagefill($imagem, 0, 0, (int) imagecolorallocate($imagem, 20, 22, 26));
        imagefilledrectangle($imagem, 0, 740, 1200, 800, (int) imagecolorallocate($imagem, 107, 93, 58));

        // O emblema do IBTO no meio (se o arquivo existir).
        $emblema = @imagecreatefrompng(public_path('images/ibto-emblema.png'));
        if ($emblema !== false) {
            imagecopyresampled($imagem, $emblema, 450, 200, 0, 0, 300, 300, imagesx($emblema), imagesy($emblema));
        }

        // O rótulo, ampliado (a fonte embutida do GD é pequena: escreve pequeno e amplia).
        $texto = 'IBTO - '.$rotulo.' (exemplo do piloto)';
        $pequena = imagecreatetruecolor(strlen($texto) * 9, 16);
        imagefill($pequena, 0, 0, (int) imagecolorallocate($pequena, 20, 22, 26));
        imagestring($pequena, 5, 0, 0, $texto, (int) imagecolorallocate($pequena, 201, 189, 148));
        imagecopyresampled($imagem, $pequena, 100, 560, 0, 0, 1000, (int) round(1000 / imagesx($pequena) * 16), imagesx($pequena), 16);

        ob_start();
        imagejpeg($imagem, null, 85);
        $caminho = "{$pasta}/piloto-{$nome}.jpg";
        Storage::disk('public')->put($caminho, (string) ob_get_clean());

        return $caminho;
    }

    /**
     * Procura a área de atuação pelo nome.
     *
     * @param  list<string>  $palavras  Ex.: ['tát', 'tiro'].
     * @return int|null O número da área, ou null.
     */
    private function area(array $palavras): ?int
    {
        foreach ($palavras as $palavra) {
            $id = AreaAtuacao::where('nome', 'like', '%'.$palavra.'%')->value('id');
            // Achou: usa essa; senão, tenta a próxima.
            if ($id !== null) {
                return (int) $id;
            }
        }

        return null;
    }
}
