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
use App\Support\Paises;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CriarFiliado
{
    /**
     * Cria a conta e o filiado.
     * $dados = os campos do formulário ou de uma linha da planilha.
     * Ex.: ['nome' => 'Fulano', 'email' => 'fulano@exemplo.com', 'cpf' => '529.982.247-25', ...]
     *
     * @param  array{nome: string, email: string|null, cpf: string|null, nascimento: string|null, telefone?: string|null, municipio: string|null, uf?: string|null, pais?: string, equipe_id?: int|null, categoria?: string|null, nivel_id?: int|null, abrangencia?: string|null, documentacao_emitida?: bool, areas?: list<int>, registro?: string|null}  $dados
     * @return Filiado O filiado criado, já com a conta (usuario) ligada.
     */
    public function executar(array $dados): Filiado
    {
        // "transaction": conta, filiado e áreas entram juntos; se algo falhar, nada fica pela metade.
        return DB::transaction(function () use ($dados) {
            // A conta do app: senha aleatória e longa, que ninguém conhece (o convite cria a de verdade).
            $usuario = User::create([
                'name' => $dados['nome'],
                // Importado sem e-mail ("importar mesmo assim"): a conta fica sem e-mail (null).
                'email' => $dados['email'] !== null ? strtolower($dados['email']) : null,
                'password' => Str::password(32),
            ]);

            $filiado = new Filiado([
                'cpf' => $dados['cpf'],
                'nascimento' => $dados['nascimento'],
                'telefone' => $dados['telefone'] ?? null,
                'municipio' => $dados['municipio'],
                // UF só no Brasil (fora dele vem vazia).
                'uf' => ! empty($dados['uf']) ? strtoupper($dados['uf']) : null,
                // País: o informado, ou o Brasil (padrão; a planilha antiga não tem país).
                'pais' => strtoupper($dados['pais'] ?? Paises::BRASIL),
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
            $filiado->registro = $dados['registro'] ?? $this->proximoRegistro($dados['uf'] ?? null, strtoupper($dados['pais'] ?? Paises::BRASIL));
            $filiado->save();

            $filiado->areas()->sync($dados['areas'] ?? []);

            // Convite por e-mail para a pessoa criar a própria senha (F3). Vai pela
            // fila e só sai depois que tudo acima foi gravado de vez.
            // Sem e-mail não há para onde mandar: o convite sai quando o e-mail for completado na edição.
            if ($usuario->email !== null) {
                $usuario->notify(new ConviteFiliado);
            }

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
    private function proximoRegistro(?string $uf, string $pais = Paises::BRASIL): string
    {
        // Maior sequência já usada, de QUALQUER ano e QUALQUER país: as 4 casas depois
        // das 2 letras do país + ano. Ex.: em "BR20260045SC" e em "PT20260046", as casas
        // 7 a 10 ("0045", "0046"). Uma sequência só para todos: o número nunca se repete.
        $ultima = Filiado::query()
            ->lockForUpdate()
            ->pluck('registro')
            // Só os registros no formato da plataforma (2 letras + 8 números no começo).
            ->filter(fn (?string $registro): bool => is_string($registro) && preg_match('/^[A-Z]{2}\d{8}/', $registro) === 1)
            ->map(fn (string $registro): int => (int) substr($registro, 6, 4))
            ->max() ?? 0;

        // O ano é sempre o corrente; só a sequência vem da maior já usada.
        return Filiado::montarRegistro((int) now()->format('Y'), $ultima + 1, $uf, $pais);
    }
}
