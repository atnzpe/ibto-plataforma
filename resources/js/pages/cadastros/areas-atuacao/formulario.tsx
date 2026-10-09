/**
 * Tela de FORMULÁRIO da área de atuação (#37): serve para cadastrar uma nova
 * (area = null) e para editar uma existente, inclusive ativar ou desativar.
 * Quem confere os dados de verdade é o servidor (AreaAtuacaoRequest); os erros
 * voltam em português e aparecem embaixo de cada campo.
 */
import { Form, Head, Link } from '@inertiajs/react';
import AreaAtuacaoController from '@/actions/App/Http/Controllers/Cadastros/AreaAtuacaoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/cadastros/areas-atuacao';
import type { AreaAtuacao } from '@/types/cadastros';

/**
 * @param area A área a editar, ou null para cadastrar uma nova. Ex.: { id: 4, nome: 'Krav Maga', ativa: true }
 * @returns A tela do formulário.
 */
export default function AreaAtuacaoFormulario({
    area,
}: {
    area: AreaAtuacao | null;
}) {
    // true quando estamos editando; false quando é uma área nova.
    const editando = area !== null;

    // Título da tela, que muda conforme o caso.
    const titulo = editando ? `Editar "${area.nome}"` : 'Nova área de atuação';

    // Para onde o formulário envia: "atualizar" (PUT) na edição, "gravar" (POST) na nova.
    const destino = editando
        ? AreaAtuacaoController.update.form(area.id)
        : AreaAtuacaoController.store.form();

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {titulo}
                </h1>

                <Form {...destino} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="nome">Nome da área</Label>
                                <Input
                                    id="nome"
                                    name="nome"
                                    defaultValue={area?.nome ?? ''}
                                    required
                                    maxLength={100}
                                    placeholder="Ex.: Tiro Defensivo"
                                    // Liga o campo à mensagem de erro, para o leitor de tela ler junto.
                                    aria-describedby={
                                        errors.nome ? 'erro-nome' : undefined
                                    }
                                    aria-invalid={
                                        errors.nome ? true : undefined
                                    }
                                />
                                <InputError
                                    id="erro-nome"
                                    message={errors.nome}
                                />
                            </div>

                            <div className="grid gap-2">
                                {/* O campo escondido manda "0" quando a caixa está desmarcada:
                                    caixa desmarcada não é enviada pelo navegador, e o servidor
                                    exige "ativa". Marcada, o "1" da caixa substitui o "0". */}
                                <input type="hidden" name="ativa" value="0" />
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="ativa"
                                        value="1"
                                        // Nova área nasce ativa; na edição, vem como está no banco.
                                        defaultChecked={area?.ativa ?? true}
                                        className="size-4"
                                    />
                                    Ativa (aparece nas listas de escolha)
                                </label>
                                <InputError message={errors.ativa} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="imagem">Imagem da área</Label>
                                {/* Imagem atual (na edição), para a pessoa ver o que vai trocar. */}
                                {area?.imagem_url && (
                                    <img
                                        src={area.imagem_url}
                                        alt={`Imagem atual de ${area.nome}`}
                                        className="h-32 w-48 rounded-md border border-border object-cover"
                                    />
                                )}
                                {/* accept: o seletor de arquivos já mostra só imagens
                                    (a conferência de verdade é no servidor). */}
                                <Input
                                    id="imagem"
                                    name="imagem"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    aria-describedby="dica-imagem"
                                />
                                <p
                                    id="dica-imagem"
                                    className="text-sm text-muted-foreground"
                                >
                                    JPG, PNG ou WEBP, até 2 MB.
                                    {editando &&
                                        ' Deixe em branco para manter a imagem atual.'}
                                </p>
                                <InputError message={errors.imagem} />
                            </div>

                            <div className="flex items-center gap-4">
                                {/* Desabilitado enquanto envia, para não gravar duas vezes. */}
                                <Button disabled={processing}>Salvar</Button>
                                <Link
                                    href={index()}
                                    className="text-sm underline underline-offset-4"
                                >
                                    Cancelar
                                </Link>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão"): volta para a lista.
AreaAtuacaoFormulario.layout = {
    breadcrumbs: [{ title: 'Áreas de atuação', href: index() }],
};
