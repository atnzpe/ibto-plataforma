/**
 * ENTRAR (tela de login): e-mail e senha, "Lembrar de mim", o link de
 * "Esqueci minha senha" e o de criar conta. Também aceita a chave de acesso
 * (passkey) pelo componente PasskeyVerify. Textos em português (tradução das
 * telas do kit do Laravel, 14/10/2026, antes do piloto).
 * Acessibilidade (18/10/2026, auditoria axe): sem tabindex forçado; a ordem do
 * teclado é a ordem natural da tela (quem usa leitor de tela não se perde).
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
    // true = mostra "Criar conta" (cadastro público com aprovação, #84). false = só por convite.
    canRegister: boolean;
};

/**
 * @param status Aviso do servidor (aparece em verde embaixo), ou vazio.
 * @param canResetPassword true = mostra "Esqueci minha senha".
 * @param canRegister true = mostra "Pedir acesso" (com o cadastro público fechado, some).
 * @returns A tela de entrar.
 */
export default function Login({
    status,
    canResetPassword,
    canRegister,
}: Props) {
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
                                        >
                                            Esqueci minha senha
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                    placeholder="Sua senha"
                                />
                                <InputError message={errors.password} />
                            </div>

                            {/* Lembrar de mim: continua conectado neste aparelho por mais tempo. */}
                            <div className="flex items-center space-x-3">
                                <Checkbox id="remember" name="remember" />
                                <Label htmlFor="remember">Lembrar de mim</Label>
                            </div>

                            {/* processing: trava o botão enquanto o servidor confere (evita clique duplo). */}
                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Entrar
                            </Button>
                        </div>

                        {/* Grupo fechado (#84): quem foi indicado pede acesso e espera a aprovação.
                            Com o cadastro público fechado, o link some (só convite). */}
                        {canRegister && (
                            <div className="text-center text-sm text-muted-foreground">
                                Foi indicado ao IBTO?{' '}
                                <TextLink href={register()}>
                                    Pedir acesso
                                </TextLink>
                            </div>
                        )}
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
