/**
 * TOCADOR DE VÍDEO usado nas aulas dos cursos (#39) e na Biblioteca de técnicas
 * (#107). Um lugar só para as duas telas: se o jeito de tocar mudar, muda nas duas.
 * YouTube toca num quadro do youtube-nocookie; arquivo (link .mp4/.webm ou
 * vídeo enviado à plataforma) toca no tocador do próprio navegador.
 */

/** Como tocar o vídeo (vem de CursoAula::video() ou BibliotecaMaterial::video() no servidor). */
export type Video =
    | { tipo: 'youtube'; id: string; embed: string }
    | { tipo: 'arquivo'; url: string }
    | { tipo: 'enviado'; url: string };

/**
 * O tocador.
 *
 * @param video Ex.: { tipo: 'youtube', embed: 'https://www.youtube-nocookie.com/embed/...' }
 * @param titulo O nome da aula ou da técnica (para o leitor de tela). Ex.: 'Postura de guarda'.
 * @returns O tocador, sempre em 16:9 e na largura toda.
 */
export default function TocadorVideo({
    video,
    titulo,
}: {
    video: Video;
    titulo: string;
}) {
    // YouTube: quadro do youtube-nocookie (não grava cookies de propaganda).
    if (video.tipo === 'youtube') {
        return (
            <iframe
                src={video.embed}
                title={`Vídeo: ${titulo}`}
                className="aspect-video w-full rounded-lg bg-black"
                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                allowFullScreen
                loading="lazy"
                referrerPolicy="strict-origin-when-cross-origin"
            />
        );
    }

    // Arquivo (link .mp4/.webm ou enviado à plataforma): o tocador do próprio navegador.
    // "nodownload" esconde o botão de baixar (não impede quem quer muito, mas evita o clique à toa).
    return (
        <video
            src={video.url}
            controls
            preload="metadata"
            controlsList="nodownload"
            className="aspect-video w-full rounded-lg bg-black"
            aria-label={`Vídeo: ${titulo}`}
        />
    );
}
