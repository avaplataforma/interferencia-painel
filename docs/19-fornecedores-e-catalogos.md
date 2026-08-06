# Fornecedores e catálogos de cursos

## Objetivo

O Mundo Inter poderá integrar vários fornecedores de cursos comprados para
revenda. Cada fornecedor terá catálogo, contrato, forma de sincronização e regra
de entrega próprios. A plataforma decidirá quais cursos poderão ser usados por
cada organização e quais aparecerão no site de cada franquia.

Fornecedor, curso acadêmico, produto comercial e publicação no site serão
conceitos separados. Um registro importado nunca será publicado nem vendido
automaticamente.

## Camadas do catálogo

```text
Fornecedor
  → Catálogo de origem
  → Curso importado
  → Curso canônico do Mundo Inter
  → Oferta comercial da organização
  → Publicação na vitrine do site
  → Matrícula comercial
  → Entrega em um ou mais AVAs
```

- **Fornecedor:** empresa proprietária ou distribuidora do conteúdo.
- **Catálogo de origem:** conjunto recebido por API, arquivo ou integração.
- **Curso importado:** espelho técnico dos dados fornecidos, preservado para
  auditoria e futuras sincronizações.
- **Curso canônico:** identidade interna e estável do curso no Mundo Inter.
- **Oferta comercial:** curso autorizado para uma organização, com preço,
  campanha, parcelamento, unidades, validade e regras de venda.
- **Publicação:** apresentação da oferta no site, com texto, imagens, SEO, ordem
  e destaque personalizados.
- **Entrega acadêmica:** curso remoto efetivamente liberado no AVA definido.

## Integrações dos fornecedores

Cada conector deverá registrar:

- nome do fornecedor e situação contratual;
- ambiente, endpoint, autenticação criptografada e limites da API;
- método de importação: API, webhook, arquivo ou processo manual controlado;
- cursor, data da última sincronização, duração, totais e erros;
- identificadores remotos, versão e conteúdo original recebido;
- regras de atualização, descontinuação e indisponibilidade;
- custo, validade da licença, limite de alunos e demais condições contratuais;
- forma de entrega: AVA Central, AVA do fornecedor, SCORM, arquivo, LTI ou outro
  mecanismo homologado.

As sincronizações serão idempotentes. A indisponibilidade de um fornecedor não
interromperá os demais catálogos.

## Curadoria e deduplicação

Cursos parecidos de fornecedores diferentes não serão unidos automaticamente.
O Admin Mundo Inter poderá:

- aprovar ou rejeitar um curso importado;
- vincular o item a um curso canônico existente;
- criar um novo curso canônico;
- revisar título, modalidade, carga horária, área e dados obrigatórios;
- escolher capa e descrição comercial próprias, respeitando o contrato;
- marcar substituição, equivalência ou encerramento;
- manter a origem e o histórico de alterações sempre rastreáveis.

Um curso canônico poderá possuir várias possibilidades de entrega. Assim, uma
mesma oferta poderá trocar de fornecedor no futuro sem perder matrículas,
histórico comercial ou URLs públicas, desde que exista uma migração aprovada.

## Distribuição para organizações

O Admin Mundo Inter controla uma **licença de catálogo** por organização. Ela
define:

- fornecedores e cursos autorizados;
- período de disponibilidade;
- unidades ou regiões permitidas;
- preço mínimo, preço sugerido e margem mínima;
- limite de matrículas ou créditos, quando aplicável;
- possibilidade de desconto e campanhas;
- AVA e curso remoto usados na entrega;
- permissão para publicar, vender ou apenas matricular internamente.

A franquia não poderá acessar cursos, custos ou contratos de fornecedores que
não estejam incluídos em sua licença.

## Oferta e vitrine do site

A organização escolhe, entre os cursos licenciados, quais ofertas deseja
publicar. Para cada oferta poderá configurar:

- nome e texto comercial dentro dos limites permitidos;
- imagens homologadas, categoria, público, modalidade e duração;
- preço final, parcelamento e campanha válidos;
- unidades atendidas e disponibilidade territorial;
- ordem, destaque, SEO e chamada para ação;
- captação de lead, checkout ou atendimento assistido;
- data de publicação e retirada.

O site consulta somente ofertas publicadas da organização resolvida pelo
domínio. Alterações passam por rascunho e publicação; sincronizar um fornecedor
não deve alterar silenciosamente uma vitrine já publicada.

## Preço, custo e repasse

O sistema deve guardar separadamente:

- custo de aquisição/licença do fornecedor;
- preço de referência do Mundo Inter;
- preço mínimo permitido;
- preço de venda da organização;
- desconto aplicado;
- comissão, royalty ou split;
- impostos e taxas quando definidos;
- margem prevista e margem realizada.

Valores contratados serão congelados na matrícula ou pedido. Mudanças futuras
no catálogo não modificarão contratos já realizados.

## Matrícula e entrega

Ao vender uma oferta, o sistema registra uma fotografia das regras comerciais e
acadêmicas utilizadas. A matrícula referencia a organização vendedora, unidade,
oferta, curso canônico, fornecedor e entregas acadêmicas.

Uma oferta poderá gerar:

- um curso no AVA Central;
- um curso no AVA dedicado da organização;
- cursos complementares em mais de um AVA;
- no futuro, uma entrega externa controlada pelo próprio fornecedor.

Falhas, cancelamentos e conclusão serão controlados por entrega, sem perder a
origem comercial da matrícula.

## Papéis administrativos

- **Admin Mundo Inter:** fornecedores, conectores, contratos, custos, catálogo
  canônico, licenças e regras globais.
- **Admin da organização:** ofertas licenciadas, preço permitido, publicação,
  unidades, campanhas e conteúdo comercial autorizado.
- **Unidade/atendente:** consulta e comercialização apenas das ofertas liberadas
  para seu contexto.

## Entidades previstas

- `course_providers`
- `provider_connections`
- `provider_catalogs`
- `provider_courses`
- `canonical_courses`
- `course_sources`
- `organization_catalog_licenses`
- `organization_course_offers`
- `site_course_publications`
- `offer_delivery_rules`

Os nomes são conceituais e serão confirmados durante a implementação.

## Primeira implantação

1. Cadastrar manualmente dois fornecedores fictícios.
2. Importar seus cursos para uma área de revisão, sem publicação automática.
3. Criar o catálogo canônico e mapear as entregas no AVA Central.
4. Licenciar cursos distintos para duas organizações de teste.
5. Publicar vitrines diferentes usando a mesma base de código.
6. Testar preço, campanha, matrícula, cobrança e entrega acadêmica.
7. Somente depois conectar o primeiro fornecedor real.
