/**
 * CAMPO DE DADOS PARA PAGAMENTO (#41): rótulo + campo de texto + erro, usado
 * na tela "Dados para pagamento" do Comando Geral e no painel de pagamento da
 * edição do curso.
 *
 * Por que mora em "components" e não dentro da página: quando um componente
 * importa um pedaço de DENTRO de uma página, o Vite junta os dois no pacote e
 * a página perde a sua entrada própria. Aí o servidor não acha a página e dá
 * erro 500 na publicação (foi o que a CI pegou em 14/10/2026).
 */
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Um campo de texto com rótulo e erro (para não repetir o mesmo bloco 5 vezes).
 *
 * @param nome O name do campo. Ex.: 'pix_chave'
 * @param rotulo O texto do rótulo. Ex.: 'Chave PIX'
 * @param valor O valor atual (vazio se null).
 * @param maximo Quantas letras no máximo. Ex.: 140
 * @param exemplo O texto de exemplo dentro do campo.
 * @param erro A mensagem de erro do servidor, se houver.
 * @returns O bloco rótulo + campo + erro.
 */
export function CampoPagamento({
    nome,
    rotulo,
    valor,
    maximo,
    exemplo,
    erro,
}: {
    nome: string;
    rotulo: string;
    valor: string | null;
    maximo: number;
    exemplo: string;
    erro?: string;
}) {
    // id único por campo, para ligar o rótulo ao campo (acessibilidade).
    const id = `pag-${nome}`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{rotulo}</Label>
            <Input
                id={id}
                name={nome}
                defaultValue={valor ?? ''}
                maxLength={maximo}
                placeholder={exemplo}
            />
            <InputError message={erro} />
        </div>
    );
}
