# Identidade e Acesso

## Escopo inicial

A primeira versão de identidade do PAINEL INTER 📊 possui usuários, papéis,
permissões e escopo explícito por unidade. Nenhuma senha é armazenada em texto
puro; hashes usam Argon2id com parâmetros versionáveis.

## Estrutura

- `users`: identidade, estado, hash, bloqueio e último acesso.
- `roles` e `permissions`: autorização por capacidades estáveis.
- `user_roles` e `role_permissions`: relações muitos-para-muitos.
- `units`: unidades organizacionais, incluindo as seis unidades iniciais aprovadas.
- `user_unit_scopes`: unidades que cada usuário pode acessar.

Papéis iniciais: `super_admin`, `manager` e `agent`. O administrador global
recebe todas as permissões e todas as unidades. Os demais papéis serão detalhados
quando os fluxos reais de operação forem aprovados.

## Autenticação

- E-mail normalizado e resposta de falha genérica.
- Senha verificada com API nativa segura do PHP.
- Sessão regenerada após login e logout.
- Token CSRF rotacionado junto com a autenticação.
- Cinco falhas consecutivas bloqueiam a conta por 15 minutos.
- Usuários inativos não podem iniciar ou manter sessão.
- Hash é atualizado no login quando os parâmetros mudarem.

## Primeiro administrador

Depois da migração, execute como o usuário da aplicação:

```bash
php bin/console user:create-admin "Nome completo" email@dominio.com
```

A senha é solicitada duas vezes com eco desabilitado. Ela deve ter no mínimo 12
caracteres e nunca deve ser fornecida como argumento, variável compartilhada ou
mensagem de chat.

## Limites desta entrega

- Administradores com `users.manage` podem listar, criar e editar usuários,
  atribuir papéis, selecionar unidades, trocar senha e ativar/desativar contas.
- Administradores com `units.manage` podem listar, criar, editar, ativar e
  desativar unidades. O código interno é permanente, e novas unidades são
  vinculadas automaticamente aos usuários com acesso global.
- O sistema impede desativar ou remover o papel do último administrador global.
- Ainda não há editor da composição interna de cada papel.
- Recuperação de senha e segundo fator serão especificados separadamente.
- Limitação atual é por conta; limitação adicional por origem será adicionada
  antes de expor autenticação a uma operação de maior escala.
