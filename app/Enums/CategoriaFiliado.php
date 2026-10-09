<?php

/*
 * CATEGORIA do filiado = a FUNÇÃO dele no IBTO (#37, Gleyson 09/10/2026).
 * É diferente do NÍVEL, que é a GRADUAÇÃO (cadastro de níveis: Praticante,
 * Monitor I–II, Instrutor I–V, Instrutor Expert I–V).
 *
 * Exemplo: categoria "Administrador de Equipe" (o que ele faz) e nível
 * "Instrutor III" (a graduação dele). Lista fechada: o banco e as telas só
 * aceitam estes valores.
 *
 * A ordem é uma PROGRESSÃO, do zero ao topo (Gleyson, 09/10/2026): Praticante
 * (iniciante) → Monitor → Instrutor → Instrutor Expert; o Administrador de
 * Equipe é a função de quem cuida de uma equipe.
 * "Praticante" é o filiado iniciante; não confundir com o PAPEL "Aluno" (#36),
 * que é de quem cria conta só para comprar curso aberto ao público (não filiado).
 */

namespace App\Enums;

enum CategoriaFiliado: string
{
    // O texto à direita é o que fica gravado no banco (sem acento nem espaço).
    case Praticante = 'praticante';
    case Monitor = 'monitor';
    case Instrutor = 'instrutor';
    case InstrutorExpert = 'instrutor_expert';
    case AdmEquipe = 'adm_equipe';

    /**
     * Nome para mostrar na tela (e para reconhecer a coluna "Categoria" da planilha).
     *
     * @return string Ex.: "Instrutor Expert".
     */
    public function rotulo(): string
    {
        // Nomes escolhidos pelo Gleyson (09/10/2026). O de "Instrutor Expert" também
        // reconhece a coluna da planilha ("INSTRUTOR EXPERT" bate sem relacionar).
        return match ($this) {
            self::Praticante => 'Praticante',
            self::Monitor => 'Monitor',
            self::Instrutor => 'Instrutor',
            self::InstrutorExpert => 'Instrutor Expert',
            self::AdmEquipe => 'Administrador de Equipe',
        };
    }
}
