/**
 * PEDIR ACESSO (cadastro com aprovação, #84): nome, e-mail, a EQUIPE, QUEM
 * INDICOU e a senha. O IBTO é um grupo fechado, só de indicados: a conta nasce
 * "em análise" e só entra depois que o admin da equipe ou o Comando Geral
 * aprovar. Precisa confirmar o e-mail. Textos em português.
 * Acessibilidade (18/10/2026, auditoria axe): sem tabindex forçado; a ordem do
 * teclado é a ordem natural da tela (quem usa leitor de tela não se perde).
 */
import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

// Uma equipe da lista. Ex.: { id: 3, rotulo: 'IBTO Pernambuco (Recife - PE)' }
type EquipeDaLista = { id: number; rotulo: string };

type Props = {
    passwordRules: string;
    // As equipes que recebem pedidos de acesso (todas, menos as inativas).
    equipes: EquipeDaLista[];
};

/**
 * A tela "Pedir acesso".
 *
 * @param passwordRules As regras de senha forte (para o gerenciador de senhas do navegador).
 * @param equipes As equipes para escolher.
 * @returns O formulário de pedido de acesso.
 */
export default function Register({ passwordRules, equipes }: Props) {
    return (
        <>
            <Head title="Pedir acesso" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome completo</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Seu nome completo"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    name="email"
                                    placeholder="seu-email@exemplo.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            {/* A equipe define quem analisa o pedido (o admin daquela equipe). */}
                            <div className="grid gap-2">
                                <Label htmlFor="equipe_id">Sua equipe</Label>
                                <select
                                    id="equipe_id"
                                    name="equipe_id"
                                    required
                                    defaultValue=""
                                    className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring"
                                >
                                    <option value="" disabled>
                                        Escolha a sua equipe
                                    </option>
                                    {equipes.map((equipe) => (
                                        <option
                                            key={equipe.id}
                                            value={equipe.id}
                                        >
                                            {equipe.rotulo}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.equipe_id} />
                            </div>

                            {/* Quem indicou: ajuda quem analisa a confirmar o pedido. */}
                            <div className="grid gap-2">
                                <Label htmlFor="indicado_por">
                                    Quem indicou você ao IBTO?
                                </Label>
                                <Input
                                    id="indicado_por"
                                    type="text"
                                    required
                                    minLength={3}
                                    maxLength={150}
                                    name="indicado_por"
                                    placeholder="Ex.: Mestre Trinca"
                                />
                                <InputError message={errors.indicado_por} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Senha</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Crie uma senha"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Repita a senha
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Digite a senha de novo"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Pedir acesso
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Já tem conta?{' '}
                            <TextLink href={login()}>Entrar</TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Pedir acesso',
    description:
        'O IBTO é um grupo fechado, só de indicados. Depois de enviar, o admin da sua equipe analisa o pedido.',
};
