# Perfis e financeiro das franquias

O **ADM Central** e o **ADM Franquias** possuem tabelas próprias de usuários, perfis, permissões e vínculos. Embora os nomes sejam iguais, as permissões não são compartilhadas.

## ADM Central

- Admin: acesso estrutural completo.
- Gestor: operação da rede, colaboradores, franquias, contratos, cobranças, tickets e personalização.
- Gerente: franquias, contratos, cobranças e tickets, sem alterar perfis estruturais.
- Atendente: consulta de franquias e atendimento de tickets.

## ADM Franquias

- Admin: acesso completo à própria franquia.
- Gestor: operação ampla, sem configurações estruturais reservadas.
- Gerente: gestão operacional conforme permissões locais.
- Atendente: atendimento conforme unidades autorizadas.

## Asaas por franquia

Cada franquia pode armazenar seu `walletId`, situação de validação e ativação do split. A chave da API continua centralizada e protegida. O split só pode ser ativado com a wallet validada e vale apenas para novas cobranças.
