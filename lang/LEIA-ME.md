# Pasta `lang`: as mensagens do servidor em português

Criada em 14/10/2026, antes do piloto. Antes dela, o servidor respondia em inglês (ex.: "These credentials do not match our records." quando a senha estava errada).

O Laravel usa esta pasta porque o `.env` tem `APP_LOCALE=pt_BR`.

| Arquivo                | O que traduz                                                                                            |
| ---------------------- | ------------------------------------------------------------------------------------------------------- |
| `pt_BR/auth.php`       | Login: senha errada, muitas tentativas                                                                  |
| `pt_BR/passwords.php`  | "Esqueci minha senha": link enviado, link vencido                                                       |
| `pt_BR/pagination.php` | Botões "Anterior" e "Próxima" das listas                                                                |
| `pt_BR/validation.php` | Mensagens embaixo dos campos (ex.: "O campo e-mail é obrigatório.") e os nomes dos campos em português  |
| `pt_BR.json`           | Frases soltas: os e-mails de "Esqueci minha senha" e de confirmação, e avisos como "Perfil atualizado." |

O `pt_BR.json` não aceita comentários (é um arquivo JSON): cada linha é "frase em inglês": "frase em português".

A publicação (`.github/workflows/deploy-homologacao.yml`) envia esta pasta para o servidor e confere se ela foi junto.
