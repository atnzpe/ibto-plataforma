/**
 * FAZER A PROVA (#43), tela do aluno: as instruções, a situação (melhor nota,
 * se aprovou e as tentativas que sobram) e as perguntas de múltipla escolha.
 * A tela NÃO recebe o gabarito: a correção é feita no servidor, que devolve a
 * nota. Aprovado (ou sem tentativas): só mostra o resultado.
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/** Uma pergunta, com as alternativas (sem dizer qual é a certa). */
type Questao = {
    id: number;
    ordem: number;
    enunciado: string;
    alternativas: { id: number; texto: string }[];
};

/**
 * @param curso O curso. Ex.: { id: 7, titulo: 'Inteligência Operacional' }
 * @param prova A prova, com as perguntas.
 * @param situacao A melhor nota (null = não fez), se aprovou e as tentativas.
 * @returns A tela da prova.
 */
export default function FazerProva({
    curso,
    prova,
    situacao,
}: {
    curso: { id: number; titulo: string };
    prova: {
        id: number;
        titulo: string;
        instrucoes: string | null;
        nota_minima: number;
        tentativas_max: number;
        questoes: Questao[];
    };
    situacao: {
        melhor_nota: number | null;
        aprovada: boolean;
        usadas: number;
        restantes: number;
    };
}) {
    // A nota da última tentativa (mensagem do servidor) e o erro, se houver.
    const { sucesso, errors } = usePage<{
        sucesso?: string | null;
        errors: Record<string, string>;
    }>().props;
    // As respostas marcadas: { número da pergunta: número da alternativa }.
    const [respostas, setRespostas] = useState<Record<number, number>>({});
    // Enviando: trava o botão (evita dois envios e duas tentativas gastas).
    const [enviando, setEnviando] = useState(false);
    // Pode responder: ainda não aprovado e com tentativa sobrando.
    const podeResponder = !situacao.aprovada && situacao.restantes > 0;
    // Quantas perguntas ainda estão sem resposta.
    const faltam = prova.questoes.filter(
        (questao) => respostas[questao.id] === undefined,
    ).length;

    /**
     * Envia as respostas para o servidor corrigir.
     *
     * @param evento O envio do formulário.
     */
    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        setEnviando(true);
        router.post(
            `/cursos/${curso.id}/provas/${prova.id}`,
            { respostas },
            {
                preserveScroll: false,
                // Depois do envio (com nota ou erro), limpa as marcações e destrava.
                onFinish: () => {
                    setEnviando(false);
                    setRespostas({});
                },
            },
        );
    };

    return (
        <>
            <Head title={`${prova.titulo} · ${curso.titulo}`} />
            <div className="flex max-w-3xl flex-col gap-5 p-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {curso.titulo}
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {prova.titulo}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Nota mínima: {prova.nota_minima} · Tentativas:{' '}
                        {situacao.usadas} de {prova.tentativas_max}
                    </p>
                </div>

                {/* Resultado da última tentativa, anunciado pelo leitor de tela. */}
                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}
                {errors.respostas && (
                    <p
                        role="alert"
                        className="rounded-md border border-red-600/40 bg-red-600/10 px-4 py-2 text-sm"
                    >
                        {errors.respostas}
                    </p>
                )}

                {/* Situação atual, escrita (não só cor). */}
                <p
                    className="rounded-md border border-border px-4 py-3 text-sm"
                    data-tour="situacao-prova"
                >
                    {situacao.aprovada
                        ? `Você foi aprovado nesta prova (melhor nota: ${situacao.melhor_nota}).`
                        : situacao.restantes === 0
                          ? `As tentativas acabaram (melhor nota: ${situacao.melhor_nota ?? 0}). Fale com o instrutor do curso.`
                          : situacao.melhor_nota === null
                            ? 'Você ainda não fez esta prova.'
                            : `Melhor nota até agora: ${situacao.melhor_nota}. Restam ${situacao.restantes} tentativa(s).`}
                </p>

                {prova.instrucoes && (
                    <p className="text-sm whitespace-pre-line">
                        {prova.instrucoes}
                    </p>
                )}

                {podeResponder && (
                    <form
                        onSubmit={enviar}
                        className="flex flex-col gap-4"
                        data-tour="perguntas-prova"
                    >
                        {prova.questoes.map((questao) => (
                            // fieldset + legend: o leitor de tela lê a pergunta antes das opções.
                            <fieldset
                                key={questao.id}
                                className="flex flex-col gap-2 rounded-md border border-border p-4"
                            >
                                <legend className="px-1 text-sm font-medium whitespace-pre-line">
                                    {questao.ordem}. {questao.enunciado}
                                </legend>
                                {questao.alternativas.map((alternativa) => (
                                    <label
                                        key={alternativa.id}
                                        className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 text-sm"
                                    >
                                        <input
                                            type="radio"
                                            name={`questao-${questao.id}`}
                                            value={alternativa.id}
                                            checked={
                                                respostas[questao.id] ===
                                                alternativa.id
                                            }
                                            onChange={() =>
                                                setRespostas((atual) => ({
                                                    ...atual,
                                                    [questao.id]:
                                                        alternativa.id,
                                                }))
                                            }
                                            className="size-5"
                                        />
                                        <span className="flex-1">
                                            {alternativa.texto}
                                        </span>
                                    </label>
                                ))}
                            </fieldset>
                        ))}
                        <p className="text-sm text-muted-foreground">
                            {faltam === 0
                                ? 'Todas respondidas. Confira e envie.'
                                : `Faltam ${faltam} pergunta(s).`}
                        </p>
                        <Button
                            type="submit"
                            className="min-h-11 self-start"
                            disabled={enviando || faltam > 0}
                        >
                            Enviar respostas
                        </Button>
                    </form>
                )}

                <Link
                    href={`/cursos/${curso.id}`}
                    className="text-sm underline underline-offset-4"
                >
                    Voltar para o curso
                </Link>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
FazerProva.layout = {
    breadcrumbs: [{ title: 'Meus cursos', href: '/meus-cursos' }],
};
