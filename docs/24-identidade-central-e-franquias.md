# Identidades separadas entre ADM Central e franquias

## Decisão

O ADM Central e os painéis das franquias possuem cadastros de usuários fisicamente separados.

- `platform_users`, `platform_roles` e tabelas relacionadas pertencem exclusivamente ao Mundo Inter.
- `users`, `roles`, vínculos organizacionais e escopos de unidade pertencem às franquias.
- A sessão guarda o contexto de autenticação (`platform` ou `franchise`) e não aceita reaproveitar uma identidade do outro contexto.
- Colaboradores centrais não recebem unidades de franquias.
- Perfis e permissões centrais são mantidos separadamente dos perfis operacionais das franquias.

## Migração segura

A conta central `contato@interferencia.com.br` é copiada com a mesma senha criptografada para a nova tabela. Caso ela não exista, o primeiro Admin System ativo é copiado como contingência, evitando bloqueio do ADM Central.

## Contratos e pagamentos

Liberar o contrato, enviar o link de assinatura, gerar o link de pagamento e enviá-lo ao parceiro são ações manuais e independentes do ADM Central. A assinatura não dispara cobrança automaticamente.
