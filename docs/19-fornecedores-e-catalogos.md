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

## Escola Avançada e Catálogo PRO

A Escola Avançada é o primeiro fornecedor real preparado nessa arquitetura. A
documentação pública da API V2 disponibiliza, neste momento, a listagem de
cursos e aulas. Ela também possui recursos financeiros, mas eles **não são
utilizados pelo Mundo Inter**.

Na primeira etapa:

- o token é armazenado criptografado no ADM Central;
- `cursos/listar` alimenta um espelho técnico chamado **Catálogo PRO**;
- categoria, capa, carga horária, aulas e valores remotos são preservados para
  revisão;
- valores remotos são somente referência e nunca geram preço ou cobrança;
- sincronizar não publica cursos, não vende e não cria matrículas;
- a indisponibilidade na origem não apaga o histórico importado;
- a loja separará as futuras ofertas pelo catálogo de origem.

A documentação consultada não apresenta uma operação oficial de matrícula,
SSO, deep link assinado, LTI, SCORM ou incorporação de conteúdo. Por isso, a
entrega padrão permanece como abertura segura no AVA do fornecedor. Iframe é
uma opção experimental, pois pode ser bloqueado por `X-Frame-Options`, CSP,
cookies de terceiros ou políticas do navegador.

Antes de liberar vendas reais do Catálogo PRO, será necessário homologar com o
fornecedor um identificador estável de curso e a forma oficial de criar o acesso
do aluno. A solução preferencial é SSO ou deep link assinado; LTI é a segunda
opção. Iframe simples não deve transportar credenciais nem substituir uma
integração acadêmica oficial.

### Curadoria e publicação assistida

O ADM Central controla duas etapas separadas após cada sincronização:

1. **Curadoria:** aprovar ou rejeitar o curso, definir nome e descrição
   comerciais e registrar observações internas.
2. **Liberação por franquia:** escolher a franquia, preço próprio, quantidade
   máxima de parcelas, visibilidade e situação da oferta.

Uma oferta aprovada aparece na loja em uma seção própria, identificada como
**Catálogo PRO** e separada dos cursos do AVA Cursos. O botão público cria ou
atualiza um Lead e registra o interesse, mas não emite cobrança nem cria
matrícula automaticamente. Até a homologação da API acadêmica do fornecedor, a
equipe confirma manualmente pagamento, matrícula e acesso ao AVA parceiro.

## Registro oficial de catálogos

O cadastro central passa a reconhecer oito linhas acadêmicas independentes:

| Catálogo | Fornecedor | Ambiente acadêmico |
| --- | --- | --- |
| INTER | AVA Cursos | `https://avacursos.com.br/{franquia}` |
| PRO | Escola Avançada | `https://interferenciaead.com.br/metodo/login.php` |
| UP | SIE | `https://www.sie.com.br/interferenciaead` |
| MASTER | IESDE | `https://eadservidor.com.br/avacursos/interferencia/` |
| CEFE | EJA CEFE | `https://avacefe.com.br/login/` |
| CONCLUSÃO | EJA Conclusão | `https://avaconclusao.com.br/login/` |
| PREPARA | Aprova Concursos | `https://aprovaconcursos.com.br/?ref=interf2026` |
| DRIVE | Trânsito | `https://ava.eadcursosdetransito.com.br/login` |

## IESDE e Catálogo MASTER por LTI 1.3

O Catálogo MASTER utiliza o **AVA Cursos como plataforma LTI 1.3** e o IESDE
como ferramenta externa. A integração antiga do Portal AVA permanece somente
como registro técnico e contingência desativada; suas credenciais não são
apagadas, mas não participam do fluxo acadêmico novo.

O cadastro é concluído em duas pontas:

1. No IESDE, registrar `Mundo Inter — Catálogo MASTER` informando
   `https://avacursos.com.br` como URL do LMS/Issuer.
2. Receber do fornecedor o registro dinâmico ou os endereços manuais da
   ferramenta: Target Link URI, início de login OIDC, JWKS e redirecionamentos.
3. No Moodle, cadastrar a ferramenta externa em **Administração do site →
   Plugins → Módulos de atividade → Ferramenta externa → Gerenciar
   ferramentas**.
4. Informar ao IESDE os endereços da plataforma Moodle, o Client ID e o
   Deployment ID gerados.
5. Homologar o lançamento com um usuário aluno antes de ativar o catálogo.

O LTI cuida do lançamento autenticado do conteúdo e evita uma segunda senha.
Ele não substitui preço, cobrança, matrícula comercial ou liberação por
franquia, que continuam no Mundo Inter. A composição do catálogo para a loja
será feita separadamente por **Deep Linking** quando oferecido pelo IESDE ou por
importação/curadoria controlada no ADM Central.

Na aba **AVA** de cada franquia, o ADM Central controla a licença geral de cada
catálogo. Essa licença não substitui a curadoria nem a oferta individual do
curso. Catálogo, API e curso precisam estar liberados para que uma nova venda
possa seguir para matrícula automática.

O Catálogo INTER permanece liberado para as franquias atuais. Novos catálogos
externos começam bloqueados e com a API pendente, preservando o princípio de
negação por padrão. A integração de cada fornecedor será ativada somente depois
de cadastradas as credenciais, testada a consulta de cursos e homologada a
operação de matrícula/liberação de acesso.

### Catálogo MASTER — Portal AVA / IESDE

O conector MASTER usa o WebService paginado do Portal AVA. A URL-base oficial é
separada do endereço que o aluno abre no navegador. A autenticação combina:

- usuário HTTP Digest;
- senha HTTP Digest;
- cabeçalho `EAD-API-KEY`.

As três credenciais são criptografadas pelo Painel e nunca aparecem novamente
na interface. A consulta de cursos usa
`web_servicePg/getCursos/format/json`, com paginação e intervalo de datas. O
conector também deixa preparados os serviços oficiais de criação de matrícula,
mudança de situação e consulta de matrículas, sem importar o financeiro do
fornecedor e sem desabilitar a validação TLS.
