<?php

/*
 * AULA de um módulo (#39): vídeo por LINK (YouTube ou .mp4/.webm, ver
 * App\Support\Video) OU vídeo ENVIADO (até 200 MB; escolha de quem cadastra),
 * texto e APOSTILA em PDF. Os arquivos ficam no disco PRIVADO e só são
 * entregues por rotas que conferem o acesso (nunca link público direto).
 */

namespace App\Models;

use App\Support\Video;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $modulo_id
 * @property string $titulo
 * @property int $ordem
 * @property string|null $video_url
 * @property string|null $video_arquivo
 * @property string|null $conteudo
 * @property string|null $apostila
 * @property int|null $duracao_minutos
 * @property bool $ativa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// "apostila" e "video_arquivo" ficam de fora: quem grava é o controlador (arquivos no disco privado).
#[Fillable(['modulo_id', 'titulo', 'ordem', 'video_url', 'conteudo', 'duracao_minutos', 'ativa'])]
class CursoAula extends Model
{
    protected $table = 'curso_aulas';

    // Tamanho máximo do VÍDEO ENVIADO: 200 MB (em KB, como a validação do
    // Laravel conta). Um lugar só: o formulário e a tela leem daqui. Vídeo
    // maior deve ir por link (YouTube), para não pesar na HostGator.
    public const VIDEO_MAXIMO_KB = 204800;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ordem' => 'integer', 'duracao_minutos' => 'integer', 'ativa' => 'boolean'];
    }

    /**
     * Como tocar o vídeo da aula (ou null se não tem vídeo). O vídeo ENVIADO
     * tem preferência sobre o link; ele toca por uma rota que confere o acesso.
     *
     * @return array<string, string>|null Ex.: ['tipo' => 'youtube', 'id' => '...', 'embed' => '...'] ou ['tipo' => 'enviado', 'url' => '/cursos/aulas/7/video']
     */
    public function video(): ?array
    {
        if ($this->video_arquivo !== null) {
            return ['tipo' => 'enviado', 'url' => route('cursos.aulas.video', $this->id)];
        }

        return Video::paraTocar($this->video_url);
    }

    /**
     * @return BelongsTo<CursoModulo, $this>
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(CursoModulo::class, 'modulo_id');
    }
}
