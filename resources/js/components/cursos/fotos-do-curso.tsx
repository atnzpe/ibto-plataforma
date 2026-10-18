/**
 * FOTOS DO CURSO (Mestre Eduardo, 17/10/2026: "o responsável pelas fotos é sempre
 * o instrutor do curso"). Quadro na tela de Inscritos, só para o instrutor
 * responsável e o Comando Geral, com dois botões:
 * - "Álbum do evento": fotos gratuitas para os filiados (o Comando Geral publica);
 * - "Fotos à venda": o instrutor é o fotógrafo (o Comando Geral ativa a venda).
 * Um álbum de cada por curso: clicar de novo abre o que já existe.
 */
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

/**
 * @param cursoId O número do curso. Ex.: 7.
 * @returns O quadro com os dois botões.
 */
export default function FotosDoCurso({ cursoId }: { cursoId: number }) {
    return (
        <section
            aria-labelledby="t-fotos-curso"
            data-tour="fotos-do-curso"
            className="flex flex-col gap-3 rounded-md border border-border p-4 text-sm"
        >
            <h2 id="t-fotos-curso" className="font-medium">
                Fotos do curso
            </h2>
            <p className="text-muted-foreground">
                Você é o responsável pelas fotos deste curso. Desfoque rostos e
                distintivos antes de enviar ao Comando Geral, que confere e
                publica.
            </p>
            {/* Botões grandes (44 px): lado a lado na tela larga, um embaixo do outro no celular. */}
            <div className="flex flex-col gap-2 sm:flex-row">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    onClick={() =>
                        router.post(`/cursos/${cursoId}/album-fotos`)
                    }
                >
                    Álbum do evento (gratuito para filiados)
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    onClick={() =>
                        router.post(`/cursos/${cursoId}/fotos-venda`)
                    }
                >
                    Fotos à venda (você é o fotógrafo)
                </Button>
            </div>
        </section>
    );
}
