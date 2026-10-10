/**
 * PAINEL DA SITUAÇÃO do curso (#40, chancela): mostra a situação atual, quando
 * foi chancelado, o motivo de uma rejeição ou cancelamento e SÓ os botões que
 * esta pessoa pode usar agora (a lista vem do servidor: FluxoCurso::acoesPara).
 * Ações que exigem motivo (rejeitar, cancelar) abrem uma janela para escrever.
 * Responsivo: botões em grade de 2 colunas no celular.
 */
import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import CursoController from '@/actions/App/Http/Controllers/Cursos/CursoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Uma ação do fluxo. Ex.: { acao: 'rejeitar', rotulo: 'Rejeitar', motivo: true } */
type AcaoFluxo = { acao: string; rotulo: string; motivo: boolean };

/** O que o painel precisa saber do curso. */
export type DadosSituacao = {
    id: number;
    situacao: string;
    situacao_rotulo: string;
    motivo_situacao: string | null;
    chancelado_em: string | null;
    pode_editar: boolean;
    acoes: AcaoFluxo[];
};

// Uma frase que explica cada situação para quem está olhando.
const EXPLICACAO: Record<string, string> = {
    rascunho:
        'Só quem cadastrou e o IBTO veem. Complete os dados e o conteúdo e envie para a chancela.',
    em_analise:
        'Enviado para a chancela do IBTO. Enquanto isso, os dados ficam travados.',
    publicado: 'Chancelado pelo IBTO: aparece para os alunos.',
    rejeitado: 'Devolvido pelo IBTO para ajustes. Corrija e envie de novo.',
    cancelado: 'Cancelado: não aparece para os alunos.',
    encerrado:
        'Encerrado: saiu do catálogo; histórico e certificados continuam.',
};

/**
 * Uma ação do fluxo: sem motivo, é um botão que envia na hora; com motivo,
 * abre uma janela para escrever o porquê.
 *
 * @param cursoId O curso. Ex.: 7
 * @param acao A ação. Ex.: { acao: 'cancelar', rotulo: 'Cancelar curso', motivo: true }
 */
function BotaoAcao({ cursoId, acao }: { cursoId: number; acao: AcaoFluxo }) {
    const [aberta, setAberta] = useState(false);
    // "Rejeitar" e "Cancelar" são vermelhos (não têm volta fácil); os outros, normais.
    const variante = acao.motivo
        ? 'destructive'
        : acao.acao === 'chancelar'
          ? 'default'
          : 'outline';

    // Sem motivo: um formulário só com o botão.
    if (!acao.motivo) {
        return (
            <Form
                {...CursoController.situacao.form(cursoId)}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <>
                        <input type="hidden" name="acao" value={acao.acao} />
                        <Button
                            type="submit"
                            variant={variante}
                            disabled={processing}
                            className="w-full"
                        >
                            {acao.rotulo}
                        </Button>
                    </>
                )}
            </Form>
        );
    }

    // Com motivo: janela com o campo.
    return (
        <Dialog open={aberta} onOpenChange={setAberta}>
            <DialogTrigger asChild>
                <Button variant={variante} className="w-full">
                    {acao.rotulo}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{acao.rotulo}</DialogTitle>
                <DialogDescription>
                    Explique o motivo: ele aparece para quem cadastrou o curso e
                    fica no histórico.
                </DialogDescription>
                <Form
                    {...CursoController.situacao.form(cursoId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setAberta(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="acao"
                                value={acao.acao}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor={`motivo-${acao.acao}`}>
                                    Motivo
                                </Label>
                                <Input
                                    id={`motivo-${acao.acao}`}
                                    name="motivo"
                                    maxLength={500}
                                    placeholder="Ex.: falta a carga horária"
                                />
                                <InputError message={errors.acao} />
                            </div>
                            <Button
                                type="submit"
                                variant={variante}
                                disabled={processing}
                                className="w-full sm:w-auto"
                            >
                                Confirmar
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * @param curso A situação, o motivo, a data da chancela e as ações permitidas.
 * @returns O painel.
 */
export default function PainelSituacao({ curso }: { curso: DadosSituacao }) {
    // Mensagem da última ação (ex.: 'Curso agora: Em análise.') e erro de regra, se houver.
    const { sucesso, errors } = usePage().props;
    const erroAcao = (errors as Record<string, string> | undefined)?.acao;
    // O motivo só aparece quando ainda faz sentido (rejeitado ou cancelado).
    const mostrarMotivo =
        curso.motivo_situacao &&
        ['rejeitado', 'cancelado'].includes(curso.situacao);

    return (
        <section
            aria-labelledby="titulo-situacao"
            className="flex flex-col gap-3 rounded-md border border-border p-4"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="titulo-situacao" className="font-semibold">
                    Situação: {curso.situacao_rotulo}
                </h2>
                {curso.chancelado_em && (
                    <span className="text-xs text-muted-foreground">
                        Chancelado em {curso.chancelado_em}
                    </span>
                )}
            </div>
            <p className="text-sm text-muted-foreground">
                {EXPLICACAO[curso.situacao]}
            </p>

            {mostrarMotivo && (
                <p className="rounded-md border border-amber-600/40 bg-amber-600/10 px-3 py-2 text-sm">
                    <strong className="font-medium">Motivo:</strong>{' '}
                    {curso.motivo_situacao}
                </p>
            )}
            {sucesso && (
                <p
                    role="status"
                    className="rounded-md border border-green-600/40 bg-green-600/10 px-3 py-2 text-sm"
                >
                    {sucesso}
                </p>
            )}
            {erroAcao && (
                <p
                    role="alert"
                    className="rounded-md border border-red-600/40 bg-red-600/10 px-3 py-2 text-sm"
                >
                    {erroAcao}
                </p>
            )}

            {/* Só os botões que esta pessoa pode usar agora (o servidor confere de novo). */}
            {curso.acoes.length > 0 && (
                <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    {curso.acoes.map((acao) => (
                        <BotaoAcao
                            key={acao.acao}
                            cursoId={curso.id}
                            acao={acao}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}
