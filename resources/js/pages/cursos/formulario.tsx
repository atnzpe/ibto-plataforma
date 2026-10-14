/**
 * FORMULÁRIO do curso (#39 / #38 / #40): cadastrar (curso = null) ou editar.
 * Todo curso nasce RASCUNHO; a situação muda só pelos botões do fluxo de
 * chancela (painel no topo), nunca por este formulário. Quem propôs só edita
 * enquanto é rascunho ou rejeitado; depois, a tela fica só para olhar.
 * As regras de verdade são conferidas no servidor (CursoRequest e FluxoCurso).
 * Responsivo: campos em grade que vira uma coluna no celular.
 */
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import CursoController from '@/actions/App/Http/Controllers/Cursos/CursoController';
// Painel da situação e dos botões do fluxo de chancela (#40).
import PainelPagamentoCurso from '@/components/cursos/painel-pagamento-curso';
import type { PagamentoDoCurso } from '@/components/cursos/painel-pagamento-curso';
import PainelSituacao from '@/components/cursos/painel-situacao';
import type { DadosSituacao } from '@/components/cursos/painel-situacao';
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
    // true = curso FECHADO: fora do site; só o IBTO matricula (ex.: Formação de instrutor).
    fechado: boolean;
    // Parte do IBTO em %, de 0 a 100. Ex.: 20 (o instrutor fica com 80).
    percentual_ibto: number;
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
    material_incluso: string | null;
    contato_whatsapp: string | null;
    instrutor_nome: string | null;
    instrutor_especialidade: string | null;
    chamada: string | null;
    imagem_url: string | null;
} & DadosSituacao;

// Visual comum das caixas de escolha (select), igual ao dos campos de texto.
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * @param curso O curso a editar, ou null para cadastrar um novo.
 * @param areas As áreas de atuação ativas.
 * @param instrutores As contas com o papel Instrutor.
 * @param modalidades Presencial e online.
 * @param acessos Aberto ao público e só filiados.
 * @param pagamento_curso Dados para pagamento próprios do curso (só para quem tem "pagamentos.configurar"; null ou ausente = sem painel).
 * @param campos_ibto true = mostra os campos só do IBTO ("Curso fechado" e "Parte do IBTO"); false ou ausente = nem aparecem.
 * @returns A tela do formulário.
 */
export default function CursoFormulario({
    curso,
    areas,
    instrutores,
    modalidades,
    acessos,
    pagamento_curso = null,
    campos_ibto = false,
}: {
    curso: CursoEdicao | null;
    areas: { id: number; nome: string }[];
    instrutores: { id: number; name: string }[];
    modalidades: Opcao[];
    acessos: Opcao[];
    pagamento_curso?: PagamentoDoCurso | null;
    campos_ibto?: boolean;
}) {
    const editando = curso !== null;
    const titulo = editando ? `Editar "${curso.titulo}"` : 'Novo curso';
    // true quando a pessoa só pode OLHAR (o servidor decide; ex.: curso já em análise).
    const somenteLeitura = editando && !curso.pode_editar;

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

                {/* Situação, motivo e os botões do fluxo de chancela (só na edição). */}
                {editando && <PainelSituacao curso={curso} />}

                <Form {...destino} className="space-y-8">
                    {({ processing, errors }) => (
                        <>
                            {/* Sem permissão de editar (ex.: curso do dono já em análise):
                                o fieldset "disabled" trava todos os campos de uma vez. */}
                            <fieldset
                                disabled={somenteLeitura}
                                className="contents"
                            >
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
                                    {/* A frase de efeito do cartaz, em destaque na página do curso. */}
                                    <div className="grid gap-2">
                                        <Label htmlFor="chamada">
                                            Chamada (frase de efeito)
                                        </Label>
                                        <Input
                                            id="chamada"
                                            name="chamada"
                                            maxLength={200}
                                            defaultValue={curso?.chamada ?? ''}
                                            placeholder="Ex.: Mais que treinamento, preparação para a vida real."
                                        />
                                        <InputError message={errors.chamada} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="descricao">
                                            Descrição
                                        </Label>
                                        <textarea
                                            id="descricao"
                                            name="descricao"
                                            rows={5}
                                            maxLength={5000}
                                            defaultValue={
                                                curso?.descricao ?? ''
                                            }
                                            placeholder="O que se aprende, para quem é, pré-requisitos."
                                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring"
                                        />
                                        <InputError
                                            message={errors.descricao}
                                        />
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
                                                <option value="">
                                                    Nenhuma
                                                </option>
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
                                                <option value="">
                                                    A definir
                                                </option>
                                                {instrutores.map(
                                                    (instrutor) => (
                                                        <option
                                                            key={instrutor.id}
                                                            value={instrutor.id}
                                                        >
                                                            {instrutor.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={errors.instrutor_id}
                                            />
                                        </div>
                                    </div>
                                    {/* Como o instrutor aparece no cartaz (nem todo instrutor tem conta). */}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="instrutor_nome">
                                                Nome do instrutor no cartaz
                                            </Label>
                                            <Input
                                                id="instrutor_nome"
                                                name="instrutor_nome"
                                                maxLength={100}
                                                defaultValue={
                                                    curso?.instrutor_nome ?? ''
                                                }
                                                placeholder="Vazio = o nome da conta escolhida"
                                            />
                                            <InputError
                                                message={errors.instrutor_nome}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="instrutor_especialidade">
                                                Especialidade
                                            </Label>
                                            <Input
                                                id="instrutor_especialidade"
                                                name="instrutor_especialidade"
                                                maxLength={100}
                                                defaultValue={
                                                    curso?.instrutor_especialidade ??
                                                    ''
                                                }
                                                placeholder="Ex.: Especialista em tiro"
                                            />
                                            <InputError
                                                message={
                                                    errors.instrutor_especialidade
                                                }
                                            />
                                        </div>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="imagem">
                                            Imagem de capa (JPG, PNG ou WEBP,
                                            até 2 MB)
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
                                            <InputError
                                                message={errors.acesso}
                                            />
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
                                            <InputError
                                                message={errors.valor}
                                            />
                                        </div>
                                    </div>
                                    {/* CAMPOS SÓ DO IBTO: "Parte do IBTO" e "Curso fechado" (o servidor
                                        confere de novo). Sem a permissão, nem aparecem e o curso fica como está. */}
                                    {campos_ibto && (
                                        <div className="grid gap-2 sm:max-w-xs">
                                            <Label htmlFor="percentual_ibto">
                                                Parte do IBTO (%)
                                            </Label>
                                            <Input
                                                id="percentual_ibto"
                                                name="percentual_ibto"
                                                type="number"
                                                inputMode="numeric"
                                                min={0}
                                                max={100}
                                                // Curso novo: 20 (o padrão); na edição, o que está no banco.
                                                defaultValue={
                                                    curso?.percentual_ibto ?? 20
                                                }
                                                aria-describedby="percentual-dica"
                                            />
                                            <p
                                                id="percentual-dica"
                                                className="text-xs text-muted-foreground"
                                            >
                                                O resto vai para o instrutor.
                                                Ex.: 20 = 20% do IBTO e 80% do
                                                instrutor; 100 = tudo do IBTO
                                                (Formação de instrutor).
                                            </p>
                                            <InputError
                                                message={errors.percentual_ibto}
                                            />
                                        </div>
                                    )}
                                    {campos_ibto && (
                                        <div className="grid gap-1">
                                            {/* O campo escondido manda "0" quando a caixa está desmarcada
                                                (caixa desmarcada não é enviada pelo navegador).
                                                Marcada, o "1" da caixa substitui o "0". */}
                                            <input
                                                type="hidden"
                                                name="fechado"
                                                value="0"
                                            />
                                            <label className="flex items-center gap-2 text-sm font-medium">
                                                <input
                                                    type="checkbox"
                                                    name="fechado"
                                                    value="1"
                                                    // Curso novo nasce aberto; na edição, vem como está no banco.
                                                    defaultChecked={
                                                        curso?.fechado ?? false
                                                    }
                                                    aria-describedby="fechado-dica"
                                                    className="size-4"
                                                />
                                                Curso fechado (não aparece no
                                                site)
                                            </label>
                                            <p
                                                id="fechado-dica"
                                                className="text-xs text-muted-foreground"
                                            >
                                                Fica fora do catálogo e das
                                                trilhas; ninguém se inscreve
                                                sozinho, só quem o IBTO
                                                matricular. Ex.: Formação de
                                                instrutor.
                                            </p>
                                        </div>
                                    )}
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor="vagas">Vagas</Label>
                                            <Input
                                                id="vagas"
                                                name="vagas"
                                                type="number"
                                                inputMode="numeric"
                                                min={1}
                                                defaultValue={
                                                    curso?.vagas ?? ''
                                                }
                                                placeholder="Vazio = sem limite"
                                            />
                                            <InputError
                                                message={errors.vagas}
                                            />
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
                                            <Label htmlFor="data_fim">
                                                Fim
                                            </Label>
                                            <Input
                                                id="data_fim"
                                                name="data_fim"
                                                type="date"
                                                defaultValue={
                                                    curso?.data_fim ?? ''
                                                }
                                            />
                                            <InputError
                                                message={errors.data_fim}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="hora_inicio">
                                                Das
                                            </Label>
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
                                                defaultValue={
                                                    curso?.hora_fim ?? ''
                                                }
                                            />
                                            <InputError
                                                message={errors.hora_fim}
                                            />
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

                                {/* ---------- O que vem junto (dos cartazes de referência) ---------- */}
                                <fieldset className="grid gap-4">
                                    <legend className="mb-2 font-medium">
                                        O que vem junto e contato
                                    </legend>
                                    <div className="grid gap-2">
                                        <Label htmlFor="material_incluso">
                                            Material incluso
                                        </Label>
                                        <Input
                                            id="material_incluso"
                                            name="material_incluso"
                                            maxLength={500}
                                            defaultValue={
                                                curso?.material_incluso ?? ''
                                            }
                                            placeholder="Ex.: Certificado, camisa do evento"
                                        />
                                        <InputError
                                            message={errors.material_incluso}
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:max-w-xs">
                                        <Label htmlFor="contato_whatsapp">
                                            Contato (WhatsApp)
                                        </Label>
                                        <Input
                                            id="contato_whatsapp"
                                            name="contato_whatsapp"
                                            type="tel"
                                            inputMode="tel"
                                            maxLength={20}
                                            defaultValue={
                                                curso?.contato_whatsapp ?? ''
                                            }
                                            placeholder="(81) 99999-0000"
                                        />
                                        <InputError
                                            message={errors.contato_whatsapp}
                                        />
                                    </div>
                                </fieldset>

                                {/* Botões: no celular, 2 colunas iguais. Sem permissão de editar: só "Voltar". */}
                                <div className="grid grid-cols-2 gap-3 sm:flex sm:items-center">
                                    {!somenteLeitura && (
                                        <Button disabled={processing}>
                                            Salvar
                                        </Button>
                                    )}
                                    <Button
                                        asChild
                                        variant="outline"
                                        className={
                                            somenteLeitura ? 'col-span-2' : ''
                                        }
                                    >
                                        <Link href={index()}>
                                            {somenteLeitura
                                                ? 'Voltar'
                                                : 'Cancelar'}
                                        </Link>
                                    </Button>
                                </div>
                            </fieldset>
                        </>
                    )}
                </Form>
                {/* Dados para pagamento do curso (#41): painel separado, só com a permissão. */}
                {editando && pagamento_curso && (
                    <PainelPagamentoCurso
                        cursoId={curso.id}
                        dados={pagamento_curso}
                    />
                )}
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
CursoFormulario.layout = {
    breadcrumbs: [{ title: 'Cursos', href: index() }],
};
