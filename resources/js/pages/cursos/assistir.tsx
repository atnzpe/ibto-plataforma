/**
 * SALA DE AULA (#39, bloco C4c): uma aula por vez, em SEQUÊNCIA, como a
 * videoteca do Dojo Manager: vídeo (YouTube, link de arquivo ou enviado),
 * texto da aula, apostila, encontro presencial, "Anterior" / "Próxima" e a
 * posição ("3 / 10"). Ao lado (tela larga) ou embaixo (celular), a lista de
 * todas as aulas, para pular direto. Sem rolagem lateral: o vídeo se ajusta
 * à largura da tela (proporção 16:9).
 */
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dataBr } from '@/pages/catalogo/index';
import { curso as paginaDoCurso } from '@/routes/catalogo';
import { assistir } from '@/routes/cursos';

/** Como tocar o vídeo (vem de CursoAula::video() no servidor). */
type Video =
    | { tipo: 'youtube'; id: string; embed: string }
    | { tipo: 'arquivo'; url: string }
    | { tipo: 'enviado'; url: string };

/**
 * O vídeo da aula: YouTube num quadro (iframe) ou o arquivo no tocador do navegador.
 *
 * @param video Ex.: { tipo: 'youtube', embed: 'https://www.youtube-nocookie.com/embed/...' }
 * @param titulo O título da aula (para o leitor de tela).
 * @returns O tocador, sempre em 16:9 e na largura toda.
 */
function Tocador({ video, titulo }: { video: Video; titulo: string }) {
    // YouTube: quadro do youtube-nocookie (não grava cookies de propaganda).
    if (video.tipo === 'youtube') {
        return (
            <iframe
                src={video.embed}
                title={`Vídeo: ${titulo}`}
                className="aspect-video w-full rounded-lg bg-black"
                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                allowFullScreen
                loading="lazy"
                referrerPolicy="strict-origin-when-cross-origin"
            />
        );
    }

    // Arquivo (link .mp4/.webm ou enviado à plataforma): o tocador do próprio navegador.
    return (
        <video
            src={video.url}
            controls
            preload="metadata"
            controlsList="nodownload"
            className="aspect-video w-full rounded-lg bg-black"
            aria-label={`Vídeo: ${titulo}`}
        />
    );
}

/**
 * @param curso O curso. Ex.: { id: 7, titulo: 'Combate Corpo a Corpo' }
 * @param aula A aula aberta, com vídeo, texto, apostila e encontro.
 * @param posicao A posição dela (começa em 1). Ex.: 3
 * @param total Quantas aulas a pessoa pode ver. Ex.: 10
 * @param anterior_id A aula anterior, ou null na primeira.
 * @param proxima_id A próxima, ou null na última.
 * @param aulas Todas as aulas (para a lista).
 * @returns A tela da sala de aula.
 */
export default function Assistir({
    curso,
    aula,
    posicao,
    total,
    anterior_id,
    proxima_id,
    aulas,
}: {
    curso: { id: number; titulo: string };
    aula: {
        id: number;
        titulo: string;
        modulo: string;
        conteudo: string | null;
        duracao_minutos: number | null;
        video: Video | null;
        apostila_url: string | null;
        data: string | null;
        hora_inicio: string | null;
        hora_fim: string | null;
        quem_aplica: string | null;
    };
    posicao: number;
    total: number;
    anterior_id: number | null;
    proxima_id: number | null;
    aulas: { id: number; titulo: string; modulo: string }[];
}) {
    /**
     * Endereço de outra aula deste curso.
     *
     * @param id A aula. Ex.: 31
     * @returns Ex.: '/cursos/7/assistir/31'
     */
    const irPara = (id: number) => assistir({ curso: curso.id, aula: id });

    return (
        <>
            <Head title={`${aula.titulo} · ${curso.titulo}`} />

            {/* Tela larga: aula (2/3) + lista (1/3). Celular: um embaixo do outro. */}
            <div className="grid gap-6 p-4 lg:grid-cols-3">
                <div className="flex min-w-0 flex-col gap-4 lg:col-span-2">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {curso.titulo} · {aula.modulo}
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {aula.titulo}
                        </h1>
                        {/* Posição na sequência, escrita (não só cor). */}
                        <p className="text-sm text-muted-foreground">
                            Aula {posicao} / {total}
                            {aula.duracao_minutos
                                ? ` · ${aula.duracao_minutos} min`
                                : ''}
                        </p>
                    </div>

                    {aula.video && (
                        <Tocador video={aula.video} titulo={aula.titulo} />
                    )}

                    {/* Encontro presencial (aula do cronograma): dia, horário e quem aplica. */}
                    {aula.data && (
                        <p className="rounded-md border border-border px-4 py-3 text-sm">
                            <strong>Encontro:</strong> {dataBr(aula.data)}
                            {aula.hora_inicio &&
                                `, das ${aula.hora_inicio}${aula.hora_fim ? ` às ${aula.hora_fim}` : ''}`}
                            {aula.quem_aplica && ` · com ${aula.quem_aplica}`}
                        </p>
                    )}

                    {/* Texto da aula: as quebras de linha digitadas são mantidas. */}
                    {aula.conteudo && (
                        <div className="text-sm leading-relaxed whitespace-pre-line">
                            {aula.conteudo}
                        </div>
                    )}

                    {aula.apostila_url && (
                        <Button
                            asChild
                            variant="outline"
                            className="w-full sm:w-fit"
                        >
                            {/* Link comum (não Inertia): o navegador baixa o PDF. */}
                            <a href={aula.apostila_url}>
                                Baixar a apostila (PDF)
                            </a>
                        </Button>
                    )}

                    {/* Anterior / Próxima: 2 botões iguais, lado a lado, em qualquer tela. */}
                    <nav
                        aria-label="Sequência das aulas"
                        className="grid grid-cols-2 gap-3"
                    >
                        {anterior_id !== null ? (
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-11"
                            >
                                <Link href={irPara(anterior_id)}>
                                    ← Anterior
                                </Link>
                            </Button>
                        ) : (
                            // Primeira aula: botão desligado, para manter a simetria.
                            <Button
                                variant="outline"
                                className="min-h-11"
                                disabled
                            >
                                ← Anterior
                            </Button>
                        )}
                        {proxima_id !== null ? (
                            <Button asChild className="min-h-11">
                                <Link href={irPara(proxima_id)}>Próxima →</Link>
                            </Button>
                        ) : (
                            // Última aula: volta para a página do curso.
                            <Button
                                asChild
                                variant="secondary"
                                className="min-h-11"
                            >
                                <Link href={paginaDoCurso(curso.id)}>
                                    Concluir ✓
                                </Link>
                            </Button>
                        )}
                    </nav>
                </div>

                {/* Lista de todas as aulas: a aberta fica marcada (aria-current). */}
                <nav
                    aria-label="Aulas do curso"
                    className="flex flex-col gap-2"
                >
                    <h2 className="text-sm font-semibold">Aulas do curso</h2>
                    <ol className="flex flex-col divide-y divide-border rounded-md border border-border">
                        {aulas.map((item, indice) => (
                            <li key={item.id}>
                                <Link
                                    href={irPara(item.id)}
                                    aria-current={
                                        item.id === aula.id ? 'page' : undefined
                                    }
                                    className="flex min-h-11 items-start gap-3 px-3 py-2 text-sm hover:bg-muted/50 aria-[current=page]:bg-muted aria-[current=page]:font-semibold"
                                >
                                    <span className="w-6 shrink-0 text-right font-mono text-muted-foreground">
                                        {indice + 1}
                                    </span>
                                    <span className="min-w-0">
                                        {item.titulo}
                                        <span className="block text-xs font-normal text-muted-foreground">
                                            {item.modulo}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ol>
                </nav>
            </div>
        </>
    );
}
