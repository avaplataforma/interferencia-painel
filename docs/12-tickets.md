# Tickets internos

O módulo Tickets organiza demandas internas entre usuários do PAINEL INTER.

## Fluxo

- Todo usuário operacional pode abrir tickets nas unidades às quais possui acesso.
- Cada ticket possui solicitante, setor responsável pelo atendimento, unidade, prioridade, prazo e status.
- Todo ticket deve ser vinculado a um aluno ou contato do CRM da mesma unidade, com atalho direto para o cadastro.
- Solicitante e integrantes do setor podem conversar no histórico do ticket.
- Integrantes do setor podem alterar o andamento; Gestor, Sede e Admin System também administram tickets das unidades permitidas.
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

Atendentes recebem consulta e abertura. Gestor, Sede e Admin System recebem também gerenciamento. Sede e Admin System podem cadastrar setores e definir seus usuários.

## Setores e anexos

Os tickets são direcionados para setores, como Comercial e Pedagógico. Os integrantes definidos no cadastro do setor recebem os avisos e podem trabalhar no chamado. O vínculo com aluno/contato é obrigatório e limitado à mesma unidade. Imagens, PDF, Word e áudio de até 16 MB podem ser anexados na abertura ou posteriormente.
