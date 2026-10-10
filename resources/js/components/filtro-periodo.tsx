/**
 * FILTRO DE PERÍODO (pedido do Gleyson, 10/10/2026): todo filtro de data é por
 * DATA INICIAL e DATA FINAL ("De" e "Até"), com o atalho "Este mês". Usado no
 * livro-caixa e no histórico. A regra de verdade (padrão, datas inválidas ou
 * invertidas) fica no servidor: App\Support\Periodo.
 * Responsivo: no celular, os dois campos lado a lado e o botão embaixo.
 */
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Primeiro e último dia do mês de hoje, no formato do campo.
 *
 * @returns Ex.: { de: '2026-10-01', ate: '2026-10-31' }
 */
function esteMes(): { de: string; ate: string } {
    const hoje = new Date();
    // 'sv-SE' escreve a data como AAAA-MM-DD (o formato do campo de data).
    const formato = (data: Date) => data.toLocaleDateString('sv-SE');

    return {
        de: formato(new Date(hoje.getFullYear(), hoje.getMonth(), 1)),
        // Dia 0 do mês seguinte = último dia deste mês.
        ate: formato(new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0)),
    };
}

/**
 * @param de A data inicial atual (vazia = sem filtro). Ex.: '2026-10-01'
 * @param ate A data final atual. Ex.: '2026-10-31'
 * @param aoMudar Chamada com o período novo quando a pessoa troca uma data ou clica em "Este mês".
 * @param prefixo Começo dos ids dos campos (para não repetir na página). Ex.: 'historico'
 * @returns Os campos "De", "Até" e o botão "Este mês".
 */
export default function FiltroPeriodo({
    de,
    ate,
    aoMudar,
    prefixo = 'periodo',
}: {
    de: string;
    ate: string;
    aoMudar: (periodo: { de: string; ate: string }) => void;
    prefixo?: string;
}) {
    return (
        <fieldset className="grid grid-cols-2 items-end gap-3 sm:flex sm:flex-wrap">
            <legend className="sr-only">Período</legend>
            <div className="grid gap-1">
                <Label htmlFor={`${prefixo}-de`}>De</Label>
                <Input
                    id={`${prefixo}-de`}
                    type="date"
                    value={de}
                    max={ate || undefined}
                    // Só recarrega com a data completa (o campo vazio é ignorado).
                    onChange={(evento) =>
                        evento.target.value &&
                        aoMudar({ de: evento.target.value, ate })
                    }
                />
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`${prefixo}-ate`}>Até</Label>
                <Input
                    id={`${prefixo}-ate`}
                    type="date"
                    value={ate}
                    min={de || undefined}
                    onChange={(evento) =>
                        evento.target.value &&
                        aoMudar({ de, ate: evento.target.value })
                    }
                />
            </div>
            <Button
                type="button"
                variant="outline"
                className="col-span-2 sm:col-span-1"
                onClick={() => aoMudar(esteMes())}
            >
                Este mês
            </Button>
        </fieldset>
    );
}
