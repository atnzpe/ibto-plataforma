/**
 * Tela do CONVITE do filiado (#37, bloco F3): a pessoa abriu o link do e-mail
 * e cria a própria senha. Depois de salvar, ela já entra logada.
 * Usa o mesmo visual das telas de entrar (layout "auth").
 */
import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/**
 * @param nome O nome da pessoa convidada. Ex.: 'Fulano'
 * @param acao Para onde o formulário envia: o mesmo endereço assinado do convite.
 * @returns A tela "crie a sua senha".
 */
export default function Convite({
    nome,
    acao,
}: {
    nome: string;
    acao: string;
}) {
    return (
        <>
            <Head title="Criar minha senha" />

            <p className="text-center text-sm text-muted-foreground">
                Olá, {nome}! Crie a sua senha para entrar na plataforma do IBTO.
            </p>

            {/* action = o endereço assinado; method POST. resetOnSuccess limpa as senhas da tela. */}
            <Form
                action={acao}
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Senha</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                required
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Repita a senha
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                            />
                        </div>

                        {/* Desabilitado enquanto envia, para não gravar duas vezes. */}
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Criar minha senha e entrar
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

// Título e descrição do layout "auth" (o mesmo das telas de entrar).
Convite.layout = {
    title: 'Criar minha senha',
    description: 'Convite de filiado do IBTO',
};
