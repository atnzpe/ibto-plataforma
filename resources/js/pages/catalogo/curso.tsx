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
import { Head, Link } from '@inertiajs/react';
import { formatarReais } from '@/lib/dinheiro';
import { dataBr } from '@/pages/catalogo/index';
import type { CartaoCurso } from '@/pages/catalogo/index';
import { login } from '@/routes';
import { index as catalogo } from '@/routes/catalogo';

type Aula = {
    id: number;
    titulo: string;
    duracao_minutos: number | null;
    data: string | null;
    hora_inicio: string | null;
    hora_fim: string | null;
    quem_aplica: string | null;
    tem_video: boolean;
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

// Classe comum dos "selos" (etiquetas arredondadas).
const selo = 'rounded-full px-3 py-0.5 text-xs';

/**
 * @param curso Tudo o que a página mostra.
 * @param logado true se há alguém logado (muda o botão da inscrição).
 * @returns A página do curso.
 */
export default function PaginaCurso({
    curso,
    logado,
}: {
    curso: CursoDetalhe;
    logado: boolean;
}) {
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
                                    {curso.trilhas.map((trilha) => (
                                        <span
                                            key={trilha.id}
                                            className={`${selo} bg-[#efe8d6] text-[#3f3a2c]`}
                                        >
                                            Trilha: {trilha.nome} · etapa{' '}
                                            {trilha.etapa}
                                        </span>
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
                                                        · {aula.titulo}
                                                        {aula.data &&
                                                            ` — ${dataBr(aula.data)}${aula.hora_inicio ? ` ${aula.hora_inicio}` : ''}`}
                                                        {!aula.data &&
                                                            aula.duracao_minutos &&
                                                            ` — ${aula.duracao_minutos} min`}
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
                        {curso.vagas && (
                            <p className="text-sm font-semibold text-[#a33a00]">
                                Vagas limitadas · {curso.vagas} vagas
                            </p>
                        )}
                        {curso.restrito && (
                            <p className="rounded-lg bg-[#1c1b17] px-3 py-2 text-sm text-[#c9bd94]">
                                Exclusivo para filiados do IBTO.
                            </p>
                        )}

                        <ol className="flex list-decimal flex-col gap-1 pl-5 text-sm text-[#3f3a2c]">
                            <li>Inscreva-se e pague pelo link (PIX).</li>
                            <li>O IBTO confirma o pagamento e a sua vaga.</li>
                            <li>
                                Depois do curso: presença, certificado e
                                registro no seu dossiê.
                            </li>
                        </ol>

                        {/* A inscrição pela plataforma entra no bloco C5; por enquanto, entrar e falar no WhatsApp. */}
                        {!logado && (
                            <Link
                                href={login()}
                                className="inline-flex min-h-12 items-center justify-center rounded-xl bg-[#6b5d3a] text-base font-semibold text-white no-underline hover:bg-[#5a4e30]"
                            >
                                Entrar para se inscrever
                            </Link>
                        )}
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
