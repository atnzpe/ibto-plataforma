/**
 * ENTRAR (tela de login): e-mail e senha, "Lembrar de mim", o link de
 * "Esqueci minha senha" e o de criar conta. Também aceita a chave de acesso
 * (passkey) pelo componente PasskeyVerify. Textos em português (tradução das
 * telas do kit do Laravel, 14/10/2026, antes do piloto).
 */
import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/passkey-verify';

type Props = {
    // Aviso vindo do servidor. Ex.: "Enviamos o link para criar uma senha nova."
    status?: string;
    // true = mostra o link "Esqueci minha senha".
    canResetPassword: boolean;
};

/**
 * @param status Aviso do servidor (aparece em verde embaixo), ou vazio.
 * @param canResetPassword true = mostra "Esqueci minha senha".
 * @returns A tela de entrar.
 */
export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Entrar" />

            <PasskeyVerify />

            {/* resetOnSuccess: depois de entrar, a senha não fica guardada no campo. */}
            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="seu-email@exemplo.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Senha</Label>
                                    {/* Só aparece se o servidor permitir trocar a senha por e-mail. */}
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Esqueci minha senha
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Sua senha"
                                />
                                <InputError message={errors.password} />
                            </div>

                            {/* Lembrar de mim: continua conectado neste aparelho por mais tempo. */}
                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Lembrar de mim</Label>
                            </div>

                            {/* processing: trava o botão enquanto o servidor confere (evita clique duplo). */}
                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Entrar
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Ainda não tem conta?{' '}
                            <TextLink href={register()} tabIndex={5}>
                                Criar conta
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

// Título e frase do topo da tela (desenhados pelo layout de autenticação).
Login.layout = {
    title: 'Entrar na sua conta',
    description: 'Digite seu e-mail e sua senha para entrar',
};
