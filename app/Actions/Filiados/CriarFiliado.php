<?php

/*
 * Cria um FILIADO e a CONTA do app dele de uma vez só (#37).
 *
 * Decisão do Gleyson (07/10/2026): todo filiado ganha conta (só se entra por
 * indicação). A conta nasce com uma senha aleatória que ninguém conhece; a
 * pessoa cria a própria senha pelo convite por e-mail (bloco F3).
 *
 * Usada pelo formulário (F2) e pela importação da planilha (F4), para as duas
 * seguirem exatamente a mesma regra.
 */

namespace App\Actions\Filiados;

use App\Models\Filiado;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CriarFiliado
{
    /**
     * Cria a conta e o filiado.
     * $dados = os campos do formulário ou de uma linha da planilha.
     * Ex.: ['nome' => 'Fulano', 'email' => 'fulano@exemplo.com', 'cpf' => '529.982.247-25', ...]
     *
     * @param  array{nome: string, email: string, cpf: string, nascimento: string, telefone?: string|null, municipio: string, uf: string, equipe_id?: int|null, nivel_id?: int|null, areas?: list<int>}  $dados
     * @return Filiado O filiado criado, já com a conta (usuario) ligada.
     */
    public function executar(array $dados): Filiado
    {
        // "transaction": conta, filiado e áreas entram juntos; se algo falhar, nada fica pela metade.
        return DB::transaction(function () use ($dados) {
            // A conta do app: senha aleatória e longa, que ninguém conhece (o convite cria a de verdade).
            $usuario = User::create([
                'name' => $dados['nome'],
                'email' => strtolower($dados['email']),
                'password' => Str::password(32),
            ]);

            $filiado = new Filiado([
                'cpf' => $dados['cpf'],
                'nascimento' => $dados['nascimento'],
                'telefone' => $dados['telefone'] ?? null,
                'municipio' => $dados['municipio'],
                'uf' => strtoupper($dados['uf']),
                'equipe_id' => $dados['equipe_id'] ?? null,
                'nivel_id' => $dados['nivel_id'] ?? null,
                'status' => 'ativo',
            ]);
            // Campos que o formulário não preenche: a conta e o número de registro.
            $filiado->user_id = $usuario->id;
            $filiado->registro = $this->proximoRegistro($dados['uf']);
            $filiado->save();

            $filiado->areas()->sync($dados['areas'] ?? []);

            return $filiado;
        });
    }

    /**
     * Próximo número de registro do ano. A sequência é do ANO inteiro (não por
     * estado), como na planilha atual: BR20260001SP, BR20260002RO, BR20260003PR...
     *
     * lockForUpdate "tranca" as linhas lidas até o fim da transaction: se duas
     * pessoas cadastrarem ao mesmo tempo, a segunda espera a primeira terminar
     * e não pega o mesmo número (como duas vendas no PDV disputando o mesmo cupom).
     *
     * @param  string  $uf  Ex.: 'PE'
     * @return string Ex.: 'BR20260016PE'
     */
    private function proximoRegistro(string $uf): string
    {
        $ano = (int) now()->format('Y');

        // Maior sequência já usada no ano: as 4 casas depois de "BR2026".
        $ultima = Filiado::query()
            ->where('registro', 'like', "BR{$ano}%")
            ->lockForUpdate()
            ->pluck('registro')
            ->map(fn (string $registro): int => (int) substr($registro, 6, 4))
            ->max() ?? 0;

        return Filiado::montarRegistro($ano, $ultima + 1, $uf);
    }
}
