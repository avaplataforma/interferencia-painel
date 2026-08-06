# Gateways de pagamento

## Decisão

O Asaas continuará como primeiro gateway e integração principal da operação
atual, mas o módulo Financeiro do Mundo Inter será independente de fornecedor.
Cada organização poderá utilizar uma ou mais conexões de pagamento homologadas,
como Asaas, PagBank/PagSeguro, Stone ou futuras integrações.

Cliente, pedido, matrícula, cobrança e pagamento são registros do Mundo Inter.
O gateway é o processador externo e não será usado como identidade principal
desses registros.

## Modelo conceitual

```text
Organização
  → Conta/conexão de pagamento
  → Capacidades habilitadas
  → Regra de roteamento

Pedido ou matrícula
  → Cobrança interna
  → Tentativa de pagamento
  → Operação no gateway escolhido
  → Eventos e conciliação
  → Recebimento, falha, estorno ou disputa
```

Uma cobrança interna poderá possuir várias tentativas, inclusive em gateways
diferentes, sem duplicar o pedido ou a matrícula.

## Conexões por organização

Cada conexão registrará:

- organização proprietária e unidades autorizadas;
- provedor, ambiente, situação e nome interno;
- credenciais criptografadas e segredo de webhook;
- conta, carteira ou recebedor remoto;
- formas de pagamento e moedas habilitadas;
- limites, taxas e prazos de liquidação conhecidos;
- capacidades de Pix, boleto, cartão, recorrência, checkout, split, estorno,
  antecipação e conciliação;
- data do último teste, saúde e erros recentes.

Uma organização poderá ter uma conexão principal e conexões alternativas. A
plataforma não presumirá que todos os gateways oferecem os mesmos recursos.

## Roteamento

A escolha do gateway será feita no servidor conforme regras autorizadas, por
exemplo:

- padrão da organização;
- unidade ou marca;
- oferta ou fornecedor do curso;
- forma de pagamento;
- recorrência ou cobrança avulsa;
- necessidade de split;
- disponibilidade e situação da conexão.

A primeira versão usará uma conexão padrão por organização. Roteamento avançado
e contingência automática serão implantados somente após conciliação e testes
financeiros específicos.

## Capacidades e experiência uniforme

O núcleo financeiro trabalhará com operações comuns:

- criar e consultar cobrança;
- alterar uma cobrança ainda permitida;
- gerar Pix, boleto, fatura ou checkout;
- criar e administrar recorrência;
- consultar situação;
- cancelar, estornar ou reembolsar quando suportado;
- receber e conciliar eventos;
- consultar taxas, liquidações e repasses disponíveis.

Cada conector traduz essas operações para a API do provedor. Quando uma função
não existir, a interface deve informar “não disponível nesta conexão”, sem
simular suporte.

## Identificadores

IDs externos nunca serão globais. Toda referência remota será identificada por:

```text
payment_connection_id + resource_type + external_id
```

As tabelas de negócio usarão IDs internos imutáveis. URLs, relatórios e vínculos
com alunos não dependerão diretamente de um ID do Asaas ou de outro gateway.

## Webhooks e idempotência

- Cada conexão terá endpoint lógico e segredo próprios.
- O evento será identificado pelo provedor, conexão e ID externo.
- Reentregas não poderão aplicar novamente um pagamento ou estorno.
- O conteúdo original será preservado com dados sensíveis protegidos.
- O recebimento apenas registra e enfileira; o processamento será resiliente.
- Eventos fora de ordem serão reconciliados com o estado consultado na API.

## Split, comissão e repasse

As regras comerciais de comissão, royalty e participação pertencem ao Mundo
Inter. O conector transforma a regra em split nativo quando o gateway suportar.
Quando não suportar, a venda deverá usar outro fluxo previamente homologado; o
sistema não prometerá split inexistente.

O valor e os participantes aplicados serão congelados no pedido/cobrança para
auditoria, mesmo que a configuração seja alterada depois.

## Migração e troca de gateway

- Cobranças existentes permanecem administradas pelo gateway que as criou.
- Assinaturas não serão transferidas automaticamente entre provedores.
- Novas cobranças podem passar a usar a nova conexão após uma data de corte.
- O histórico financeiro continuará consolidado no Mundo Inter.
- Uma conexão desativada fica disponível em modo de consulta para documentos,
  eventos, estornos e auditoria ainda necessários.

## Permissões

- **Admin Mundo Inter:** homologa conectores e capacidades da plataforma.
- **Admin da organização:** configura suas conexões e escolhe a conexão padrão.
- **Sede/financeiro autorizado:** opera cobranças dentro das permissões atuais.
- Credenciais, webhooks, testes e troca de gateway são ações auditadas.

## Entidades previstas

- `payment_providers`
- `payment_connections`
- `payment_connection_capabilities`
- `payment_routing_rules`
- `financial_customers`
- `financial_charges`
- `payment_attempts`
- `payment_transactions`
- `payment_webhook_events`
- `payment_settlements`

Os nomes são conceituais e serão confirmados na implementação.

## Estratégia de implantação

1. Encapsular a integração atual do Asaas atrás de um contrato interno.
2. Converter IDs e eventos atuais para uma conexão Asaas da organização inicial.
3. Manter todas as funções existentes e comparar resultados antes da migração.
4. Criar um conector falso para testar capacidades ausentes e isolamento.
5. Integrar um segundo gateway somente em sandbox.
6. Ativar uma organização piloto com uma conexão diferente do Asaas.
