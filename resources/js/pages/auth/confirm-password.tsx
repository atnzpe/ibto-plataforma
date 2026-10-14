/**
 * CONFIRMAR A SENHA (área segura). Aparece antes de telas sensíveis, como os
 * DADOS PARA PAGAMENTO (#41; Gleyson, 10/10/2026: "tela bem segura"): mesmo
 * logada, a pessoa digita a senha (ou usa a passkey) de novo. Assim, um
 * computador esquecido aberto não basta para trocar o PIX.
 * Traduzida do kit inicial (inglês) para o português.
 */
import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/passkey-verify';

/**
 * @returns A tela: confirmar com a passkey ou com a senha.
 */
export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirme a sua senha" />

            {/* Passkey (digital, rosto ou chave de segurança), se a pessoa tiver uma. */}
            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="Confirmar com a passkey"
                loadingLabel="Confirmando..."
                separator="Ou confirme com a senha"
            />

            {/* resetOnSuccess: o campo da senha é limpo depois de enviado. */}
            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Senha</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Sua senha"
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="min-h-11 w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                Confirmar a senha
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

// Título e explicação mostrados pela moldura das telas de entrar.
ConfirmPassword.layout = {
    title: 'Confirme a sua senha',
    description:
        'Esta é uma área segura da plataforma. Digite a sua senha de novo para continuar.',
};
