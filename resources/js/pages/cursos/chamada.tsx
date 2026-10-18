/**
 * CHAMADA DO ENCONTRO (#44; desenho aprovado em 10/10/2026): marcar quem veio
 * (só pelo REGISTRO, LGPD) e escrever o que foi trabalhado. Pode ser refeita;
 * abre como estava. Botões grandes (44 px) para usar no celular, no tatame.
 */
import FotoPessoa from '@/components/foto-pessoa';
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import ChamadaController from '@/actions/App/Http/Controllers/Cursos/ChamadaController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { dataBr } from '@/pages/catalogo/index';
import {
    index,
    inscritos as telaInscritos,
} from '@/routes/comando-geral/cursos';

/** Um inscrito confirmado na chamada. */
type Chamado = {
    matricula_id: number;
    // null = não filiado (aparece o nº da matrícula).
    registro: string | null;
    // Foto de perfil (#78), ou null sem foto (aparece um ícone).
    foto: string | null;
    equipe: string;
    // true/false = já marcado numa chamada anterior; null = primeira chamada.
    presente: boolean | null;
};

/**
 * @param curso O curso. Ex.: { id: 7, titulo: 'Combate Corpo a Corpo' }
 * @param aula O encontro (título, data, o registro anterior).
 * @param inscritos Os confirmados, com a marcação anterior.
 * @returns A tela da chamada.
 */
export default function Chamada({
    curso,
    aula,
    inscritos,
}: {
    curso: { id: number; titulo: string };
    aula: {
        id: number;
        titulo: string;
        data: string | null;
        hora_inicio: string | null;
        registro_encontro: string | null;
        chamada_feita_em: string | null;
    };
    inscritos: Chamado[];
}) {
    // Quem está marcado como presente agora (ids das matrículas). Primeira chamada: ninguém marcado.
    const [presentes, setPresentes] = useState<number[]>(
        inscritos
            .filter((inscrito) => inscrito.presente === true)
            .map((inscrito) => inscrito.matricula_id),
    );

    /**
     * Marca ou desmarca uma pessoa.
     *
     * @param id A matrícula. Ex.: 12
     */
    const alternar = (id: number) =>
        setPresentes(
            presentes.includes(id)
                ? presentes.filter((outro) => outro !== id)
                : [...presentes, id],
        );

    return (
        <>
            <Head title={`Chamada · ${aula.titulo}`} />

            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <Link
                    href={telaInscritos(curso.id)}
                    className="text-sm underline underline-offset-4"
                >
                    ← Voltar aos inscritos de {curso.titulo}
                </Link>
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Chamada: {aula.titulo}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {aula.data && dataBr(aula.data)}
                        {aula.hora_inicio && ` às ${aula.hora_inicio}`}
                        {aula.chamada_feita_em &&
                            ` · última chamada em ${aula.chamada_feita_em} (refazer corrige)`}
                    </p>
                </div>

                <Form
                    {...ChamadaController.update.form({
                        curso: curso.id,
                        aula: aula.id,
                    })}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Os marcados vão escondidos (presentes[]); os demais contam como falta. */}
                            {presentes.map((id) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name="presentes[]"
                                    value={id}
                                />
                            ))}

                            <fieldset className="flex flex-col gap-2">
                                <legend className="mb-2 font-medium">
                                    Quem veio? ({presentes.length} de{' '}
                                    {inscritos.length})
                                </legend>
                                {inscritos.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum inscrito com matrícula
                                        confirmada.
                                    </p>
                                )}
                                {/* Atalhos: todos presentes / limpar (2 botões iguais). */}
                                {inscritos.length > 0 && (
                                    <div className="grid grid-cols-2 gap-2 sm:w-fit">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11"
                                            onClick={() =>
                                                setPresentes(
                                                    inscritos.map(
                                                        (i) => i.matricula_id,
                                                    ),
                                                )
                                            }
                                        >
                                            Todos presentes
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11"
                                            onClick={() => setPresentes([])}
                                        >
                                            Limpar
                                        </Button>
                                    </div>
                                )}
                                {/* Um por linha, a linha inteira é clicável (44 px). 2 colunas na tela larga. */}
                                <ul className="grid gap-2 sm:grid-cols-2">
                                    {inscritos.map((inscrito) => (
                                        <li key={inscrito.matricula_id}>
                                            <label className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border border-border px-3 py-2 text-sm has-checked:border-[#6b5d3a] has-checked:bg-[#efe8d6] dark:has-checked:bg-[#2a2721]">
                                                <input
                                                    type="checkbox"
                                                    checked={presentes.includes(
                                                        inscrito.matricula_id,
                                                    )}
                                                    onChange={() =>
                                                        alternar(
                                                            inscrito.matricula_id,
                                                        )
                                                    }
                                                    className="size-5"
                                                />
                                                {/* Foto (#78): ajuda a reconhecer o aluno na hora da chamada. */}
                                                <FotoPessoa
                                                    foto={inscrito.foto}
                                                    rotulo={`Foto de ${inscrito.registro ?? `matrícula nº ${inscrito.matricula_id}`}`}
                                                />
                                                <span className="flex flex-col">
                                                    <strong className="font-mono">
                                                        {inscrito.registro ??
                                                            `Matrícula nº ${inscrito.matricula_id}`}
                                                    </strong>
                                                    <span className="text-xs text-muted-foreground">
                                                        {inscrito.equipe}
                                                    </span>
                                                </span>
                                                {/* Estado escrito (não só cor). */}
                                                <span className="ml-auto text-xs font-medium">
                                                    {presentes.includes(
                                                        inscrito.matricula_id,
                                                    )
                                                        ? 'Presente'
                                                        : 'Falta'}
                                                </span>
                                            </label>
                                        </li>
                                    ))}
                                </ul>
                            </fieldset>

                            <div className="grid gap-2">
                                <label
                                    htmlFor="registro_encontro"
                                    className="text-sm font-medium"
                                >
                                    O que foi trabalhado no encontro
                                    (obrigatório; vai para o dossiê dos alunos)
                                </label>
                                <textarea
                                    id="registro_encontro"
                                    name="registro_encontro"
                                    rows={4}
                                    minLength={3}
                                    maxLength={2000}
                                    required
                                    defaultValue={aula.registro_encontro ?? ''}
                                    placeholder="Ex.: guarda, base, quedas e defesa contra agarre."
                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring"
                                />
                                <InputError
                                    message={errors.registro_encontro}
                                />
                            </div>

                            <Button
                                className="min-h-11 w-full sm:w-fit"
                                disabled={processing || inscritos.length === 0}
                            >
                                Salvar chamada
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
Chamada.layout = {
    breadcrumbs: [{ title: 'Cursos', href: index() }],
};
