# Contratos e assinatura digital

## Objetivo

Controlar no ADM Central os contratos de prestação de serviços vinculados às solicitações de novas franquias.

## Fluxo

1. O ADM mantém modelos versionados de contrato.
2. Na solicitação, gera um contrato informando condições comerciais, vigência e eventual cobrança.
3. O conteúdo é congelado como uma cópia independente do modelo.
4. O contrato nasce como rascunho e precisa ser conferido.
5. O ADM libera o link público de assinatura.
6. O representante visualiza, identifica-se e declara o aceite integral.
7. O sistema registra nome, documento, e-mail, data, hora, IP, navegador e um hash SHA-256 de integridade.
8. A solicitação passa a indicar contrato assinado.

## Situações

- `draft`: rascunho interno;
- `sent`: liberado para assinatura;
- `viewed`: aberto pelo signatário;
- `signed`: assinado e com evidências registradas;
- `cancelled`: cancelado.

## Segurança e validade

- O link público usa token aleatório de 256 bits.
- O conteúdo assinado não acompanha alterações futuras do modelo.
- O código de integridade permite detectar alterações no documento ou nas evidências.
- A página assinada pode ser impressa ou salva como PDF com as evidências.
- Cobrança é opcional e registrada como previsão. A emissão automática pelo Asaas será ligada em fase posterior, após validação das regras comerciais.
