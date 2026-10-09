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
use App\Notifications\ConviteFiliado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CriarFiliado
{
    /**
     * Cria a conta e o filiado.
     * $dados = os campos do formulário ou de uma linha da planilha.
     * Ex.: ['nome' => 'Fulano', 'email' => 'fulano@exemplo.com', 'cpf' => '529.982.247-25', ...]
     *
     * @param  array{nome: string, email: string, cpf: string, nascimento: string, telefone?: string|null, municipio: string, uf: string, equipe_id?: int|null, categoria?: string|null, nivel_id?: int|null, abrangencia?: string|null, documentacao_emitida?: bool, areas?: list<int>, registro?: string|null}  $dados
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
                // Função no IBTO (ex.: 'instrutor'), ou null.
                'categoria' => $dados['categoria'] ?? null,
                'nivel_id' => $dados['nivel_id'] ?? null,
                // nacional, internacional ou null (não se aplica).
                'abrangencia' => $dados['abrangencia'] ?? null,
                // Documentação do IBTO emitida. Quando não vem (ex.: planilha sem essa
                // coluna), vale o padrão "sim" (true): a maioria dos filiados já tem
                // carteirinha; as exceções são desmarcadas na tela (Gleyson, 09/10/2026).
                'documentacao_emitida' => $dados['documentacao_emitida'] ?? true,
                'status' => 'ativo',
            ]);
            // Campos que o formulário não preenche: a conta e o número de registro.
            $filiado->user_id = $usuario->id;
            // Registro: o que já existe na planilha (importação) é mantido, porque a
            // carteirinha não muda; senão, a plataforma gera o próximo do ano.
            $filiado->registro = $dados['registro'] ?? $this->proximoRegistro($dados['uf']);
            $filiado->save();

            $filiado->areas()->sync($dados['areas'] ?? []);

            // Convite por e-mail para a pessoa criar a própria senha (F3). Vai pela
            // fila e só sai depois que tudo acima foi gravado de vez.
            $usuario->notify(new ConviteFiliado);

            return $filiado;
        });
    }

    /**
     * Próximo número de registro. Regra do Mestre (09/10/2026): "BR" + ANO
     * CORRENTE + o número SEGUINTE da sequência + a UF da pessoa. A sequência é
     * ÚNICA e NUNCA recomeça: não é por estado nem por ano. Ex.: depois de
     * BR20260045SC vem BR20260046PE; e, em 2027, BR20270047... (o ano muda, a
     * contagem segue, como o número do cupom fiscal no PDV).
     *
     * lockForUpdate "tranca" as linhas lidas até o fim da transaction: se duas
     * pessoas cadastrarem ao mesmo tempo, a segunda espera a primeira terminar
     * e não pega o mesmo número (como duas vendas no PDV disputando o mesmo cupom).
     *
     * @param  string  $uf  Ex.: 'PE'
     * @return string Ex.: 'BR20260046PE'
     */
    private function proximoRegistro(string $uf): string
    {
        // Maior sequência já usada, de QUALQUER ano: as 4 casas depois de "BR" + ano.
        // Ex.: em "BR20260045SC", as casas 7 a 10 são "0045".
        $ultima = Filiado::query()
            ->where('registro', 'like', 'BR%')
            ->lockForUpdate()
            ->pluck('registro')
            ->map(fn (string $registro): int => (int) substr($registro, 6, 4))
            ->max() ?? 0;

        // O ano é sempre o corrente; só a sequência vem da maior já usada.
        return Filiado::montarRegistro((int) now()->format('Y'), $ultima + 1, $uf);
    }
}
