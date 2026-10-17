/**
 * DETALHES DO CURSO no Comando Geral (tela 3 do desenho aprovado pelo
 * Gleyson em 10/10/2026): o resumo em passos (vagas → pagamento →
 * presença → certificado) e os INSCRITOS com o próximo passo de cada um.
 * LGPD: só o registro (ou o nº da matrícula) e a equipe; o nome fica na ficha.
 * Presença (#44): os encontros com o botão da chamada e o "x de y" de cada
 * inscrito. O certificado chega com a #44 ("em breve").
 * Sem rolagem lateral: resumo em grade e inscritos em cartões.
 */
import FotoPessoa from '@/components/foto-pessoa';
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { dataBr } from '@/pages/catalogo/index';
import {
    chamada,
    edit,
    index,
    matricular,
} from '@/routes/comando-geral/cursos';
import { ficha, index as matriculas } from '@/routes/comando-geral/matriculas';
// Certificados (#44): ver, emitir e cancelar.
import { ver as verCertificado } from '@/routes/certificados';
import { cancelar as cancelarCertificado } from '@/routes/comando-geral/certificados';
import {
    aptos as emitirAptos,
    emitir as emitirCertificado,
} from '@/routes/comando-geral/cursos/certificados';

/** Um inscrito na lista. */
type Inscrito = {
    id: number;
    // null = não filiado (aparece o nº da matrícula).
    registro: string | null;
    // Foto de perfil (#78), ou null sem foto (aparece um ícone).
    foto: string | null;
    equipe: string;
    situacao: 'aguardando_pagamento' | 'confirmada' | 'cancelada';
    situacao_rotulo: string;
    tem_comprovante: boolean;
    // Presenças: veio em X de Y encontros com chamada.
    presencas: number;
    encontros_com_chamada: number;
    // Certificado (#44): código e número (null = não emitido), se vale, e o motivo de não estar apto.
    certificado: string | null;
    certificado_id: number | null;
    certificado_valido: boolean;
    motivo_nao_apto: string | null;
};

// Cor de cada situação (o texto também aparece escrito).
const SELO: Record<Inscrito['situacao'], string> = {
    aguardando_pagamento:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    confirmada:
        'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100',
    cancelada: 'bg-muted text-muted-foreground',
};

/**
 * @param curso O curso (título, modalidade, data, local).
 * @param resumo Quantos em cada passo.
 * @param inscritos Todas as matrículas do curso (também as canceladas).
 * @param pode_confirmar true = pode abrir a ficha e confirmar o PIX.
 * @param encontros Os encontros (aulas com data), com a chamada feita ou não.
 * @param pode_chamada true = pode fazer a chamada (dono, admin da equipe, Comando Geral).
 * @param pode_emitir true = pode emitir e cancelar certificados (só o IBTO).
 * @param pode_matricular true = mostra "Matricular pessoa" (só o IBTO); false = o formulário nem aparece.
 * @param fechado true = curso FECHADO (fora do site; só entra quem o IBTO matricular).
 * @returns A tela.
 */
export default function DetalhesCurso({
    curso,
    resumo,
    inscritos,
    pode_confirmar,
    encontros,
    pode_chamada,
    pode_emitir,
    pode_matricular = false,
    fechado = false,
}: {
    curso: {
        id: number;
        titulo: string;
        modalidade: string;
        data_inicio: string | null;
        local: string | null;
        situacao_rotulo: string;
        pode_editar: boolean;
    };
    resumo: {
        vagas: number | null;
        ocupadas: number;
        aguardando: number;
        confirmadas: number;
        canceladas: number;
        // Presentes na última chamada (null = nenhuma) e o título do encontro.
        presentes_ultima: number | null;
        // Certificados válidos emitidos (passo 4).
        certificados: number;
        ultima_chamada: string | null;
    };
    inscritos: Inscrito[];
    pode_confirmar: boolean;
    encontros: {
        id: number;
        titulo: string;
        data: string | null;
        hora_inicio: string | null;
        chamada_feita: boolean;
    }[];
    pode_chamada: boolean;
    pode_emitir: boolean;
    pode_matricular?: boolean;
    fechado?: boolean;
}) {
    // Mensagem da última ação (ex.: "Chamada registrada: 9 de 12 presentes").
    const { sucesso, errors } = usePage().props;
    // Os cartões do resumo, na ordem dos passos do desenho.
    const passos: { passo: string; numero: string; texto: string }[] = [
        {
            passo: 'Vagas',
            numero:
                resumo.vagas === null
                    ? String(resumo.ocupadas)
                    : `${resumo.ocupadas} / ${resumo.vagas}`,
            texto: resumo.vagas === null ? 'ocupadas (sem limite)' : 'ocupadas',
        },
        {
            passo: '1 · Pagamento',
            numero: String(resumo.aguardando),
            texto: 'aguardando',
        },
        {
            passo: '2 · Vaga confirmada',
            numero: String(resumo.confirmadas),
            texto: 'pagamento confirmado',
        },
        {
            passo: '3 · Presença',
            // Presentes na última chamada (— = nenhuma chamada ainda).
            numero:
                resumo.presentes_ultima === null
                    ? '—'
                    : String(resumo.presentes_ultima),
            texto:
                resumo.ultima_chamada !== null
                    ? `presentes em "${resumo.ultima_chamada}"`
                    : 'nenhuma chamada ainda',
        },
        {
            passo: '4 · Certificado',
            numero: String(resumo.certificados),
            texto: 'emitidos',
        },
    ];

    return (
        <>
            <Head title={`Inscritos · ${curso.titulo}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {[
                                curso.modalidade,
                                curso.data_inicio && dataBr(curso.data_inicio),
                                curso.local,
                                curso.situacao_rotulo,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {curso.titulo}
                        </h1>
                    </div>
                    {/* Botões do topo: 2 iguais no celular, lado a lado na tela larga. */}
                    <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={edit(curso.id)}>
                                {curso.pode_editar
                                    ? 'Editar curso'
                                    : 'Ver o curso'}
                            </Link>
                        </Button>
                        {/* "Emitir certificados dos presentes" (desenho): todos os aptos de uma vez. Só o IBTO. */}
                        {pode_emitir && (
                            <Button
                                className="min-h-11"
                                onClick={() =>
                                    window.confirm(
                                        'Emitir o certificado de todos os inscritos aptos (presença mínima) que ainda não têm?',
                                    ) &&
                                    router.post(
                                        emitirAptos(curso.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Emitir certificados dos aptos
                            </Button>
                        )}
                    </div>
                </div>

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}
                {/* Certificado recusado (ex.: já emitido): o motivo com o código. */}
                {errors.certificado && (
                    <p
                        role="alert"
                        className="rounded-md border border-red-600/40 bg-red-600/10 px-4 py-2 text-sm"
                    >
                        {errors.certificado}
                    </p>
                )}

                {/* Resumo em passos: 2 colunas no celular, 5 na tela larga. */}
                <section
                    aria-label="Resumo do curso"
                    className="grid grid-cols-2 gap-3 lg:grid-cols-5"
                >
                    {passos.map((passo) => (
                        <div
                            key={passo.passo}
                            className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4"
                        >
                            <span className="text-xs text-muted-foreground">
                                {passo.passo}
                            </span>
                            <strong className="text-2xl font-semibold">
                                {passo.numero}
                            </strong>
                            <span className="text-xs text-muted-foreground">
                                {passo.texto}
                            </span>
                        </div>
                    ))}
                </section>

                {/* Encontros e chamada (#44): cada encontro com o botão da chamada. */}
                {encontros.length > 0 && (
                    <section
                        aria-labelledby="t-encontros"
                        className="flex flex-col gap-3"
                    >
                        <h2 id="t-encontros" className="font-semibold">
                            Encontros e chamada
                        </h2>
                        {/* 1 coluna no celular, 2 no tablet, 3 na tela larga. */}
                        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {encontros.map((encontro) => (
                                <li
                                    key={encontro.id}
                                    className="flex flex-col gap-2 rounded-md border border-border p-4 text-sm"
                                >
                                    <strong>{encontro.titulo}</strong>
                                    <span className="text-muted-foreground">
                                        {encontro.data && dataBr(encontro.data)}
                                        {encontro.hora_inicio &&
                                            ` às ${encontro.hora_inicio}`}
                                    </span>
                                    {/* Estado escrito (não só cor). */}
                                    <span className="text-xs font-medium">
                                        {encontro.chamada_feita
                                            ? '✓ Chamada feita'
                                            : 'Chamada ainda não feita'}
                                    </span>
                                    {pode_chamada && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant={
                                                encontro.chamada_feita
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            className="mt-auto min-h-11"
                                        >
                                            <Link
                                                href={chamada({
                                                    curso: curso.id,
                                                    aula: encontro.id,
                                                })}
                                            >
                                                {encontro.chamada_feita
                                                    ? 'Refazer a chamada'
                                                    : 'Fazer a chamada'}
                                            </Link>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                {/* "MATRICULAR PESSOA" (14/10/2026): só o IBTO. O único jeito de entrar
                    num curso FECHADO; também serve para quem pagou fora do site. */}
                {pode_matricular && (
                    <section
                        aria-labelledby="t-matricular"
                        className="flex flex-col gap-3 rounded-xl border border-border p-4"
                    >
                        <h2 id="t-matricular" className="font-semibold">
                            Matricular pessoa
                        </h2>
                        {/* Curso fechado: lembra por que este formulário importa. */}
                        {fechado && (
                            <p className="text-sm text-muted-foreground">
                                Este curso é fechado: não aparece no site e só
                                entra quem for matriculado aqui.
                            </p>
                        )}
                        {/* resetOnSuccess: limpa o campo depois de matricular, pronto para a próxima pessoa. */}
                        <Form
                            {...matricular.form(curso.id)}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="flex flex-col gap-2 sm:flex-row sm:items-end"
                        >
                            {({ processing }) => (
                                <>
                                    <div className="grid flex-1 gap-2">
                                        <Label htmlFor="pessoa">
                                            Registro do filiado ou e-mail da
                                            conta
                                        </Label>
                                        <Input
                                            id="pessoa"
                                            name="pessoa"
                                            maxLength={150}
                                            required
                                            autoComplete="off"
                                            placeholder="Ex.: BR20260007PE"
                                            aria-describedby="erro-matricular"
                                        />
                                    </div>
                                    {/* Motivo da exceção (#88): só exigido se a pessoa não cumpre os pré-requisitos. */}
                                    <div className="grid gap-1">
                                        <Label htmlFor="motivo-excecao">
                                            Motivo (se a pessoa não cumpre os
                                            pré-requisitos)
                                        </Label>
                                        <Input
                                            id="motivo-excecao"
                                            name="motivo"
                                            maxLength={500}
                                            autoComplete="off"
                                            placeholder="Ex.: instrutor experiente vindo de outra federação"
                                        />
                                    </div>
                                    {/* processing: trava o botão enquanto o servidor responde (evita clique duplo). */}
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-h-11"
                                    >
                                        Matricular
                                    </Button>
                                </>
                            )}
                        </Form>
                        {/* O motivo da recusa, com o código (ex.: VAGAS_ESGOTADAS). */}
                        <InputError
                            id="erro-matricular"
                            message={errors.matricular}
                        />
                    </section>
                )}

                <section
                    aria-labelledby="t-inscritos"
                    className="flex flex-col gap-3"
                >
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="t-inscritos" className="font-semibold">
                            Inscritos ({inscritos.length})
                        </h2>
                        <span className="text-xs text-muted-foreground">
                            Só o registro (LGPD).{' '}
                            {resumo.canceladas > 0 &&
                                `${resumo.canceladas} cancelada(s) no histórico.`}
                        </span>
                    </div>

                    {inscritos.length === 0 && (
                        <p className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                            Ninguém se inscreveu ainda.
                        </p>
                    )}

                    {/* Um cartão por inscrito: 1 coluna no celular, 2 no tablet, 3 na tela larga. */}
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {inscritos.map((inscrito) => (
                            <li
                                key={inscrito.id}
                                className="flex flex-col gap-2 rounded-md border border-border p-4 text-sm"
                            >
                                <span className="flex flex-wrap items-center gap-2">
                                    {/* Foto (#78), quando quem vê tem permissão. */}
                                    <FotoPessoa
                                        foto={inscrito.foto}
                                        rotulo={`Foto de ${inscrito.registro ?? `matrícula nº ${inscrito.id}`}`}
                                    />
                                    <strong className="font-mono">
                                        {inscrito.registro ??
                                            `Matrícula nº ${inscrito.id}`}
                                    </strong>
                                    <span
                                        className={`rounded px-1.5 py-0.5 text-xs font-medium ${SELO[inscrito.situacao]}`}
                                    >
                                        {inscrito.situacao_rotulo}
                                    </span>
                                </span>
                                <span className="text-muted-foreground">
                                    {inscrito.equipe}
                                </span>

                                {/* O próximo passo de cada um. */}
                                {inscrito.situacao ===
                                    'aguardando_pagamento' && (
                                    <span className="text-xs">
                                        Próximo passo:{' '}
                                        {inscrito.tem_comprovante
                                            ? 'conferir o comprovante e confirmar o pagamento'
                                            : 'aguardar o comprovante do PIX'}
                                    </span>
                                )}
                                {inscrito.situacao === 'confirmada' && (
                                    <span className="text-xs">
                                        {/* Presença escrita: veio em X de Y encontros com chamada. */}
                                        {inscrito.encontros_com_chamada > 0
                                            ? `Presença: ${inscrito.presencas} de ${inscrito.encontros_com_chamada} encontro(s)`
                                            : 'Próximo passo: presença nos encontros'}
                                    </span>
                                )}

                                {/* Certificado (#44): o código (com o link) ou o motivo de ainda não estar apto. */}
                                {inscrito.certificado !== null && (
                                    <span className="flex flex-wrap items-center gap-2 text-xs">
                                        <Link
                                            href={verCertificado(
                                                inscrito.certificado,
                                            )}
                                            className="font-mono underline underline-offset-4"
                                        >
                                            {inscrito.certificado}
                                        </Link>
                                        <span className="font-medium">
                                            {inscrito.certificado_valido
                                                ? '✓ Certificado válido'
                                                : '✗ Certificado cancelado'}
                                        </span>
                                    </span>
                                )}
                                {inscrito.certificado === null &&
                                    inscrito.motivo_nao_apto !== null && (
                                        <span className="text-xs text-muted-foreground">
                                            Certificado:{' '}
                                            {inscrito.motivo_nao_apto}
                                        </span>
                                    )}
                                {/* Emitir (apto e sem certificado) ou cancelar (válido): só o IBTO. */}
                                {pode_emitir &&
                                    inscrito.situacao === 'confirmada' &&
                                    inscrito.certificado === null &&
                                    inscrito.motivo_nao_apto === null && (
                                        <Button
                                            size="sm"
                                            className="min-h-11"
                                            onClick={() =>
                                                router.post(
                                                    emitirCertificado({
                                                        curso: curso.id,
                                                        matricula: inscrito.id,
                                                    }).url,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Emitir certificado
                                        </Button>
                                    )}
                                {pode_emitir &&
                                    inscrito.certificado !== null &&
                                    inscrito.certificado_valido &&
                                    inscrito.certificado_id !== null && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="min-h-11"
                                            onClick={() => {
                                                const motivo = window.prompt(
                                                    `Cancelar o certificado ${inscrito.certificado}? Ele deixa de validar. Escreva o motivo:`,
                                                );
                                                // Fechou ou deixou em branco: não cancela.
                                                if (
                                                    motivo !== null &&
                                                    motivo.trim() !== ''
                                                ) {
                                                    router.post(
                                                        cancelarCertificado(
                                                            inscrito.certificado_id!,
                                                        ).url,
                                                        { motivo },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }
                                            }}
                                        >
                                            Cancelar certificado
                                        </Button>
                                    )}

                                {/* Ficha (nome) e confirmação: só para quem confirma pagamentos. */}
                                {pode_confirmar && (
                                    <div className="mt-auto grid grid-cols-2 gap-2 pt-1">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                            className="min-h-11"
                                        >
                                            <Link href={ficha(inscrito.id)}>
                                                Abrir ficha
                                            </Link>
                                        </Button>
                                        {inscrito.situacao ===
                                        'aguardando_pagamento' ? (
                                            <Button
                                                asChild
                                                size="sm"
                                                className="min-h-11"
                                            >
                                                <Link
                                                    href={matriculas({
                                                        query: {
                                                            curso: curso.id,
                                                            situacao:
                                                                'aguardando_pagamento',
                                                        },
                                                    })}
                                                >
                                                    Confirmar pagamento
                                                </Link>
                                            </Button>
                                        ) : (
                                            <span aria-hidden="true" />
                                        )}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
DetalhesCurso.layout = {
    breadcrumbs: [{ title: 'Cursos', href: index() }],
};
