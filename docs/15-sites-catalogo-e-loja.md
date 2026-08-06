# Sites, catálogo e loja das franquias

## Objetivo

Cada organização poderá publicar um site institucional personalizado para
apresentar sua marca, unidades e cursos. O site será uma vitrine pública ligada
ao mesmo catálogo usado pelo PAINEL INTER e poderá evoluir para loja virtual sem
duplicar cursos, preços, campanhas ou alunos.

## Personalização por organização

- Nome comercial, razão social e contatos.
- Logo, favicon, cores, tipografia e imagens institucionais.
- Domínio próprio e subdomínio temporário do Mundo Inter.
- Cabeçalho, rodapé, menus, redes sociais e canais de atendimento.
- Páginas institucionais e blocos de conteúdo configuráveis.
- Política de privacidade, termos, consentimento e dados do encarregado.
- SEO: título, descrição, imagem social, sitemap e URLs canônicas.

A personalização será baseada em temas e componentes seguros. Não será permitido
injetar PHP ou JavaScript arbitrário pelo painel administrativo.

## Catálogo de cursos

O curso terá um registro canônico no Mundo Inter e poderá ser associado ao curso
correspondente do Moodle de cada organização. A publicação no site acrescentará:

- título comercial, resumo e descrição completa;
- capa, galeria, modalidade, carga horária e requisitos;
- unidades onde está disponível;
- preço base, parcelamento máximo e campanhas válidas;
- situação: rascunho, publicado, oculto ou encerrado;
- slug, SEO e ordem de destaque;
- chamada para inscrição, contato ou compra.

Preço e disponibilidade podem variar por organização e unidade sem alterar o
curso acadêmico sincronizado do AVA.

## Jornada pública

```text
Visitante
  → Site da organização
  → Curso do catálogo
  → Interesse ou compra
  → Lead identificado por organização, unidade, curso e campanha
  → Conversão em aluno
  → Matrícula
  → Cobrança Asaas
  → Confirmação por webhook
  → Liberação no AVA
```

O formulário deve registrar consentimento, origem, UTM, página, curso, unidade e
campanha. A deduplicação seguirá as regras de lead/aluno já definidas, respeitando
o contexto da organização.

## Loja virtual

A primeira versão será um **checkout assistido**: o site envia a intenção ao
PAINEL INTER e usa checkout/link do Asaas. Dados de cartão não passarão nem serão
armazenados nos servidores do Mundo Inter.

Evoluções previstas:

- carrinho com um ou mais produtos compatíveis;
- cupom ou campanha com validade e regras;
- escolha de unidade e modalidade;
- PIX, boleto, cartão e assinatura conforme disponibilidade no Asaas;
- pedido com estados próprios, separado de cobrança e matrícula;
- recuperação de checkout abandonado;
- emissão de comprovante e comunicações transacionais;
- split financeiro conforme contrato da organização/unidade.

Pagamento aprovado não deverá liberar acesso diretamente. O webhook atualiza o
pedido e uma regra idempotente confirma matrícula e liberação no AVA.

## Domínios e publicação

Cada domínio terá propriedade verificada antes da ativação. A plataforma manterá
certificado TLS, host principal, redirecionamentos e estado de verificação. O
domínio nunca será usado isoladamente para autorizar acesso administrativo.

O cache do site público deve variar por organização e ser invalidado quando
catálogo, campanha ou identidade visual forem publicados.

## Administração

O futuro menu **Site** deverá reunir:

- identidade visual;
- domínios;
- páginas e menus;
- catálogo publicado;
- banners e destaques;
- formulários e conversões;
- SEO e integrações analíticas;
- pré-visualização e publicação.

Alterações poderão ficar em rascunho e serão auditadas quando publicadas.

## Primeira entrega recomendada

1. Domínio e tema por organização.
2. Página inicial, página institucional, unidades e contato.
3. Catálogo e detalhe de curso.
4. Formulário de interesse integrado ao CRM.
5. Métricas básicas de origem e conversão.

Carrinho e compra direta entram depois que catálogo, matrícula, cobrança e
webhooks estiverem estáveis no modelo multitenant.
