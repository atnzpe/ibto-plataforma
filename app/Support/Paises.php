<?php

/*
 * LISTA DE PAÍSES (#11, decisão do Gleyson em 24/10/2026): para o filiado que
 * mora fora do Brasil e para a Rede Nacional separar os credenciados por país.
 *
 * Cada país pela sigla internacional de 2 letras (ISO 3166-1, a mesma do
 * passaporte e dos domínios: BR, PT, US...) e o nome em português. A sigla vai
 * no início do número de registro do filiado (ex.: PT20260046, Portugal).
 * Lista fechada: um país digitado errado não entra no cadastro.
 */

namespace App\Support;

class Paises
{
    // O Brasil: país padrão de todo filiado (é o único com estados/UF no cadastro).
    public const BRASIL = 'BR';

    /**
     * Sigla => nome em português, em ordem alfabética do nome (o Brasil fica primeiro
     * nas telas, por ser o padrão). Ex.: 'PT' => 'Portugal'.
     *
     * @var array<string, string>
     */
    public const LISTA = [
        'BR' => 'Brasil',
        'AF' => 'Afeganistão', 'ZA' => 'África do Sul', 'AL' => 'Albânia', 'DE' => 'Alemanha', 'AD' => 'Andorra',
        'AO' => 'Angola', 'AG' => 'Antígua e Barbuda', 'SA' => 'Arábia Saudita', 'DZ' => 'Argélia', 'AR' => 'Argentina',
        'AM' => 'Armênia', 'AU' => 'Austrália', 'AT' => 'Áustria', 'AZ' => 'Azerbaijão', 'BS' => 'Bahamas',
        'BH' => 'Bahrein', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BE' => 'Bélgica', 'BZ' => 'Belize',
        'BJ' => 'Benin', 'BY' => 'Bielorrússia', 'BO' => 'Bolívia', 'BA' => 'Bósnia e Herzegovina', 'BW' => 'Botsuana',
        'BN' => 'Brunei', 'BG' => 'Bulgária', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'BT' => 'Butão',
        'CV' => 'Cabo Verde', 'CM' => 'Camarões', 'KH' => 'Camboja', 'CA' => 'Canadá', 'QA' => 'Catar',
        'KZ' => 'Cazaquistão', 'TD' => 'Chade', 'CL' => 'Chile', 'CN' => 'China', 'CY' => 'Chipre',
        'CO' => 'Colômbia', 'KM' => 'Comores', 'CG' => 'Congo', 'CD' => 'Congo (Rep. Democrática)', 'KP' => 'Coreia do Norte',
        'KR' => 'Coreia do Sul', 'CI' => 'Costa do Marfim', 'CR' => 'Costa Rica', 'HR' => 'Croácia', 'CU' => 'Cuba',
        'DK' => 'Dinamarca', 'DJ' => 'Djibuti', 'DM' => 'Dominica', 'EG' => 'Egito', 'SV' => 'El Salvador',
        'AE' => 'Emirados Árabes Unidos', 'EC' => 'Equador', 'ER' => 'Eritreia', 'SK' => 'Eslováquia', 'SI' => 'Eslovênia',
        'ES' => 'Espanha', 'US' => 'Estados Unidos', 'EE' => 'Estônia', 'SZ' => 'Essuatíni', 'ET' => 'Etiópia',
        'FJ' => 'Fiji', 'PH' => 'Filipinas', 'FI' => 'Finlândia', 'FR' => 'França', 'GA' => 'Gabão',
        'GM' => 'Gâmbia', 'GH' => 'Gana', 'GE' => 'Geórgia', 'GD' => 'Granada', 'GR' => 'Grécia',
        'GT' => 'Guatemala', 'GY' => 'Guiana', 'GF' => 'Guiana Francesa', 'GN' => 'Guiné', 'GQ' => 'Guiné Equatorial',
        'GW' => 'Guiné-Bissau', 'HT' => 'Haiti', 'HN' => 'Honduras', 'HU' => 'Hungria', 'YE' => 'Iêmen',
        'IN' => 'Índia', 'ID' => 'Indonésia', 'IQ' => 'Iraque', 'IR' => 'Irã', 'IE' => 'Irlanda',
        'IS' => 'Islândia', 'IL' => 'Israel', 'IT' => 'Itália', 'JM' => 'Jamaica', 'JP' => 'Japão',
        'JO' => 'Jordânia', 'KW' => 'Kuwait', 'LA' => 'Laos', 'LS' => 'Lesoto', 'LV' => 'Letônia',
        'LB' => 'Líbano', 'LR' => 'Libéria', 'LY' => 'Líbia', 'LI' => 'Liechtenstein', 'LT' => 'Lituânia',
        'LU' => 'Luxemburgo', 'MK' => 'Macedônia do Norte', 'MG' => 'Madagascar', 'MY' => 'Malásia', 'MW' => 'Malawi',
        'MV' => 'Maldivas', 'ML' => 'Mali', 'MT' => 'Malta', 'MA' => 'Marrocos', 'MU' => 'Maurício',
        'MR' => 'Mauritânia', 'MX' => 'México', 'MM' => 'Mianmar', 'MZ' => 'Moçambique', 'MD' => 'Moldávia',
        'MC' => 'Mônaco', 'MN' => 'Mongólia', 'ME' => 'Montenegro', 'NA' => 'Namíbia', 'NP' => 'Nepal',
        'NI' => 'Nicarágua', 'NE' => 'Níger', 'NG' => 'Nigéria', 'NO' => 'Noruega', 'NZ' => 'Nova Zelândia',
        'OM' => 'Omã', 'NL' => 'Países Baixos', 'PS' => 'Palestina', 'PA' => 'Panamá', 'PG' => 'Papua-Nova Guiné',
        'PK' => 'Paquistão', 'PY' => 'Paraguai', 'PE' => 'Peru', 'PL' => 'Polônia', 'PT' => 'Portugal',
        'KE' => 'Quênia', 'KG' => 'Quirguistão', 'GB' => 'Reino Unido', 'CF' => 'República Centro-Africana', 'CZ' => 'República Tcheca',
        'DO' => 'República Dominicana', 'RO' => 'Romênia', 'RW' => 'Ruanda', 'RU' => 'Rússia', 'WS' => 'Samoa',
        'SM' => 'San Marino', 'LC' => 'Santa Lúcia', 'KN' => 'São Cristóvão e Névis', 'ST' => 'São Tomé e Príncipe', 'VC' => 'São Vicente e Granadinas',
        'SN' => 'Senegal', 'SL' => 'Serra Leoa', 'RS' => 'Sérvia', 'SG' => 'Singapura', 'SY' => 'Síria',
        'SO' => 'Somália', 'LK' => 'Sri Lanka', 'SD' => 'Sudão', 'SS' => 'Sudão do Sul', 'SE' => 'Suécia',
        'CH' => 'Suíça', 'SR' => 'Suriname', 'TH' => 'Tailândia', 'TW' => 'Taiwan', 'TJ' => 'Tajiquistão',
        'TZ' => 'Tanzânia', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TO' => 'Tonga', 'TT' => 'Trinidad e Tobago',
        'TN' => 'Tunísia', 'TM' => 'Turcomenistão', 'TR' => 'Turquia', 'UA' => 'Ucrânia', 'UG' => 'Uganda',
        'UY' => 'Uruguai', 'UZ' => 'Uzbequistão', 'VU' => 'Vanuatu', 'VA' => 'Vaticano', 'VE' => 'Venezuela',
        'VN' => 'Vietnã', 'ZM' => 'Zâmbia', 'ZW' => 'Zimbábue',
    ];

    /**
     * O nome de um país pela sigla.
     *
     * @param  string|null  $sigla  Ex.: 'PT'.
     * @return string|null Ex.: 'Portugal'; null se a sigla não estiver na lista.
     */
    public static function nome(?string $sigla): ?string
    {
        return self::LISTA[strtoupper((string) $sigla)] ?? null;
    }
}
