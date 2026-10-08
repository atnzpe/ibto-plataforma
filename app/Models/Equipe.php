<?php

/*
 * Equipe filiada ao IBTO (ex.: "Vulcan"), #37.
 *
 * Como a "unidade" de uma rede de academias: os filiados pertencem a uma
 * equipe. LGPD: telefone, e-mail e documento do responsável ficam
 * criptografados no banco (casts "encrypted") e só aparecem para o administrador.
 */

namespace App\Models;

use App\Enums\StatusEquipe;
use Database\Factories\EquipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property string|null $logo
 * @property string|null $cidade
 * @property string|null $uf
 * @property string|null $responsavel_nome
 * @property string|null $responsavel_telefone
 * @property string|null $responsavel_email
 * @property string|null $documento
 * @property StatusEquipe $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// "logo" fica de fora: quem grava o caminho é o controller, depois de guardar o arquivo.
#[Fillable(['nome', 'cidade', 'uf', 'responsavel_nome', 'responsavel_telefone', 'responsavel_email', 'documento', 'status'])]
class Equipe extends Model
{
    /** @use HasFactory<EquipeFactory> */
    use HasFactory;

    /**
     * Tipo de cada coluna especial.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // "encrypted": grava embaralhado com a APP_KEY e devolve o texto normal no PHP.
            'responsavel_telefone' => 'encrypted',
            'responsavel_email' => 'encrypted',
            'documento' => 'encrypted',
            // O texto do banco vira uma das opções do enum (ex.: StatusEquipe::Ativa).
            'status' => StatusEquipe::class,
        ];
    }

    /**
     * Áreas de atuação da equipe (várias), pela tabela de ligação.
     *
     * @return BelongsToMany<AreaAtuacao, $this>
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(AreaAtuacao::class, 'area_atuacao_equipe', 'equipe_id', 'area_atuacao_id');
    }

    /**
     * Endereço público da logo (a rede nacional do site também a mostra).
     * O "?v=" muda quando a equipe é alterada, para o navegador não mostrar a logo antiga.
     *
     * @return string|null Ex.: 'https://cursos.ib-to.org/imagens/equipes/3/logo?v=1759870000'; null sem logo.
     */
    public function logoUrl(): ?string
    {
        if ($this->logo === null) {
            return null;
        }

        return route('equipes.logo', ['equipe' => $this->id, 'v' => $this->updated_at?->getTimestamp()]);
    }
}
