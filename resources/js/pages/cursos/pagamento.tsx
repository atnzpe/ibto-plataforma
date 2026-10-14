/**
 * DADOS PARA PAGAMENTO no Comando Geral (#41; pedido do Gleyson em
 * 10/10/2026: "as duas opções e uma chave liga/desliga"):
 * - os dados do IBTO: PIX, favorecido, banco, agência, conta e instruções;
 * - a CHAVE "Permitir dados por curso": desligada = todo curso usa os do
 *   IBTO; ligada = o curso com dados próprios (preenchidos na edição do
 *   curso) usa os dele, e os outros continuam com os do IBTO.
 * Só quem tem "pagamentos.configurar". Cada troca vai para a auditoria.
 */
import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PagamentoConfigController from '@/actions/App/Http/Controllers/Cursos/PagamentoConfigController';
import InputError from '@/components/input-error';
import { CampoPagamento } from '@/components/cursos/campo-pagamento';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/comando-geral/pagamento';

/** Os dados do IBTO, como vêm do servidor. */
type Config = {
    pix_chave: string | null;
    favorecido: string | null;
    banco: string | null;
    agencia: string | null;
    conta: string | null;
    instrucoes: string | null;
    por_curso: boolean;
};

/**
 * @param config Os dados atuais do IBTO e a chave.
 * @param cursos_com_dados Quantos cursos têm dados próprios guardados.
 * @param ultima_alteracao Quem trocou por último e quando, ou null.
 * @returns A tela.
 */
export default function DadosPagamento({
    config,
    cursos_com_dados,
    ultima_alteracao,
}: {
    config: Config;
    cursos_com_dados: number;
    ultima_alteracao: {
        quem: string;
        quando: string | null;
        onde: string;
    } | null;
}) {
    const { sucesso } = usePage().props;
    // A chave liga/desliga (o texto de explicação muda junto).
    const [porCurso, setPorCurso] = useState(config.por_curso);

    return (
        <>
            <Head title="Dados para pagamento" />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Dados para pagamento
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        O que o aluno vê para pagar a matrícula. Cada mudança
                        fica registrada no histórico (quem mudou, quando, de quê
                        para quê).
                    </p>
                </div>

                {/* Última alteração (de quem e quando): ajuda a perceber uma troca não combinada. */}
                {ultima_alteracao && (
                    <p className="rounded-md border border-border px-4 py-2 text-sm">
                        Última alteração:{' '}
                        <strong>{ultima_alteracao.quem}</strong> em{' '}
                        {ultima_alteracao.quando} ({ultima_alteracao.onde}).
                        Todos os responsáveis recebem um e-mail a cada troca.
                    </p>
                )}

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                <Form
                    {...PagamentoConfigController.update.form()}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* A CHAVE liga/desliga, em destaque no topo. */}
                            <fieldset className="flex flex-col gap-2 rounded-md border border-border p-4">
                                <legend className="px-1 font-medium">
                                    Dados por curso
                                </legend>
                                {/* "0" escondido: caixa desmarcada não é enviada pelo navegador. */}
                                <input
                                    type="hidden"
                                    name="por_curso"
                                    value="0"
                                />
                                <label className="flex min-h-11 items-center gap-3 text-sm">
                                    <input
                                        type="checkbox"
                                        name="por_curso"
                                        value="1"
                                        checked={porCurso}
                                        onChange={(evento) =>
                                            setPorCurso(evento.target.checked)
                                        }
                                        className="size-5"
                                    />
                                    <span className="font-medium">
                                        Permitir dados por curso:{' '}
                                        {porCurso ? 'LIGADO' : 'DESLIGADO'}
                                    </span>
                                </label>
                                <p className="text-xs text-muted-foreground">
                                    {porCurso
                                        ? 'Ligado: o curso que tiver dados próprios (na edição do curso) usa os dele; os outros usam os do IBTO abaixo.'
                                        : 'Desligado: todos os cursos usam os dados do IBTO abaixo. Os dados próprios dos cursos ficam guardados, mas não aparecem.'}{' '}
                                    {cursos_com_dados > 0 &&
                                        `Hoje ${cursos_com_dados} curso(s) têm dados próprios guardados.`}
                                </p>
                            </fieldset>

                            <fieldset className="grid gap-4">
                                <legend className="mb-1 font-medium">
                                    Dados do IBTO
                                </legend>
                                <CampoPagamento
                                    nome="pix_chave"
                                    rotulo="Chave PIX"
                                    valor={config.pix_chave}
                                    maximo={140}
                                    exemplo="Ex.: financeiro@ib-to.org ou o CNPJ"
                                    erro={errors.pix_chave}
                                />
                                <CampoPagamento
                                    nome="favorecido"
                                    rotulo="Favorecido"
                                    valor={config.favorecido}
                                    maximo={150}
                                    exemplo="Quem recebe"
                                    erro={errors.favorecido}
                                />
                                {/* Banco, agência e conta: 1 coluna no celular, 3 na tela larga. */}
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <CampoPagamento
                                        nome="banco"
                                        rotulo="Banco"
                                        valor={config.banco}
                                        maximo={100}
                                        exemplo="Ex.: 001 - Banco do Brasil"
                                        erro={errors.banco}
                                    />
                                    <CampoPagamento
                                        nome="agencia"
                                        rotulo="Agência"
                                        valor={config.agencia}
                                        maximo={20}
                                        exemplo="Ex.: 1234-5"
                                        erro={errors.agencia}
                                    />
                                    <CampoPagamento
                                        nome="conta"
                                        rotulo="Conta"
                                        valor={config.conta}
                                        maximo={30}
                                        exemplo="Ex.: 12345-6"
                                        erro={errors.conta}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="pag-instrucoes">
                                        Instruções para quem vai pagar
                                    </Label>
                                    <textarea
                                        id="pag-instrucoes"
                                        name="instrucoes"
                                        rows={3}
                                        maxLength={500}
                                        defaultValue={config.instrucoes ?? ''}
                                        placeholder="Ex.: Depois do PIX, envie o comprovante pela plataforma."
                                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring"
                                    />
                                    <InputError message={errors.instrucoes} />
                                </div>
                            </fieldset>

                            <Button
                                className="min-h-11 w-full sm:w-fit"
                                disabled={processing}
                            >
                                Salvar
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
DadosPagamento.layout = {
    breadcrumbs: [{ title: 'Dados para pagamento', href: edit() }],
};
