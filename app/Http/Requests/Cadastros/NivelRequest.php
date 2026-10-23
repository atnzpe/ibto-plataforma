<?php

/*
 * Validação do formulário de nível (#37), no SERVIDOR.
 *
 * Além do formato dos campos, confere a REGRA DE NEGÓCIO do grau, que vem de
 * CategoriaNivel::grauMaximo(): Praticante não tem grau; Monitor vai até II;
 * Instrutor e Instrutor Expert, até V.
 *
 * Níveis livres (18/10/2026): com um NOME escolhido (ex.: "Grão-Mestre" ou
 * "Monitor III"), o grau deixa de ser obrigatório. A posição diz depois de
 * qual nível o novo entra; a letra (A a Z) vai no número de série do
 * certificado de graduação. Nenhum nome pode se repetir.
 */

namespace App\Http\Requests\Cadastros;

use App\Enums\CategoriaNivel;
use App\Models\Nivel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NivelRequest extends FormRequest
{
    /**
     * Regras de formato de cada campo.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Obrigatória e só uma das 4 categorias do enum (ela define a "família" do nível).
            'categoria' => ['required', Rule::enum(CategoriaNivel::class)],
            // Opcional aqui (o Praticante e os níveis com nome próprio não têm); o limite é conferido em after().
            'grau' => ['nullable', 'integer', 'min:1', 'max:5'],
            // Nome escolhido (opcional): vazio = nome automático (categoria + grau).
            'nome_personalizado' => ['nullable', 'string', 'max:60'],
            // Uma letra só, de A a Z (opcional): vazio = a letra da categoria.
            'letra' => ['nullable', 'string', 'regex:/^[A-Za-z]$/'],
            // Depois de qual nível este entra: 'inicio' (o mais baixo) ou o número de um nível.
            'depois_de' => ['nullable', 'string', 'max:20'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    /**
     * Regras que dependem de mais de um campo (rodam depois das de formato).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                // Se o formato já falhou (ex.: categoria inválida), não tem como conferir o resto.
                if ($validador->errors()->isNotEmpty()) {
                    return;
                }

                $categoria = CategoriaNivel::from($this->string('categoria')->toString());
                // Grau como número, ou null quando veio vazio.
                $grau = $this->filled('grau') ? $this->integer('grau') : null;
                // Nome escolhido, sem espaços nas pontas, ou null quando veio vazio.
                $nome = $this->filled('nome_personalizado') ? trim($this->string('nome_personalizado')->toString()) : null;
                $maximo = $categoria->grauMaximo();
                // Número do nível em edição, ou null quando é um nível novo.
                $nivelAtual = $this->route('nivel');
                $idAtual = $nivelAtual instanceof Nivel ? $nivelAtual->id : null;

                // Categoria sem grau (Praticante) recebeu um grau: recusa.
                if ($maximo === 0 && $grau !== null) {
                    $validador->errors()->add('grau', "{$categoria->value} não tem grau.");

                    return;
                }

                // Categoria com grau, sem grau e sem nome escolhido: não há como dar nome ao nível.
                if ($maximo > 0 && $grau === null && $nome === null) {
                    $validador->errors()->add('grau', "Informe o grau de {$categoria->value} ou escreva um nome para o nível.");

                    return;
                }

                // Grau acima do máximo da categoria (ex.: Monitor IV): recusa e explica a saída.
                if ($grau !== null && $grau > $maximo) {
                    $validador->errors()->add('grau', "{$categoria->value} vai só até o grau {$maximo}. Para um nível acima, deixe o grau vazio e escreva o nome (ex.: \"{$categoria->value} III\").");

                    return;
                }

                // A posição precisa ser 'inicio' ou um nível que existe (e não o próprio nível).
                $depoisDe = $this->string('depois_de')->toString();
                if ($depoisDe !== '' && $depoisDe !== 'inicio') {
                    $existe = ctype_digit($depoisDe) && (int) $depoisDe !== $idAtual && Nivel::whereKey((int) $depoisDe)->exists();
                    if (! $existe) {
                        $validador->errors()->add('depois_de', 'Escolha uma posição da lista.');

                        return;
                    }
                }

                // Mesma categoria + mesmo grau já cadastrado: o banco também recusaria.
                if ($grau !== null) {
                    $repetido = Nivel::query()
                        ->where('categoria', $categoria->value)
                        ->where('grau', $grau)
                        ->when($idAtual !== null, fn ($consulta) => $consulta->whereKeyNot($idAtual))
                        ->exists();

                    if ($repetido) {
                        $validador->errors()->add('grau', 'Esse nível já está cadastrado.');

                        return;
                    }
                }

                // O nome que vai aparecer nas telas (o escolhido ou o automático) não pode repetir.
                $candidato = new Nivel(['categoria' => $categoria->value, 'grau' => $grau, 'nome_personalizado' => $nome]);
                $nomeFinal = mb_strtolower($candidato->nome());
                $outros = Nivel::query()->when($idAtual !== null, fn ($consulta) => $consulta->whereKeyNot($idAtual))->get();
                // Compara sem diferença de maiúsculas ("grão-mestre" = "Grão-Mestre").
                if ($outros->contains(fn (Nivel $outro) => mb_strtolower($outro->nome()) === $nomeFinal)) {
                    // O erro vai para o campo que o usuário pode corrigir.
                    $validador->errors()->add($nome !== null ? 'nome_personalizado' : 'grau', 'Esse nível já está cadastrado.');
                }
            },
        ];
    }

    /**
     * Mensagens de erro em português.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categoria.required' => 'Escolha a categoria.',
            'categoria.enum' => 'Categoria inválida.',
            'grau.integer' => 'O grau precisa ser um número de 1 a 5.',
            'grau.min' => 'O grau precisa ser um número de 1 a 5.',
            'grau.max' => 'O grau precisa ser um número de 1 a 5.',
            'nome_personalizado.max' => 'O nome pode ter até 60 letras.',
            'letra.regex' => 'A letra precisa ser uma só, de A a Z.',
            'ativo.required' => 'Informe se o nível está ativo.',
            'ativo.boolean' => 'Valor inválido para "ativo".',
        ];
    }
}
