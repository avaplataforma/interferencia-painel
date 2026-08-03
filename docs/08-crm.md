# CRM

## Visão

O CRM será próprio e integrado ao PAINEL INTER 📊. Ele deve refletir o
processo real da empresa, manter histórico útil e conversar naturalmente com as
unidades e o WhatsApp. Esta fase documenta direção, não define telas ou tabelas.

## Resultados esperados

- Visão única e controlada do relacionamento com cada contato.
- Organização de oportunidades, etapas, responsáveis e próximas ações.
- Histórico de interações sem perder a origem e a unidade.
- Indicadores confiáveis de operação e conversão.

## Domínios conceituais

- Contatos e meios de contato.
- Oportunidades e funis.
- Etapas e motivos de ganho/perda.
- Atividades, tarefas, notas e responsáveis.
- Origens, campanhas e unidades.
- Interações e vínculos com conversas do WhatsApp.

Esses conceitos são hipóteses a validar com usuários antes de modelar o banco.

## Regras de desenho

- Evitar duplicação de pessoas entre números/unidades sem fundir dados de forma
  irreversível.
- Preservar origem, autoria e data das alterações importantes.
- Aplicar permissões e visibilidade por papel e unidade.
- Separar dado operacional de métricas derivadas.
- Tornar campos obrigatórios apenas quando sustentados pelo processo real.
- Não transformar mensagens brutas em cadastro definitivo sem validação.

## Descoberta necessária

Antes da implementação, entrevistar responsáveis das unidades e documentar:

1. Como um contato entra e quando vira oportunidade.
2. Funis, etapas, responsáveis e regras de transferência.
3. Informações essenciais e fontes atuais.
4. Atividades, prazos, alertas e critérios de encerramento.
5. Relatórios e decisões que cada indicador deve apoiar.
6. Exceções entre Central, Tijucas e demais unidades.

## Integração com WhatsApp

A conversa e o contato do CRM são conceitos relacionados, mas distintos. Um
contato poderá participar de várias conversas e canais; uma mensagem não deve
criar automaticamente duplicatas. A estratégia de identificação e mesclagem só
será definida após analisar dados e fluxos reais.

## Primeira entrega operacional

O cadastro inicial de contatos possui nome, telefone/WhatsApp, e-mail, documento,
unidade responsável, responsável, status configurável, data e origem do cadastro,
curso, interesse de 0 a 10, polo/cidade de origem, observações e situação ativa.
Os status iniciais são Novo, Negociação, Sem interesse e Matriculado.

O formulário único do site oficial envia o código público do polo. A API valida
esse código contra unidades ativas e resolve internamente o `unit_id`; IDs
numéricos nunca são aceitos do visitante. A integração exige chave Bearer,
`submission_id` idempotente, data/hora original e pode registrar consentimento e
versão do aviso de privacidade. Telefone e e-mail ajudam a detectar duplicidade.

Usuários autorizados em mais de uma unidade podem selecionar **Todas as
unidades** para consulta consolidada. Cadastros e edições internas continuam
exigindo uma unidade específica.

## Etiquetas

- O catálogo de etiquetas é global para a empresa e cada etiqueta possui nome, cor e situação.
- Um contato pode receber várias etiquetas, independentemente de sua unidade.
- A listagem de contatos mostra as etiquetas e permite filtrar por uma etiqueta específica.
- Administradores globais e gestores podem cadastrar e editar etiquetas pelo menu ADM.
- Usuários com permissão para gerenciar contatos podem aplicar ou remover etiquetas existentes, sem alterar o catálogo.

Os status do CRM também são configuráveis. Administradores globais e gestores
podem cadastrar nome, cor, ordem de exibição e situação pelo menu ADM. Apenas
status ativos aparecem para seleção nos novos cadastros de contato.

## Follow-up

Cada contato pode possuir vários acompanhamentos com próxima ação, data e hora,
atendente e observações. A agenda do CRM reúne os registros das unidades que o
usuário pode acessar e permite acompanhar as situações pendente, concluída e
cancelada. O histórico permanece vinculado ao contato.

A agenda destaca acompanhamentos atrasados, previstos para hoje e futuros. Pode
ser filtrada por situação, período e atendente. Ações rápidas permitem concluir,
cancelar ou concluir e abrir imediatamente o próximo acompanhamento. O painel
inicial apresenta os totais da unidade ativa ou da visão consolidada autorizada.
O cabeçalho exibe um alerta com sino para o usuário logado quando existem
retornos atribuídos a ele que estão atrasados ou previstos para o dia atual.

## Formulários externos

Gestores e administradores podem criar formulários incorporáveis por iframe.
Cada formulário possui domínio autorizado, identificador público, etiqueta
automática e status inicial. O Polo/Cidade escolhido pelo visitante resolve a
Unidade responsável; a origem é identificada no servidor pela configuração do
formulário, sem aceitar uma etiqueta enviada pelo navegador. O painel gera o
código do iframe e contabiliza novos contatos recebidos.

## Novos contatos no painel

A visão geral destaca os contatos que permanecem no status Novo, respeitando a
unidade ativa e todas as unidades autorizadas ao usuário. A lista recente pode
ser filtrada pela origem do cadastro e pela etiqueta que identifica o site ou o
formulário externo. A origem WhatsApp já aparece na visão operacional e será
alimentada quando a integração oficial desse canal entrar em operação.
