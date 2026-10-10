/**
 * PAINEL "Dados para pagamento deste curso" na edição do curso (#41; pedido
 * do Gleyson em 10/10/2026). Só aparece para quem tem "pagamentos.configurar"
 * (o servidor manda null para os outros). Formulário SEPARADO do curso: quem
 * propõe o curso não muda para onde vai o dinheiro.
 * Vazio = o curso usa os dados do IBTO. Os dados daqui só valem com a chave
 * "Permitir dados por curso" LIGADA (tela Dados para pagamento).
 */
import { Form, Link } from '@inertiajs/react';
import PagamentoConfigController from '@/actions/App/Http/Controllers/Cursos/PagamentoConfigController';
import { Button } from '@/components/ui/button';
import { CampoPagamento } from '@/pages/cursos/pagamento';
import { edit as telaPagamento } from '@/routes/comando-geral/pagamento';

/** Os dados próprios do curso e o estado da chave. */
export type PagamentoDoCurso = {
    pix_chave: string | null;
    favorecido: string | null;
    banco: string | null;
    agencia: string | null;
    conta: string | null;
    // A chave liga/desliga do Comando Geral.
    por_curso_ligado: boolean;
};

/**
 * @param cursoId O curso. Ex.: 7
 * @param dados Os dados atuais do curso e a chave.
 * @returns O painel com o formulário.
 */
export default function PainelPagamentoCurso({
    cursoId,
    dados,
}: {
    cursoId: number;
    dados: PagamentoDoCurso;
}) {
    return (
        <section
            aria-labelledby="t-pag-curso"
            className="flex flex-col gap-4 rounded-md border border-border p-4"
        >
            <div>
                <h2 id="t-pag-curso" className="font-medium">
                    Dados para pagamento deste curso
                </h2>
                {/* Aviso escrito do estado da chave (não só cor). */}
                <p className="text-xs text-muted-foreground">
                    {dados.por_curso_ligado
                        ? 'A chave "por curso" está LIGADA: com a chave PIX preenchida, o aluno vê estes dados. Vazio = usa os do IBTO.'
                        : 'A chave "por curso" está DESLIGADA: estes dados ficam guardados, mas o aluno vê os do IBTO.'}{' '}
                    <Link
                        href={telaPagamento()}
                        className="underline underline-offset-4"
                    >
                        Mudar a chave
                    </Link>
                </p>
            </div>

            <Form
                {...PagamentoConfigController.curso.form(cursoId)}
                options={{ preserveScroll: true }}
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <CampoPagamento
                            nome="pix_chave"
                            rotulo="Chave PIX do curso"
                            valor={dados.pix_chave}
                            maximo={140}
                            exemplo="Vazio = usa a do IBTO"
                            erro={errors.pix_chave}
                        />
                        <CampoPagamento
                            nome="favorecido"
                            rotulo="Favorecido"
                            valor={dados.favorecido}
                            maximo={150}
                            exemplo="Ex.: Equipe Vulcan"
                            erro={errors.favorecido}
                        />
                        {/* Banco, agência e conta: 1 coluna no celular, 3 na tela larga. */}
                        <div className="grid gap-4 sm:grid-cols-3">
                            <CampoPagamento
                                nome="banco"
                                rotulo="Banco"
                                valor={dados.banco}
                                maximo={100}
                                exemplo="Ex.: 260 - Nubank"
                                erro={errors.banco}
                            />
                            <CampoPagamento
                                nome="agencia"
                                rotulo="Agência"
                                valor={dados.agencia}
                                maximo={20}
                                exemplo="Ex.: 0001"
                                erro={errors.agencia}
                            />
                            <CampoPagamento
                                nome="conta"
                                rotulo="Conta"
                                valor={dados.conta}
                                maximo={30}
                                exemplo="Ex.: 1234567-8"
                                erro={errors.conta}
                            />
                        </div>
                        <Button
                            className="min-h-11 w-full sm:w-fit"
                            disabled={processing}
                        >
                            Salvar dados do curso
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
