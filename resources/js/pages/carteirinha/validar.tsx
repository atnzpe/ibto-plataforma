/**
 * VALIDAÇÃO PÚBLICA DA CARTEIRINHA (#46), aberta pelo QR code.
 *
 * Mostra só o necessário: situação (com a validade), registro, nome abreviado,
 * categoria, nível e equipe. Foto, nome completo e cidade só se o filiado
 * autorizou o uso da imagem. Código que não existe: "não encontrado", sem
 * revelar nada. A situação vem escrita (não só a cor).
 * PASSAPORTE (#81): no topo, o semáforo (🟢 Liberado, 🟡 Liberado com ressalva,
 * 🔴 Acesso negado) para o instrutor decidir na porta do treino.
 */
import { Head } from '@inertiajs/react';

/** As três cores do semáforo do passaporte (#81). */
type Sinal = 'verde' | 'amarelo' | 'vermelho';

// Cor e símbolo de cada sinal. Contraste AA: texto escuro sobre fundo claro.
const SEMAFORO: Record<Sinal, { classe: string; simbolo: string }> = {
    verde: {
        classe: 'border-green-700 bg-green-50 text-green-900',
        simbolo: '🟢',
    },
    amarelo: {
        classe: 'border-amber-700 bg-amber-50 text-amber-900',
        simbolo: '🟡',
    },
    vermelho: {
        classe: 'border-red-700 bg-red-50 text-red-900',
        simbolo: '🔴',
    },
};

/** Os dados públicos da carteirinha. */
type Carteirinha = {
    // O semáforo do passaporte (#81): a cor, o texto e o motivo (ex.: 'Anuidade pendente').
    sinal: Sinal;
    sinal_rotulo: string;
    sinal_motivo: string | null;
    registro: string;
    situacao: 'ativa' | 'pendente' | 'inativa';
    situacao_rotulo: string;
    validade: string | null;
    categoria: string | null;
    nivel: string | null;
    equipe: string | null;
    nome_abreviado: string;
    nome_completo: string | null;
    cidade: string | null;
    foto_publica_url: string | null;
};

// Cor e símbolo de cada situação (o texto também diz, para quem não distingue cores).
const ESTILO: Record<Carteirinha['situacao'], string> = {
    ativa: 'border-green-700 bg-green-50 text-green-900',
    pendente: 'border-amber-700 bg-amber-50 text-amber-900',
    inativa: 'border-red-700 bg-red-50 text-red-900',
};

/**
 * @param codigo O código lido do QR. Ex.: 'IBTO-7K2M-9QXA'.
 * @param carteirinha Os dados públicos, ou null se o código não existe.
 * @returns A página de validação.
 */
export default function ValidarCarteirinha({
    codigo,
    carteirinha,
}: {
    codigo: string;
    carteirinha: Carteirinha | null;
}) {
    return (
        <>
            <Head title="Validação da carteirinha" />
            <div className="mx-auto flex max-w-xl flex-col gap-6 px-4 py-10">
                <h1 className="font-[Audiowide,sans-serif] text-2xl">
                    Validação da carteirinha
                </h1>
                <p className="text-sm text-[#4a4536]">
                    Código: <strong className="font-mono">{codigo}</strong>
                </p>

                {carteirinha === null ? (
                    <p
                        role="status"
                        className="rounded-xl border border-red-700 bg-red-50 p-4 text-red-900"
                    >
                        Carteirinha não encontrada. Confira o código ou fale com
                        o IBTO.
                    </p>
                ) : (
                    <section
                        aria-labelledby="t-carteirinha"
                        className="flex flex-col gap-4 rounded-xl border border-[#e4dccb] bg-white p-6"
                    >
                        {/* SEMÁFORO DO PASSAPORTE (#81): cor, símbolo e texto (quem não distingue cores lê o texto). */}
                        <div
                            role="status"
                            className={`flex items-center gap-4 rounded-lg border-2 p-4 ${SEMAFORO[carteirinha.sinal].classe}`}
                        >
                            <span
                                aria-hidden="true"
                                className="text-4xl leading-none"
                            >
                                {SEMAFORO[carteirinha.sinal].simbolo}
                            </span>
                            <div>
                                <p className="text-xl font-bold">
                                    {carteirinha.sinal_rotulo}
                                </p>
                                {/* O motivo só aparece no amarelo e no vermelho (ex.: 'Anuidade pendente'). */}
                                {carteirinha.sinal_motivo && (
                                    <p className="text-sm">
                                        {carteirinha.sinal_motivo}
                                    </p>
                                )}
                            </div>
                        </div>
                        <p
                            className={`rounded-lg border p-3 text-sm font-semibold ${ESTILO[carteirinha.situacao]}`}
                        >
                            Carteirinha: {carteirinha.situacao_rotulo}
                            {carteirinha.validade
                                ? ` · válida até ${carteirinha.validade}`
                                : ''}
                        </p>
                        <div className="flex flex-wrap items-center gap-4">
                            {/* Foto só com a autorização de imagem do filiado. */}
                            {carteirinha.foto_publica_url && (
                                <img
                                    src={carteirinha.foto_publica_url}
                                    alt="Foto do filiado"
                                    className="h-28 w-24 rounded-md object-cover"
                                />
                            )}
                            <div>
                                <h2
                                    id="t-carteirinha"
                                    className="text-lg font-semibold"
                                >
                                    {carteirinha.nome_completo ??
                                        carteirinha.nome_abreviado}
                                </h2>
                                <p className="font-mono text-sm">
                                    {carteirinha.registro}
                                </p>
                            </div>
                        </div>
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            {carteirinha.categoria && (
                                <>
                                    <dt className="text-[#5c5644]">
                                        Categoria
                                    </dt>
                                    <dd>{carteirinha.categoria}</dd>
                                </>
                            )}
                            {carteirinha.nivel && (
                                <>
                                    <dt className="text-[#5c5644]">Nível</dt>
                                    <dd>{carteirinha.nivel}</dd>
                                </>
                            )}
                            {carteirinha.equipe && (
                                <>
                                    <dt className="text-[#5c5644]">Equipe</dt>
                                    <dd>{carteirinha.equipe}</dd>
                                </>
                            )}
                            {carteirinha.cidade && (
                                <>
                                    <dt className="text-[#5c5644]">Cidade</dt>
                                    <dd>{carteirinha.cidade}</dd>
                                </>
                            )}
                        </dl>
                        <p className="text-xs text-[#5c5644]">
                            Instituto Brasileiro de Treinamento Operacional. Por
                            segurança, esta página mostra só o necessário para
                            conferir a carteirinha.
                        </p>
                    </section>
                )}
            </div>
        </>
    );
}
