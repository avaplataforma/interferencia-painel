# Mundo Inter

## Visão

O **Mundo Inter** será o ecossistema central que hospeda o PAINEL INTER e os
produtos digitais oferecidos a redes, franquias e unidades educacionais. A
plataforma deve crescer a partir da aplicação atual, preservando um único núcleo
de código e separando com rigor os dados e as configurações de cada organização.

## Produtos do ecossistema

- **PAINEL INTER:** operação interna, CRM, alunos, matrículas, financeiro,
  WhatsApp, tickets, documentos e integrações.
- **Site da franquia:** presença institucional, unidades, páginas comerciais,
  catálogo de cursos, campanhas, captação de leads e futura loja virtual.
- **AVA:** uma organização poderá usar seu Moodle dedicado, o Moodle Central
  `avacursos.com.br`, ou ambos, sempre integrados ao PAINEL INTER.
- **Administração Mundo Inter:** gestão interna de organizações, domínios,
  planos, limites, fornecedores, licenças de catálogos, saúde das integrações e
  auditoria da plataforma.
- **Assinatura eletrônica:** geração e assinatura de contratos e termos com
  documentos imutáveis, autenticação e pacote de evidências.

## Hierarquia organizacional

```text
Mundo Inter
└── Organização (rede ou franqueadora)
    ├── Marca e domínios
    ├── Integrações próprias
    ├── Site e catálogo
    └── Unidades (polos ou franquias)
        ├── Usuários e permissões
        ├── Leads e atendimentos
        ├── Alunos e matrículas
        └── Operação financeira
```

Uma organização poderá representar uma rede inteira. A unidade representa o
polo operacional. Se uma futura contratação exigir isolamento empresarial
adicional, uma franquia poderá ser promovida a organização sem reutilizar IDs ou
misturar seus dados com outra operação.

## Princípios

1. Uma aplicação e uma base de código, com contexto obrigatório de organização.
2. Nenhuma consulta de negócio pode depender apenas do ID recebido na URL.
3. Domínio, identidade visual e integrações são resolvidos pela organização.
4. O site público e o painel compartilham catálogo e captação, mas possuem
   superfícies, sessões e permissões separadas.
5. A organização atual será convertida na primeira organização do Mundo Inter.
6. A evolução será gradual, com migrações reversíveis e sem reescrever todos os
   módulos de uma só vez.

## Endereços de referência

- `mundointer.com.br`: presença institucional do produto.
- `painel.mundointer.com.br`: PAINEL INTER.
- `api.mundointer.com.br`: API e webhooks futuros.
- `admin.mundointer.com.br`: administração central futura.
- Domínios próprios: sites e acessos personalizados de cada organização.

Os endereços são configurações, não identificadores permanentes. A organização
terá ID interno imutável, mesmo quando trocar de domínio.
