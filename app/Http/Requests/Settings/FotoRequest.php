<?php

/*
 * Validação da FOTO enviada (#78): perfil do usuário e 3x4 do filiado.
 * Só JPG, PNG ou WebP, até 5 MB. O recorte e a redução vêm depois (FotoDePerfil).
 */

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FotoRequest extends FormRequest
{
    /**
     * Regras do campo "foto".
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Obrigatória; imagem de verdade; só estes formatos; no máximo 5 MB (5120 KB).
            'foto' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * Mensagens em português simples, para a pessoa saber o que trocar.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required' => 'Escolha uma foto.',
            'foto.image' => 'O arquivo precisa ser uma foto (JPG, PNG ou WebP).',
            'foto.mimes' => 'Use uma foto JPG, PNG ou WebP.',
            'foto.max' => 'A foto pode ter no máximo 5 MB.',
        ];
    }
}
