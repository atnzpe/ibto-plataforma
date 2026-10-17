/**
 * PÁGINA DO CURSO (#38, bloco C4a), pública, no desenho aprovado (artboard
 * "Aluno · Página do curso") e nos cartazes do IBTO: capa inteira, selos,
 * título, chamada, instrutor com especialidade, quando e onde, investimento,
 * material incluso em selos, módulos em cartões (como o cartaz de
 * Sobrevivência Urbana) e a lateral de inscrição. A inscrição de verdade
 * entra no bloco C5; por enquanto, o contato pelo WhatsApp.
 * Responsivo: conteúdo e lateral lado a lado na tela larga; um embaixo do
 * outro no celular.
 */
import type { RequisitoDoCurso } from '@/components/cursos/quadro-matricula';
import { Head, Link } from '@inertiajs/react';
import QuadroMatricula from '@/components/cursos/quadro-matricula';
import type { MatriculaDaPessoa } from '@/components/cursos/quadro-matricula';
import { Button } from '@/components/ui/button';
// Janela do encontro (C4d): componentes prontos de "janela" (modal) acessível.
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatarReais } from '@/lib/dinheiro';
import { dataBr } from '@/pages/catalogo/index';
import type { CartaoCurso } from '@/pages/catalogo/index';
import { index as catalogo, trilha as paginaDaTrilha } from '@/routes/catalogo';
import { assistir } from '@/routes/cursos';

type Aula = {
    id: number;
    titulo: string;
    duracao_minutos: number | null;
    data: string | null;
    hora_inicio: string | null;
    hora_fim: string | null;
    quem_aplica: string | null;
    tem_video: boolean;
    // Janela do cronograma (C4d): introdução pública e os links de lembrete (null sem data).
    introducao: string | null;
    lembrete_url: string | null;
    google_agenda_url: string | null;
    // A situação desta aula para a pessoa logada (só com matrícula confirmada); null = nada a mostrar.
    minha_situacao: 'presente' | 'falta' | 'aguardando' | 'assistida' | null;
};

// Texto e cor de cada situação da aula (ESCRITA, não só cor), no fundo escuro do módulo.
const SITUACAO_AULA: Record<
    NonNullable<Aula['minha_situacao']>,
    { texto: string; classe: string }
> = {
    presente: { texto: '✓ Presente', classe: 'bg-green-200 text-green-950' },
    falta: { texto: '✗ Falta', classe: 'bg-red-200 text-red-950' },
    aguardando: { texto: 'Aguardando', classe: 'bg-[#e9e2cc] text-[#1c1b17]' },
    assistida: { texto: '✓ Assistida', classe: 'bg-green-200 text-green-950' },
};

type CursoDetalhe = CartaoCurso & {
    descricao: string | null;
    data_fim: string | null;
    hora_inicio: string | null;
    hora_fim: string | null;
    instrutor_especialidade: string | null;
    materiais: string[];
    contato_whatsapp: string | null;
    whatsapp_url: string | null;
    situacao_rotulo: string;
    previa: boolean;
    modulos: { id: number; titulo: string; aulas: Aula[] }[];
    trilhas: { id: number; nome: string; etapa: number }[];
};

/**
 * JANELA DO ENCONTRO (#38, bloco C4d; conversa de 23/09/2026 com a Aline e o
 * Eduardo): o encontro do cronograma vira um botão; ao tocar, abre uma janela
 * com o dia, o horário, o local, quem aplica, a introdução e o LEMBRETE
 * (arquivo de agenda .ics ou o Google Agenda).
 *
 * @param aula O encontro. Ex.: { titulo: 'Aula 1', data: '2026-10-10', hora_inicio: '09:00' }
 * @param local Onde acontece (o local do curso). Ex.: 'Recife/PE'
 * @returns O botão com a data e a janela.
 */
function JanelaEncontro({ aula, local }: { aula: Aula; local: string | null }) {
    // Texto do horário. Ex.: "09:00 às 12:00", "a partir das 09:00" ou null (dia inteiro).
    const horario = aula.hora_inicio
        ? aula.hora_fim
            ? `${aula.hora_inicio} às ${aula.hora_fim}`
            : `a partir das ${aula.hora_inicio}`
        : null;

    return (
        <Dialog>
            {/* O botão mostra a data e a hora; 44 px de altura para o dedo. */}
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="ml-1 inline-flex min-h-11 items-center rounded-lg border border-[#6b5d3a] px-2 text-xs text-[#c9bd94] hover:bg-[#2a2721]"
                    aria-label={`Detalhes e lembrete do encontro ${aula.titulo}`}
                >
                    📅 {dataBr(aula.data ?? '')}
                    {aula.hora_inicio ? ` ${aula.hora_inicio}` : ''}
                </button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{aula.titulo}</DialogTitle>
                    {aula.introducao && (
                        <DialogDescription className="whitespace-pre-line">
                            {aula.introducao}
                        </DialogDescription>
                    )}
                </DialogHeader>

                {/* Os detalhes em pares "nome: valor". */}
                <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                    <dt className="text-muted-foreground">Dia</dt>
                    <dd>{dataBr(aula.data ?? '')}</dd>
                    <dt className="text-muted-foreground">Horário</dt>
                    <dd>{horario ?? 'O dia todo'}</dd>
                    {local && (
                        <>
                            <dt className="text-muted-foreground">Local</dt>
                            <dd>{local}</dd>
                        </>
                    )}
                    {aula.quem_aplica && (
                        <>
                            <dt className="text-muted-foreground">
                                Quem aplica
                            </dt>
                            <dd>{aula.quem_aplica}</dd>
                        </>
                    )}
                </dl>

                {/* Lembrete: 2 botões iguais (empilhados no celular). */}
                <div className="grid gap-2 sm:grid-cols-2">
                    {aula.lembrete_url && (
                        <Button asChild className="min-h-11">
                            {/* Link comum: o navegador baixa o .ics e o celular oferece "Adicionar à agenda". */}
                            <a href={aula.lembrete_url}>Lembrete na agenda</a>
                        </Button>
                    )}
                    {aula.google_agenda_url && (
                        <Button asChild variant="outline" className="min-h-11">
                            <a
                                href={aula.google_agenda_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Google Agenda
                            </a>
                        </Button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
// Classe comum dos "selos" (etiquetas arredondadas).
const selo = 'rounded-full px-3 py-0.5 text-xs';

/**
 * @param curso Tudo o que a página mostra.
 * @param logado true se há alguém logado (muda o botão da inscrição).
 * @param pode_assistir true se a pessoa pode ver o conteúdo (botão e aulas viram links para a sala de aula).
 * @param vagas_restantes Quantas vagas sobram (null = sem limite). Ex.: 3
 * @param requisitos Pré-requisitos do curso (#88), cada um com cumprido true/false (visitante: null).
 * @param vagas_preenchidas Quantas vagas já estão ocupadas. Ex.: 14
 * @param matricula A matrícula da pessoa logada (aguardando ou confirmada), ou null.
 * @returns A página do curso.
 */
export default function PaginaCurso({
    curso,
    logado,
    pode_assistir,
    vagas_restantes,
    requisitos = [],
    albuns_fotos = [],
    vagas_preenchidas = 0,
    matricula,
}: {
    curso: CursoDetalhe;
    logado: boolean;
    pode_assistir: boolean;
    // Vagas que sobram (#41); null = sem limite.
    vagas_restantes: number | null;
    requisitos?: RequisitoDoCurso[];
    // Venda de fotos (#90): os álbuns deste curso que a pessoa pode ver.
    albuns_fotos?: { id: number; titulo: string }[];
    // Vagas já ocupadas (aguardando ou confirmadas). Ex.: 14.
    vagas_preenchidas?: number;
    // A matrícula da pessoa logada, ou null.
    matricula: MatriculaDaPessoa | null;
}) {
    // A primeira aula (para o botão "Assistir as aulas"), só para quem pode assistir.
    const primeiraAula = pode_assistir
        ? curso.modulos.flatMap((modulo) => modulo.aulas)[0]
        : undefined;
    // Total de aulas, para o resumo do conteúdo. Ex.: 6.
    const totalDeAulas = curso.modulos.reduce(
        (soma, modulo) => soma + modulo.aulas.length,
        0,
    );

    return (
        <>
            <Head title={curso.titulo} />

            <div className="flex flex-col gap-6">
                <Link
                    href={catalogo()}
                    className="text-sm text-[#6b5d3a] underline-offset-4 hover:underline"
                >
                    ← Todos os cursos
                </Link>

                {/* Prévia: o curso ainda não está publicado (só a equipe e o dono veem). */}
                {curso.previa && (
                    <p
                        role="status"
                        className="rounded-lg border border-amber-600/40 bg-amber-600/10 px-4 py-2 text-sm"
                    >
                        Prévia: este curso está como{' '}
                        <strong>{curso.situacao_rotulo}</strong> e ainda não
                        aparece para o público.
                    </p>
                )}

                <div className="flex flex-wrap items-start gap-6">
                    {/* ---------- Conteúdo principal ---------- */}
                    <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-6">
                        <section className="overflow-hidden rounded-2xl border border-[#e4dccb] bg-white">
                            {/* Capa inteira, sem cortar (cartaz em pé ou deitado). */}
                            <div className="flex max-h-[28rem] items-center justify-center bg-[#1c1b17]">
                                {curso.imagem_url ? (
                                    <img
                                        src={curso.imagem_url}
                                        alt={`Cartaz do curso ${curso.titulo}`}
                                        className="max-h-[28rem] w-full object-contain"
                                    />
                                ) : (
                                    <span className="py-16 font-[Audiowide,sans-serif] text-5xl text-[#c9bd94]">
                                        IBTO
                                    </span>
                                )}
                            </div>
                            <div className="flex flex-col gap-3 p-6">
                                <div className="flex flex-wrap gap-1.5">
                                    <span
                                        className={`${selo} border border-[#6b5d3a] font-semibold text-[#6b5d3a]`}
                                    >
                                        {curso.modalidade}
                                    </span>
                                    {curso.area && (
                                        <span
                                            className={`${selo} bg-[#efe8d6] text-[#3f3a2c]`}
                                        >
                                            {curso.area}
                                        </span>
                                    )}
                                    <span
                                        className={`${selo} bg-[#efe8d6] text-[#3f3a2c]`}
                                    >
                                        {curso.tipo}
                                    </span>
                                    {/* Cada trilha vira link para o fluxograma dela. */}
                                    {curso.trilhas.map((trilha) => (
                                        <Link
                                            key={trilha.id}
                                            href={paginaDaTrilha(trilha.id)}
                                            className={`${selo} bg-[#efe8d6] text-[#3f3a2c] underline-offset-4 hover:underline`}
                                        >
                                            Trilha: {trilha.nome} · etapa{' '}
                                            {trilha.etapa}
                                        </Link>
                                    ))}
                                </div>
                                <h1 className="font-[Audiowide,sans-serif] text-3xl leading-tight font-normal">
                                    {curso.titulo}
                                </h1>
                                {curso.chamada && (
                                    <p className="text-lg font-medium text-[#6b5d3a]">
                                        {curso.chamada}
                                    </p>
                                )}
                                {curso.instrutor && (
                                    <p className="text-sm">
                                        Instrutor:{' '}
                                        <strong className="font-semibold">
                                            {curso.instrutor}
                                        </strong>
                                        {curso.instrutor_especialidade &&
                                            ` (${curso.instrutor_especialidade})`}
                                    </p>
                                )}
                                {/* white-space: pre-line respeita as quebras de linha da descrição. */}
                                {curso.descricao && (
                                    <p className="text-[15px] leading-relaxed whitespace-pre-line text-[#3f3a2c]">
                                        {curso.descricao}
                                    </p>
                                )}
                            </div>
                        </section>

                        {/* Material incluso: um selo por item (como nos cartazes). */}
                        {curso.materiais.length > 0 && (
                            <section
                                aria-labelledby="t-material"
                                className="flex flex-col gap-3 rounded-2xl border border-[#e4dccb] bg-white p-6"
                            >
                                <h2
                                    id="t-material"
                                    className="text-lg font-semibold"
                                >
                                    Material incluso
                                </h2>
                                <ul className="flex flex-wrap gap-2">
                                    {curso.materiais.map((material) => (
                                        <li
                                            key={material}
                                            className="rounded-lg border border-[#6b5d3a] px-3 py-2 text-sm font-medium text-[#3f3a2c]"
                                        >
                                            {material}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        {/* Conteúdo: os módulos em cartões, com as aulas/encontros (sem abrir vídeo nem apostila). */}
                        {curso.modulos.length > 0 && (
                            <section
                                aria-labelledby="t-conteudo"
                                className="flex flex-col gap-3 rounded-2xl border border-[#e4dccb] bg-white p-6"
                            >
                                <h2
                                    id="t-conteudo"
                                    className="text-lg font-semibold"
                                >
                                    Conteúdo · {curso.modulos.length} módulo(s),{' '}
                                    {totalDeAulas} aula(s)
                                </h2>
                                <ol className="grid gap-3 sm:grid-cols-2">
                                    {curso.modulos.map((modulo, posicao) => (
                                        <li
                                            key={modulo.id}
                                            className="flex flex-col gap-2 rounded-xl bg-[#1c1b17] p-4 text-[#f4f1e8]"
                                        >
                                            <span className="text-xs text-[#c9bd94]">
                                                Módulo {posicao + 1}
                                            </span>
                                            <strong className="font-semibold">
                                                {modulo.titulo}
                                            </strong>
                                            <ul className="flex flex-col gap-1 text-sm text-[#e9e2cc]">
                                                {modulo.aulas.map((aula) => (
                                                    <li key={aula.id}>
                                                        ·{' '}
                                                        {/* Quem pode assistir: o título vira link para a aula. */}
                                                        {pode_assistir ? (
                                                            <Link
                                                                href={assistir({
                                                                    curso: curso.id,
                                                                    aula: aula.id,
                                                                })}
                                                                className="text-[#f4f1e8] underline underline-offset-4"
                                                            >
                                                                {aula.titulo}
                                                            </Link>
                                                        ) : (
                                                            aula.titulo
                                                        )}
                                                        {/* Encontro com data: botão que abre a janela com detalhes e lembrete. */}
                                                        {aula.data && (
                                                            <JanelaEncontro
                                                                aula={aula}
                                                                local={
                                                                    curso.local
                                                                }
                                                            />
                                                        )}
                                                        {!aula.data &&
                                                            aula.duracao_minutos &&
                                                            ` — ${aula.duracao_minutos} min`}
                                                        {/* A situação desta aula para quem está matriculado (desenho de 10/10/2026). */}
                                                        {aula.minha_situacao && (
                                                            <span
                                                                className={`ml-2 rounded px-1.5 py-0.5 text-xs font-medium ${SITUACAO_AULA[aula.minha_situacao].classe}`}
                                                            >
                                                                {
                                                                    SITUACAO_AULA[
                                                                        aula
                                                                            .minha_situacao
                                                                    ].texto
                                                                }
                                                            </span>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        </li>
                                    ))}
                                </ol>
                            </section>
                        )}
                    </div>

                    {/* ---------- Lateral de inscrição ---------- */}
                    <aside
                        aria-labelledby="t-inscricao"
                        className="flex min-w-0 flex-[1_1_300px] flex-col gap-4 rounded-2xl border border-[#e4dccb] bg-white p-6"
                    >
                        <h2 id="t-inscricao" className="text-lg font-semibold">
                            Inscrição
                        </h2>
                        <dl className="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt className="text-xs text-[#5c5644]">Data</dt>
                                <dd className="font-medium">
                                    {curso.data_inicio
                                        ? dataBr(curso.data_inicio)
                                        : curso.presencial
                                          ? 'A definir'
                                          : 'No seu ritmo'}
                                    {curso.data_fim &&
                                        curso.data_fim !== curso.data_inicio &&
                                        ` a ${dataBr(curso.data_fim)}`}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-[#5c5644]">
                                    Horário
                                </dt>
                                <dd className="font-medium">
                                    {curso.hora_inicio
                                        ? `${curso.hora_inicio} às ${curso.hora_fim ?? '—'}`
                                        : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-[#5c5644]">
                                    Local
                                </dt>
                                <dd className="font-medium">
                                    {curso.local ??
                                        (curso.presencial
                                            ? 'A definir'
                                            : 'Online')}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-[#5c5644]">
                                    Carga horária
                                </dt>
                                <dd className="font-medium">
                                    {curso.carga_horaria
                                        ? `${curso.carga_horaria} h`
                                        : '—'}
                                </dd>
                            </div>
                        </dl>
                        <div>
                            <span className="text-xs text-[#5c5644]">
                                Investimento
                            </span>
                            <p className="text-3xl font-semibold">
                                {curso.valor_centavos === 0
                                    ? 'Gratuito'
                                    : formatarReais(curso.valor_centavos)}
                            </p>
                        </div>
                        {/* Vagas limitadas: a barra "14 de 20 preenchidas" (desenho de 10/10/2026). Sem limite: nada. */}
                        {curso.vagas && (
                            <div className="flex flex-col gap-1">
                                <div className="flex justify-between text-sm">
                                    <span>Vagas</span>
                                    <strong className="font-semibold">
                                        {vagas_preenchidas} de {curso.vagas}{' '}
                                        preenchidas
                                    </strong>
                                </div>
                                <div
                                    role="img"
                                    aria-label={`${vagas_preenchidas} de ${curso.vagas} vagas preenchidas`}
                                    className="h-2.5 overflow-hidden rounded-full bg-[#efe8d6]"
                                >
                                    {/* A parte cheia: a porcentagem preenchida (no máximo 100%). */}
                                    <div
                                        className="h-full bg-[#6b5d3a]"
                                        style={{
                                            width: `${Math.min(100, (vagas_preenchidas / curso.vagas) * 100)}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        )}
                        {curso.restrito && (
                            <p className="rounded-lg bg-[#1c1b17] px-3 py-2 text-sm text-[#c9bd94]">
                                Exclusivo para filiados do IBTO.
                            </p>
                        )}

                        <ol className="flex list-decimal flex-col gap-1 pl-5 text-sm text-[#3f3a2c]">
                            <li>
                                Garanta a sua vaga, pague pelo PIX e envie o
                                comprovante aqui.
                            </li>
                            <li>
                                O IBTO confirma o pagamento e você recebe um
                                e-mail.
                            </li>
                            <li>
                                Depois do curso: presença, certificado e
                                registro no seu dossiê.
                            </li>
                        </ol>

                        {/* Quem tem acesso ao conteúdo (equipe do curso agora; inscritos no C5): entra na sala de aula pela 1ª aula. */}
                        {primeiraAula !== undefined && (
                            <Link
                                href={assistir({
                                    curso: curso.id,
                                    aula: primeiraAula.id,
                                })}
                                className="inline-flex min-h-12 items-center justify-center rounded-xl bg-[#1c1b17] text-base font-semibold text-[#f4f1e8] no-underline hover:bg-black"
                            >
                                Assistir as aulas
                            </Link>
                        )}
                        {/* Matrícula (#41): entrar, garantir a vaga, PIX e comprovante, ou confirmada. */}
                        <QuadroMatricula
                            cursoId={curso.id}
                            logado={logado}
                            gratuito={curso.valor_centavos === 0}
                            previa={curso.previa}
                            vagasRestantes={vagas_restantes}
                            matricula={matricula}
                            requisitos={requisitos}
                        />
                        {/* Fotos do evento à venda (#90): só para quem fez o curso, com a venda ligada. */}
                        {albuns_fotos.map((album) => (
                            <Link
                                key={album.id}
                                href={`/fotos/albuns/${album.id}`}
                                className="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#6b5d3a] px-4 text-sm font-semibold text-[#6b5d3a] hover:bg-[#6b5d3a] hover:text-white"
                            >
                                Fotos do evento: {album.titulo}
                            </Link>
                        ))}
                        {curso.whatsapp_url && (
                            <a
                                href={curso.whatsapp_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#6b5d3a] text-sm font-medium text-[#3f3a2c] no-underline hover:bg-[#efe8d6]"
                            >
                                Mais informações no WhatsApp (
                                {curso.contato_whatsapp})
                            </a>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}
