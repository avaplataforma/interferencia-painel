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
9. Se houver cobrança prevista, o ADM escolhe forma de pagamento e vencimento, confere os dados e autoriza a emissão real no Asaas.
10. O sistema procura o parceiro pelo CNPJ antes de criar um cliente, guarda os identificadores do cliente e da cobrança e permite atualizar a situação diretamente pelo Asaas.

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
- Cobrança é opcional e nunca é emitida antes da assinatura e da confirmação manual do ADM Central.
- O Asaas aceita clientes duplicados; por isso o sistema consulta o CNPJ e reutiliza o cadastro existente antes de criar outro.
- Uma trava interna impede uma segunda emissão para o mesmo contrato e a referência externa identifica sua origem como `mundo-inter:franchise-contract:{id}`.
- A situação financeira fica vinculada ao contrato, com acesso ao documento de cobrança e atualização manual segura.
