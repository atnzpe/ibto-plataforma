<?php

/*
 * MATERIAL DA BIBLIOTECA DE TÉCNICAS (#107): uma técnica com vídeo (link ou
 * arquivo enviado) e/ou apostila em PDF, para monitores e instrutores revisarem.
 * Pode exigir um NÍVEL MÍNIMO (ex.: técnica avançada só a partir de Instrutor I)
 * e pertencer a uma ÁREA DE ATUAÇÃO (ex.: Krav Maga).
 *
 * Como na aula de um curso (CursoAula), os arquivos ficam no disco PRIVADO e só
 * saem pelas rotas que conferem o acesso (nunca por um link público direto).
 */

namespace App\Models;

use App\Support\Video;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $titulo
 * @property string|null $descricao
 * @property int|null $area_atuacao_id
 * @property int|null $nivel_minimo_id
 * @property string|null $video_url
 * @property string|null $video_arquivo
 * @property string|null $apostila
 * @property bool $ativo
 * @property int|null $autor_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// Campos que o formulário pode preencher de uma vez. "video_arquivo", "apostila"
// e "autor_id" ficam de fora de propósito: quem grava é o controlador, depois de
// guardar o arquivo; assim ninguém manda um caminho ou um autor inventado.
#[Fillable(['titulo', 'descricao', 'area_atuacao_id', 'nivel_minimo_id', 'video_url', 'ativo'])]
class BibliotecaMaterial extends Model
{
    // Nome da tabela. Sem isto, o Laravel tentaria "biblioteca_materials" (plural em inglês).
    protected $table = 'biblioteca_materiais';

    // Pasta (no disco privado) onde ficam os vídeos e as apostilas da biblioteca.
    public const PASTA = 'biblioteca';

    /**
     * Tipo de cada coluna especial.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // O banco guarda 0 ou 1; no PHP vira false ou true.
        return ['ativo' => 'boolean'];
    }

    /**
     * O material está LIBERADO para alguém deste nível?
     * Regra da biblioteca (mais firme que a dos cursos): só vê quem já TEM o nível
     * exigido ou um acima. Ex.: material de "Instrutor I" não aparece para o
     * Monitor II, mas aparece para o Instrutor III. (Nos módulos dos cursos o
     * aluno vê "o nível dele e o seguinte"; aqui não, porque a técnica avançada é
     * para quem já chegou lá, como pediu o Alex.)
     *
     * @param  int|null  $nivelDaPessoaId  O nível do filiado. Ex.: 5. null = sem nível.
     * @param  array<int, int>  $posicoes  id do nível => posição na hierarquia (Nivel::posicoes()).
     * @return bool true = pode ver.
     */
    public function liberadoPara(?int $nivelDaPessoaId, array $posicoes): bool
    {
        // Material sem nível mínimo: todos que abrem a biblioteca veem.
        if ($this->nivel_minimo_id === null) {
            return true;
        }

        // Pessoa sem nível (ex.: instrutor sem ficha de filiado): só vê o material sem nível.
        if ($nivelDaPessoaId === null) {
            return false;
        }

        // Posição exigida: nível desconhecido conta como "altíssimo" (ninguém vê, por segurança).
        $exigida = $posicoes[$this->nivel_minimo_id] ?? PHP_INT_MAX;
        // Posição da pessoa: nível desconhecido conta como zero (o mais baixo possível).
        $daPessoa = $posicoes[$nivelDaPessoaId] ?? 0;

        return $daPessoa >= $exigida;
    }

    /**
     * Como tocar o vídeo (ou null se não tem vídeo). O vídeo ENVIADO tem
     * preferência sobre o link; ele toca por uma rota que confere o acesso.
     *
     * @return array<string, string>|null Ex.: ['tipo' => 'youtube', 'id' => '...', 'embed' => '...'] ou ['tipo' => 'enviado', 'url' => '/biblioteca/3/video']
     */
    public function video(): ?array
    {
        // Vídeo enviado: toca pela rota protegida.
        if ($this->video_arquivo !== null) {
            return ['tipo' => 'enviado', 'url' => route('biblioteca.video', $this->id)];
        }

        // Senão, o link (YouTube ou .mp4/.webm); link inválido ou vazio = null.
        return Video::paraTocar($this->video_url);
    }

    /**
     * Filtro "só os ativos", para a lista de quem estuda.
     * Uso: BibliotecaMaterial::ativos()->get()
     *
     * @param  Builder<BibliotecaMaterial>  $consulta  A consulta que está sendo montada.
     * @return Builder<BibliotecaMaterial> A mesma consulta, só com os materiais ativos.
     */
    public function scopeAtivos(Builder $consulta): Builder
    {
        return $consulta->where('ativo', true);
    }

    /**
     * A área de atuação do material (pode ser vazia = todas).
     *
     * @return BelongsTo<AreaAtuacao, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaAtuacao::class, 'area_atuacao_id');
    }

    /**
     * O nível mínimo para ver (pode ser vazio = todos que abrem a biblioteca).
     *
     * @return BelongsTo<Nivel, $this>
     */
    public function nivelMinimo(): BelongsTo
    {
        return $this->belongsTo(Nivel::class, 'nivel_minimo_id');
    }

    /**
     * Quem enviou o material.
     *
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
