/**
 * FICHA DA MATRÍCULA no Comando Geral (#41; decisão do Gleyson em
 * 10/10/2026: na lista só o registro; o NOME e o E-MAIL só aqui, ao abrir a
 * ficha). Cada abertura fica registrada na auditoria (LGPD).
 */
import { Head, Link } from '@inertiajs/react';
import { formatarReais } from '@/lib/dinheiro';
import { index } from '@/routes/comando-geral/matriculas';

/**
 * @param matricula Os dados da matrícula e da pessoa.
 * @returns A tela da ficha.
 */
export default function FichaMatricula({
    matricula,
}: {
    matricula: {
        id: number;
        nome: string;
        email: string | null;
        registro: string | null;
        equipe: string;
        curso: string;
        situacao: string;
        situacao_rotulo: string;
        valor_centavos: number;
        criada_em: string | null;
        confirmada_em: string | null;
        cancelada_em: string | null;
        motivo_cancelamento: string | null;
        comprovante_url: string | null;
        lancamento_id: number | null;
    };
}) {
    // Os pares "nome: valor" da ficha (só os que têm valor aparecem).
    const linhas: [string, string | null][] = [
        ['Nome', matricula.nome],
        ['E-mail', matricula.email ?? 'Sem e-mail'],
        ['Registro', matricula.registro ?? 'Não filiado'],
        ['Equipe', matricula.equipe],
        ['Curso', matricula.curso],
        ['Situação', matricula.situacao_rotulo],
        [
            'Valor',
            matricula.valor_centavos === 0
                ? 'Gratuito'
                : formatarReais(matricula.valor_centavos),
        ],
        ['Matrícula em', matricula.criada_em],
        ['Confirmada em', matricula.confirmada_em],
        ['Cancelada em', matricula.cancelada_em],
        ['Motivo do cancelamento', matricula.motivo_cancelamento],
        [
            'Receita no Financeiro',
            matricula.lancamento_id !== null
                ? `Lançamento nº ${matricula.lancamento_id}`
                : null,
        ],
    ];

    return (
        <>
            <Head title={`Matrícula nº ${matricula.id}`} />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <Link
                    href={index()}
                    className="text-sm underline underline-offset-4"
                >
                    ← Voltar às matrículas
                </Link>
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Matrícula nº {matricula.id}
                    </h1>
                    <p className="text-xs text-muted-foreground">
                        Dados pessoais: esta abertura fica registrada no
                        histórico (LGPD).
                    </p>
                </div>

                {/* Pares em 1 coluna no celular e 2 (nome | valor) na tela larga. */}
                <dl className="grid gap-x-4 gap-y-2 rounded-md border border-border p-4 text-sm sm:grid-cols-[auto_1fr]">
                    {linhas
                        .filter(([, valor]) => valor !== null && valor !== '')
                        .map(([rotulo, valor]) => (
                            <div key={rotulo} className="contents">
                                <dt className="text-muted-foreground">
                                    {rotulo}
                                </dt>
                                {/* overflow-wrap: e-mail comprido quebra a linha (sem rolagem lateral). */}
                                <dd className="mb-2 font-medium [overflow-wrap:anywhere] sm:mb-0">
                                    {valor}
                                </dd>
                            </div>
                        ))}
                </dl>

                {matricula.comprovante_url && (
                    <a
                        href={matricula.comprovante_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm underline underline-offset-4"
                    >
                        Ver o comprovante do PIX
                    </a>
                )}
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
FichaMatricula.layout = {
    breadcrumbs: [{ title: 'Matrículas', href: index() }],
};
