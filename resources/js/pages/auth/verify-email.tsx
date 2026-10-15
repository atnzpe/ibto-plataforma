/**
 * CONFIRME SEU E-MAIL: aparece para quem criou a conta e ainda não clicou no
 * link de confirmação. Dá para pedir o e-mail de novo ou sair. Textos em
 * português (tradução das telas do kit do Laravel, 14/10/2026, antes do piloto).
 */
// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Confirme seu e-mail" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    Enviamos um novo link de confirmação para o e-mail que você
                    informou no cadastro.
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            Enviar o e-mail de confirmação de novo
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            Sair
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Confirme seu e-mail',
    description:
        'Clique no link que acabamos de enviar para o seu e-mail para confirmar a conta.',
};
