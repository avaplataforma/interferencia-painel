# Visão Geral

## Propósito

O PAINEL INTER 📊 será o sistema central de gestão da empresa. Seu objetivo
é reunir operação, relacionamento com clientes, usuários, unidades e canais de
WhatsApp em uma plataforma única, auditável e preparada para evolução contínua.

## Contexto

- Produto: **PAINEL INTER 📊**.
- Endereço: `https://interferencia.com.br/painel`.
- Infraestrutura: VPS própria.
- Abrangência inicial: seis números/unidades.

| Identificador conceitual | Unidade | Observação |
|---|---|---|
| sede-central-tijucas | Central | Operação central |
| filial-tijucas | Tijucas | Unidade separada da Central |
| itapema | Itapema | Unidade local |
| porto-belo | Porto Belo | Unidade local |
| sao-joao-batista | São João Batista | Unidade local |
| nova-trento | Nova Trento | Unidade local |

Os identificadores são propostas estáveis para configuração e URLs; sua adoção
definitiva ocorrerá ao modelar o banco.

## Objetivos

- Oferecer uma visão centralizada sem perder a segregação por unidade.
- Sustentar um CRM próprio e adequado ao processo da empresa.
- Integrar os seis números pela API oficial do WhatsApp.
- Manter o aplicativo WhatsApp Business operacional por coexistência.
- Permitir crescimento modular, com segurança e rastreabilidade.

## Fora do escopo desta fase

- Login, usuários e permissões.
- CRUDs e fluxos operacionais.
- CRM, mensageria e webhooks.
- Cadastro real das unidades e números.
- Provisionamento da VPS, banco ou Meta Business.

## Princípios

1. Segurança e privacidade desde o desenho.
2. Regra de negócio dentro do módulo responsável.
3. Alterações de banco versionadas e reversíveis quando possível.
4. Segredos apenas no ambiente, nunca no repositório.
5. Observabilidade e auditoria como requisitos, não complementos.
6. Interface acessível, responsiva e consistente.
