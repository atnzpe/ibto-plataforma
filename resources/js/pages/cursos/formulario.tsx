/**
 * FORMULÁRIO do curso no Comando Geral (#39 / #38): cadastrar (curso = null)
 * ou editar. Os campos de data e local aparecem destacados no presencial.
 * As regras de verdade são conferidas no servidor (CursoRequest): três
 * modalidades de acesso (gratuito é só para filiados), presencial exige data
 * e local, valor em centavos.
 * Responsivo: campos em grade que vira uma coluna no celular.
 */
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import CursoController from '@/actions/App/Http/Controllers/Cursos/CursoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/comando-geral/cursos';

/** Uma opção de lista fixa. Ex.: { valor: 'online', rotulo: 'Online' } */
type Opcao = { valor: string; rotulo: string };

/** O curso aberto para edição (valores já no formato dos campos). */
type CursoEdicao = {
    id: number;
    titulo: string;
    descricao: string | null;
    area_atuacao_id: number | null;
    modalidade: string;
    acesso: string;
    valor: string;
    vagas: number | null;
    data_inicio: string | null;
    data_fim: string | null;
    hora_inicio: string | null;
    hora_fim: string | null;
    local: string | null;
    carga_horaria: number | null;
    instrutor_id: number | null;
    link_pagamento: string | null;
    situacao: string;
    imagem_url: string | null;
};

// Visual comum das caixas de escolha (select), igual ao dos campos de texto.
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * @param curso O curso a editar, ou null para cadastrar um novo.
 * @param areas As áreas de atuação ativas.
 * @param instrutores As contas com o papel Instrutor.
 * @param modalidades Presencial e online.
 * @param acessos Aberto ao público e só filiados.
 * @param situacoes Rascunho, publicado e encerrado.
 * @returns A tela do formulário.
 */
export default function CursoFormulario({
    curso,
    areas,
    instrutores,
    modalidades,
    acessos,
    situacoes,
}: {
    curso: CursoEdicao | null;
    areas: { id: number; nome: string }[];
    instrutores: { id: number; name: string }[];
    modalidades: Opcao[];
    acessos: Opcao[];
    situacoes: Opcao[];
}) {
    const editando = curso !== null;
    const titulo = editando ? `Editar "${curso.titulo}"` : 'Novo curso';

    // Modalidade escolhida agora: no presencial, data e local são obrigatórios.
    const [modalidade, setModalidade] = useState(
        curso?.modalidade ?? 'presencial',
    );
    const presencial = modalidade === 'presencial';

    // Para onde o formulário envia: atualizar (edição) ou gravar (novo).
    const destino = editando
        ? CursoController.update.form(curso.id)
        : CursoController.store.form();

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {titulo}
                </h1>

                <Form {...destino} className="space-y-8">
                    {({ processing, errors }) => (
                        <>
                            {/* ---------- O curso ---------- */}
                            <fieldset className="grid gap-4">
                                <legend className="mb-2 font-medium">
                                    O curso
                                </legend>
                                <div className="grid gap-2">
                                    <Label htmlFor="titulo">
                                        Nome do curso
                                    </Label>
                                    <Input
                                        id="titulo"
                                        name="titulo"
                                        maxLength={150}
                                        defaultValue={curso?.titulo}
                                        placeholder="Ex.: Combate Corpo a Corpo"
                                    />
                                    <InputError message={errors.titulo} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="descricao">Descrição</Label>
                                    <textarea
                                        id="descricao"
                                        name="descricao"
                                        rows={5}
                                        maxLength={5000}
                                        defaultValue={curso?.descricao ?? ''}
                                        placeholder="O que se aprende, para quem é, pré-requisitos."
                                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring"
                                    />
                                    <InputError message={errors.descricao} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="area_atuacao_id">
                                            Área de atuação
                                        </Label>
                                        <select
                                            id="area_atuacao_id"
                                            name="area_atuacao_id"
                                            defaultValue={
                                                curso?.area_atuacao_id ?? ''
                                            }
                                            className={classeSelect}
                                        >
                                            <option value="">Nenhuma</option>
                                            {areas.map((area) => (
                                                <option
                                                    key={area.id}
                                                    value={area.id}
                                                >
                                                    {area.nome}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.area_atuacao_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="instrutor_id">
                                            Instrutor
                                        </Label>
                                        <select
                                            id="instrutor_id"
                                            name="instrutor_id"
                                            defaultValue={
                                                curso?.instrutor_id ?? ''
                                            }
                                            className={classeSelect}
                                        >
                                            <option value="">A definir</option>
                                            {instrutores.map((instrutor) => (
                                                <option
                                                    key={instrutor.id}
                                                    value={instrutor.id}
                                                >
                                                    {instrutor.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.instrutor_id}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="imagem">
                                        Imagem de capa (JPG, PNG ou WEBP, até 2
                                        MB)
                                    </Label>
                                    {curso?.imagem_url && (
                                        <img
                                            src={curso.imagem_url}
                                            alt={`Capa atual de ${curso.titulo}`}
                                            className="h-28 w-full max-w-sm rounded-md object-cover"
                                        />
                                    )}
                                    <Input
                                        id="imagem"
                                        name="imagem"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                    />
                                    <InputError message={errors.imagem} />
                                </div>
                            </fieldset>

                            {/* ---------- Modalidade, acesso e valor ---------- */}
                            <fieldset className="grid gap-4">
                                <legend className="mb-2 font-medium">
                                    Modalidade, acesso e valor
                                </legend>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="modalidade">
                                            Modalidade
                                        </Label>
                                        <select
                                            id="modalidade"
                                            name="modalidade"
                                            value={modalidade}
                                            onChange={(evento) =>
                                                setModalidade(
                                                    evento.target.value,
                                                )
                                            }
                                            className={classeSelect}
                                        >
                                            {modalidades.map((opcao) => (
                                                <option
                                                    key={opcao.valor}
                                                    value={opcao.valor}
                                                >
                                                    {opcao.rotulo}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.modalidade}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="acesso">
                                            Quem pode se inscrever
                                        </Label>
                                        <select
                                            id="acesso"
                                            name="acesso"
                                            defaultValue={
                                                curso?.acesso ?? 'aberto'
                                            }
                                            className={classeSelect}
                                        >
                                            {acessos.map((opcao) => (
                                                <option
                                                    key={opcao.valor}
                                                    value={opcao.valor}
                                                >
                                                    {opcao.rotulo}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.acesso} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="valor">
                                            Valor (R$)
                                        </Label>
                                        <Input
                                            id="valor"
                                            name="valor"
                                            inputMode="decimal"
                                            defaultValue={curso?.valor}
                                            placeholder="Vazio = gratuito"
                                            aria-describedby="valor-dica"
                                        />
                                        <p
                                            id="valor-dica"
                                            className="text-xs text-muted-foreground"
                                        >
                                            Gratuito é só para filiados.
                                        </p>
                                        <InputError message={errors.valor} />
                                    </div>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="vagas">Vagas</Label>
                                        <Input
                                            id="vagas"
                                            name="vagas"
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            defaultValue={curso?.vagas ?? ''}
                                            placeholder="Vazio = sem limite"
                                        />
                                        <InputError message={errors.vagas} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="carga_horaria">
                                            Carga horária (horas)
                                        </Label>
                                        <Input
                                            id="carga_horaria"
                                            name="carga_horaria"
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            defaultValue={
                                                curso?.carga_horaria ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.carga_horaria}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="link_pagamento">
                                            Link de pagamento
                                        </Label>
                                        <Input
                                            id="link_pagamento"
                                            name="link_pagamento"
                                            type="url"
                                            defaultValue={
                                                curso?.link_pagamento ?? ''
                                            }
                                            placeholder="https://"
                                        />
                                        <InputError
                                            message={errors.link_pagamento}
                                        />
                                    </div>
                                </div>
                            </fieldset>

                            {/* ---------- Quando e onde ---------- */}
                            <fieldset className="grid gap-4">
                                <legend className="mb-2 font-medium">
                                    Quando e onde{' '}
                                    {presencial
                                        ? '(obrigatório no presencial)'
                                        : '(opcional no online)'}
                                </legend>
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="data_inicio">
                                            Início
                                        </Label>
                                        <Input
                                            id="data_inicio"
                                            name="data_inicio"
                                            type="date"
                                            defaultValue={
                                                curso?.data_inicio ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.data_inicio}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="data_fim">Fim</Label>
                                        <Input
                                            id="data_fim"
                                            name="data_fim"
                                            type="date"
                                            defaultValue={curso?.data_fim ?? ''}
                                        />
                                        <InputError message={errors.data_fim} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="hora_inicio">Das</Label>
                                        <Input
                                            id="hora_inicio"
                                            name="hora_inicio"
                                            type="time"
                                            defaultValue={
                                                curso?.hora_inicio ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.hora_inicio}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="hora_fim">Às</Label>
                                        <Input
                                            id="hora_fim"
                                            name="hora_fim"
                                            type="time"
                                            defaultValue={curso?.hora_fim ?? ''}
                                        />
                                        <InputError message={errors.hora_fim} />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="local">Local</Label>
                                    <Input
                                        id="local"
                                        name="local"
                                        maxLength={150}
                                        defaultValue={curso?.local ?? ''}
                                        placeholder="Ex.: Recife · PE"
                                    />
                                    <InputError message={errors.local} />
                                </div>
                            </fieldset>

                            {/* ---------- Situação ---------- */}
                            <div className="grid gap-2 sm:max-w-xs">
                                <Label htmlFor="situacao">Situação</Label>
                                <select
                                    id="situacao"
                                    name="situacao"
                                    defaultValue={curso?.situacao ?? 'rascunho'}
                                    className={classeSelect}
                                    aria-describedby="situacao-dica"
                                >
                                    {situacoes.map((opcao) => (
                                        <option
                                            key={opcao.valor}
                                            value={opcao.valor}
                                        >
                                            {opcao.rotulo}
                                        </option>
                                    ))}
                                </select>
                                <p
                                    id="situacao-dica"
                                    className="text-xs text-muted-foreground"
                                >
                                    Só "Publicado" aparece para os alunos.
                                </p>
                                <InputError message={errors.situacao} />
                            </div>

                            {/* Botões: no celular, 2 colunas iguais. */}
                            <div className="grid grid-cols-2 gap-3 sm:flex sm:items-center">
                                <Button disabled={processing}>Salvar</Button>
                                <Button asChild variant="outline">
                                    <Link href={index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
CursoFormulario.layout = {
    breadcrumbs: [{ title: 'Cursos', href: index() }],
};
