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
