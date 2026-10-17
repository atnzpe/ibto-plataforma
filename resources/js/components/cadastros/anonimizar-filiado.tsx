/**
 * ANONIMIZAR O FILIADO (LGPD, #47): o quadro vermelho no fim da ficha, só para
 * quem cuida de TODOS os filiados. Fica fechado (detalhes) para ninguém clicar
 * sem querer. Ao abrir, pede a senha, o registro digitado de novo e o motivo.
 * NÃO TEM VOLTA: apaga nome, e-mail, CPF, nascimento, telefone, cidade e fotos.
 */
import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { anonimizar } from '@/routes/cadastros/filiados';

/**
 * @param id O número interno do filiado. Ex.: 42.
 * @param registro O registro, que a pessoa precisa digitar para confirmar. Ex.: 'IBTO-0042'.
 * @returns O quadro de anonimização.
 */
export default function AnonimizarFiliado({
    id,
    registro,
}: {
    id: number;
    registro: string;
}) {
    return (
        <details className="rounded-md border border-red-700/50 p-4 text-sm">
            <summary className="min-h-11 cursor-pointer content-center font-medium text-red-800 dark:text-red-300">
                Anonimizar a pedido do titular (LGPD)
            </summary>
            <div className="mt-3 flex flex-col gap-4">
                <p>
                    Apaga para sempre o nome, o e-mail, o CPF, o nascimento, o
                    telefone, a cidade e as fotos, desativa a conta e invalida a
                    carteirinha. Ficam só o registro, a equipe, o nível e os
                    números do financeiro e das presenças.{' '}
                    <strong>Não tem volta.</strong>
                </p>
                <Form
                    {...anonimizar.form(id)}
                    // Última confirmação do navegador antes de enviar.
                    onBefore={() =>
                        window.confirm(
                            `Anonimizar o filiado ${registro}? Não tem volta.`,
                        )
                    }
                    className="flex flex-col gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="anon-registro">
                                    Digite o registro {registro} para confirmar
                                </Label>
                                <Input
                                    id="anon-registro"
                                    name="registro"
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={errors.registro} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="anon-motivo">Motivo</Label>
                                <Input
                                    id="anon-motivo"
                                    name="motivo"
                                    placeholder="Ex.: pedido do titular por e-mail em 17/10/2026"
                                    required
                                    minLength={10}
                                    maxLength={200}
                                />
                                <InputError message={errors.motivo} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="anon-senha">Sua senha</Label>
                                <Input
                                    id="anon-senha"
                                    name="senha"
                                    type="password"
                                    autoComplete="current-password"
                                    required
                                />
                                <InputError message={errors.senha} />
                            </div>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                                className="self-start"
                            >
                                Anonimizar para sempre
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </details>
    );
}
