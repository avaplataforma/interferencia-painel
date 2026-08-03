# WhatsApp

## Decisão

A integração usará a **WhatsApp Cloud API oficial**, com o recurso de
**coexistência** para manter cada número disponível tanto no aplicativo WhatsApp
Business quanto na API, conforme elegibilidade e regras vigentes da Meta.

## Escopo organizacional

Serão considerados seis números, associados às unidades:

1. Central.
2. Tijucas, separada da Central.
3. Itapema.
4. Porto Belo.
5. São João Batista.
6. Nova Trento.

A relação exata entre número, conta do WhatsApp Business, portfólio empresarial
e unidade deverá ser inventariada antes da implementação.

## Capacidades futuras

- Receber mensagens e atualizações de status por webhook.
- Enviar mensagens livres dentro das condições permitidas e templates aprovados
  quando exigidos.
- Organizar conversas por unidade e responsável.
- Vincular contatos e histórico ao CRM sem duplicação indevida.
- Registrar falhas, tentativas, entrega, leitura e contexto de consentimento.

## Requisitos técnicos

- HTTPS público e validação da assinatura dos webhooks.
- Verificação de token apenas no fluxo de configuração.
- Processamento idempotente para eventos repetidos ou fora de ordem.
- Filas, retentativas com recuo e fila de falhas.
- Tokens e segredos fora do banco quando possível, com rotação documentada.
- Identificadores externos separados dos IDs internos.
- Observabilidade por unidade e número, sem expor conteúdo sensível em logs.

## Coexistência

Coexistência não significa que aplicativo e API tenham comportamento idêntico.
Antes de ativar qualquer número, o projeto deverá validar na documentação oficial
vigente: elegibilidade, sincronização de histórico/contatos, dispositivos
vinculados, recursos suportados e possíveis efeitos operacionais. A ativação
deverá começar com um número piloto e ter plano de reversão.

## Conformidade e operação

- Registrar consentimento e origem quando aplicável.
- Respeitar políticas da plataforma, janela de atendimento e templates.
- Definir retenção, acesso, exportação e exclusão conforme LGPD.
- Criar procedimento para token expirado, webhook indisponível, perda de
  qualidade e bloqueio do número.
- Não usar mensagens de clientes em ambientes de desenvolvimento.

## Antes de implementar

- Confirmar ativos e administradores no ecossistema Meta.
- Validar disponibilidade atual da coexistência para cada número.
- Mapear responsáveis, volume, horários e processo de atendimento por unidade.
- Definir modelo de distribuição, transferência e encerramento de conversas.
- Aprovar política de consentimento e retenção com os responsáveis adequados.

## Fundação interna

O painel possui um cadastro administrativo de linhas, limitado inicialmente a
uma linha por unidade. Cada número guarda nome operacional, telefone em formato
internacional, unidade, situação e estado da futura conexão oficial.

O administrador global define explicitamente quais usuários podem acessar cada
linha. A liberação também exige que o usuário possua acesso à unidade
correspondente; remover a unidade revoga efetivamente a visualização da caixa.
Administradores globais mantêm visão integral.

A tela de Atendimento mostra somente as linhas autorizadas e informa que leitura
e envio permanecem aguardando a Cloud API oficial. Nenhuma automação de WhatsApp
Web, sessão por QR Code ou biblioteca não oficial faz parte desta fundação.

## Webhook e mensageria do piloto

O endpoint público do piloto é `GET|POST /api/whatsapp/webhook`. A verificação
compara o token em tempo constante e o recebimento exige a assinatura
`X-Hub-Signature-256`, calculada com o segredo do aplicativo Meta. Requisições
sem assinatura válida não são processadas.

Eventos são idempotentes e alimentam conversas e mensagens vinculadas à linha
pelo `phone_number_id`. Mensagens de texto e atualizações de entrega/leitura já
possuem persistência interna; outros tipos ficam identificados pelo tipo para
tratamento posterior. Conteúdo de webhook não é gravado nos logs.

Os segredos são fornecidos exclusivamente por variáveis de ambiente. O cadastro
da linha armazena apenas WABA ID e Phone Number ID, que não são credenciais.
## Caixa de entrada interna

A caixa de entrada mostra somente as linhas liberadas para o usuário e oferece filtros para todas as conversas, minhas conversas, conversas sem atendente e não lidas. Enquanto a empresa aguarda a aprovação da Meta, a interface inicial usa uma prévia demonstrativa não persistida e mantém o envio desativado.

O administrador também dispõe de um simulador seguro de recebimento. Ele grava conversas marcadas com `is_test`, permitindo validar caixa de entrada, atribuição, vínculo com CRM e follow-up sem abrir uma sessão do WhatsApp Web e sem enviar mensagens externas.

Todo usuário com acesso à linha e à respectiva unidade pode assumir uma conversa para si. A transferência para outro atendente exige a permissão `whatsapp.conversations.assign`, concedida aos perfis Administrador global e Gestor. A validação é feita novamente no servidor, independentemente dos controles exibidos na interface.

## Integração automática com o CRM

- Uma nova conversa procura primeiro um contato ativo com o mesmo telefone na unidade da linha.
- Havendo correspondência na mesma unidade, a conversa é vinculada automaticamente ao contato existente.
- Sem correspondência, o sistema cria um contato provisório com origem WhatsApp e status Novo.
- Telefones iguais em outras unidades não são vinculados automaticamente; a caixa de entrada exibe um alerta.
- Ao atribuir a conversa, o atendente do contato vinculado também é atualizado.

## Ciclo de atendimento

- A caixa separa conversas abertas, encerradas, atrasadas, não lidas, sem atendente e atribuídas ao usuário.
- Para encerrar, o atendente informa se o atendimento foi concluído ou se já existe retorno agendado.
- Quando a opção de retorno é escolhida, o sistema exige um follow-up pendente para o contato.
- Encerramentos e reaberturas são registrados no histórico do CRM.
- Uma nova mensagem recebida reabre automaticamente a conversa.

## Notificações operacionais

- O menu do WhatsApp exibe a quantidade de mensagens não lidas das linhas permitidas ao usuário.
- A central de notificações reúne mensagens não lidas e follow-ups atrasados ou previstos para hoje.
- Os totais respeitam o contexto de unidade, as linhas autorizadas e o atendente responsável.
- Os contadores são atualizados em segundo plano a cada 30 segundos enquanto a página estiver visível.
