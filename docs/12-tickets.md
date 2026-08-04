# Tickets internos

O módulo Tickets organiza demandas internas entre usuários do PAINEL INTER.

## Fluxo

- Todo usuário operacional pode abrir tickets nas unidades às quais possui acesso.
- Cada ticket possui solicitante, responsável pelo atendimento, unidade, prioridade, prazo e status.
- O ticket pode ser vinculado opcionalmente a um aluno ou contato do CRM da mesma unidade, com atalho direto para o cadastro.
- Solicitante e responsável podem conversar no histórico do ticket.
- O responsável pode alterar o andamento; Gestor, Sede e Admin System também podem redistribuir e administrar tickets das unidades permitidas.
- Toda abertura, resposta, redistribuição e mudança de status é registrada em histórico imutável.

## Status

- Aberto
- Em andamento
- Aguardando
- Resolvido
- Fechado

## Prioridades

- Baixa
- Normal
- Alta
- Urgente

## Notificações

O sininho informa tickets atualizados e atrasados. Um ticket deixa de ser considerado não lido quando o usuário abre sua página. O contador do menu é atualizado periodicamente junto das demais notificações do painel.

## Permissões

- `tickets.view`: consultar tickets visíveis.
- `tickets.create`: abrir tickets.
- `tickets.manage`: acompanhar, redistribuir e alterar tickets das unidades permitidas.

Atendentes recebem consulta e abertura. Gestor, Sede e Admin System recebem também gerenciamento.
