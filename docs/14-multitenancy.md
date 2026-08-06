# Multitenancy e isolamento

## Modelo inicial

O Mundo Inter adotará **banco compartilhado com isolamento lógico por
organização**. Tabelas de negócio receberão `organization_id`, além do `unit_id`
quando o registro também pertencer a uma unidade.

O modelo reduz custo e complexidade operacional nesta fase, mas deverá permitir
que uma organização seja movida futuramente para banco dedicado sem alterar os
contratos dos módulos.

## Resolução do contexto

O contexto será determinado nesta ordem:

1. Host validado da requisição.
2. Organização vinculada ao host e em situação ativa.
3. Usuário autenticado e vínculo com a organização.
4. Unidade ativa entre as unidades autorizadas.

Domínios desconhecidos não poderão abrir uma organização padrão. A requisição
será recusada ou direcionada a uma página neutra do Mundo Inter.

## Regras obrigatórias

- Toda tabela pertencente ao cliente deve possuir `organization_id` não nulo.
- Chaves únicas devem incluir a organização quando o valor puder se repetir em
  clientes diferentes, por exemplo `(organization_id, cpf_normalized)`.
- Repositórios recebem o contexto organizacional pelo servidor; nunca aceitam
  livremente um `organization_id` enviado pelo navegador.
- Relacionamentos devem impedir vínculos cruzados entre organizações.
- Cache, filas, arquivos, logs de auditoria e idempotência de webhooks devem
  incluir o identificador da organização.
- O Admin System da plataforma terá acesso excepcional, explícito e auditado.
- Exportação e exclusão devem operar dentro de uma única organização.

## Identidade e acesso

Um usuário poderá participar de uma ou mais organizações, mas cada sessão terá
uma organização ativa. Papéis e unidades autorizadas pertencem ao vínculo do
usuário com a organização, e não ao usuário global.

Perfis previstos:

- **Admin Mundo Inter:** administração da plataforma.
- **Admin da organização:** identidade, integrações e estrutura do cliente.
- **Sede:** operação completa sem configurações estruturais da plataforma.
- **Gestor e operacional:** permissões por módulo e unidade.

## Integrações

Credenciais dos gateways de pagamento, WhatsApp, Moodle, e-mail e armazenamento
serão guardadas por organização, criptografadas com chave-mestra externa ao
banco. Webhooks terão rota e segredo capazes de identificar a organização e a
conexão externa antes do processamento.

O Asaas será a primeira conexão financeira, mas não será embutido como regra de
domínio. Cada organização poderá possuir conexão principal e alternativas em
provedores homologados, com capacidades e roteamento próprios.

O AVA Central é uma integração de plataforma compartilhada e sua credencial não
será duplicada entre organizações. A autorização de uso, o catálogo licenciado e
as entregas acadêmicas serão vinculados individualmente a cada organização. AVAs
dedicados continuarão com credenciais próprias da organização.

## Evolução técnica

1. Criar `organizations`, `organization_domains` e vínculos de usuários.
2. Registrar a operação atual como organização inicial.
3. Adicionar `organization_id` às tabelas centrais sem mudar a interface.
4. Introduzir um `OrganizationContext` obrigatório nos repositórios.
5. Cobrir tentativas de acesso cruzado com testes automatizados.
6. Somente então permitir a segunda organização em produção.

Nenhuma organização externa será ativada antes de uma revisão específica de
isolamento, backup, auditoria e recuperação.
