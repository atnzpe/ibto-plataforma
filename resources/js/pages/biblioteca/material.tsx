/**
 * FICHA DE UM MATERIAL da Biblioteca de técnicas (#107): o vídeo, a explicação
 * e o botão da apostila. O servidor só abre esta tela para quem pode ver o
 * material (ativo e liberado para o nível da pessoa); os outros recebem 403.
 */
import { Head, Link } from '@inertiajs/react';
import TocadorVideo from '@/components/tocador-video';
import type { Video } from '@/components/tocador-video';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/biblioteca';

/** O material como o servidor manda. */
type Material = {
    id: number;
    titulo: string;
    descricao: string | null;
    // null = vale para todas as áreas.
    area: string | null;
    // null = todos os níveis.
    nivel: string | null;
    // Como tocar; null = sem vídeo.
    video: Video | null;
    // Endereço protegido da apostila; null = sem apostila.
    apostila_url: string | null;
};

/**
 * A tela da ficha.
 *
 * @param material O material aberto. Ex.: { id: 3, titulo: 'Postura de guarda', ... }
 * @returns A tela.
 */
export default function MaterialBiblioteca({
    material,
}: {
    material: Material;
}) {
    return (
        <>
            <Head title={material.titulo} />

            <div className="flex max-w-3xl flex-col gap-5 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {material.titulo}
                    </h1>
                    {/* Área e nível, escritos. */}
                    <p className="text-sm text-muted-foreground">
                        {material.area ?? 'Todas as áreas'} ·{' '}
                        {material.nivel ?? 'Todos os níveis'}
                    </p>
                </div>

                {/* Com vídeo: o tocador. Sem vídeo: um aviso curto. */}
                {material.video ? (
                    <TocadorVideo
                        video={material.video}
                        titulo={material.titulo}
                    />
                ) : (
                    <p className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                        Esta técnica ainda não tem vídeo.
                    </p>
                )}

                {/* Explicação: mantém as quebras de linha que o Comando Geral digitou. */}
                {material.descricao && (
                    <p className="text-sm leading-relaxed whitespace-pre-line">
                        {material.descricao}
                    </p>
                )}

                <div className="flex flex-col gap-3 sm:flex-row">
                    {/* Apostila: link comum (o navegador baixa o PDF pela rota protegida). */}
                    {material.apostila_url && (
                        <Button asChild className="min-h-11">
                            <a href={material.apostila_url}>
                                Baixar a apostila (PDF)
                            </a>
                        </Button>
                    )}
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href={index()}>Voltar para a biblioteca</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
MaterialBiblioteca.layout = {
    breadcrumbs: [
        { title: 'Biblioteca de técnicas', href: index() },
        { title: 'Técnica', href: '#' },
    ],
};
