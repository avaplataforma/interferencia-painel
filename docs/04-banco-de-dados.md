# Banco de Dados

## Diretrizes

O MariaDB será a fonte transacional principal. O esquema real será criado por
migrações somente quando os domínios forem especificados; esta fundação não cria
tabelas prematuramente.

- Engine InnoDB.
- Charset `utf8mb4` e collation compatível com os requisitos de busca.
- Chaves estrangeiras explícitas.
- Índices derivados de consultas reais, não por suposição.
- Datas técnicas em UTC; exibição em `America/Sao_Paulo`.
- Valores monetários em `DECIMAL`, nunca ponto flutuante.
- Migrações imutáveis após aplicadas em ambiente compartilhado.

## Domínios conceituais futuros

Sem definir tabelas nesta fase, o modelo deverá considerar:

- identidade: usuários, papéis, permissões e sessões;
- organização: unidades, vínculos e números/canais;
- CRM: contatos, oportunidades, etapas, atividades e histórico;
- WhatsApp: conversas, mensagens, participantes, templates e eventos;
- governança: auditoria, consentimentos e configurações.

## Unidades e isolamento

A Sede/Central em Tijucas e a Filial Tijucas são unidades distintas. Registros
com escopo organizacional deverão referenciar inequivocamente a unidade. A
autorização por unidade será aplicada no caso de uso e validada no acesso aos
dados, sem confiar apenas em filtros da interface.

## WhatsApp e idempotência

Eventos externos possuem identificadores próprios e podem ser reenviados. O
modelo futuro deverá impor unicidade adequada, registrar o estado de
processamento e permitir repetição segura. Payloads brutos só serão retidos se
necessários, por prazo definido e com dados sensíveis protegidos.

## Privacidade e ciclo de vida

Antes do CRM e WhatsApp, devem ser definidas base legal, retenção, anonimização,
exportação e exclusão. Logs e backups também fazem parte do ciclo de vida e não
podem conservar dados indefinidamente por acidente.

## Operação

- Usuários de banco separados por ambiente e com privilégio mínimo.
- Backup automático criptografado, com retenção e teste de restauração.
- Migração executada de forma controlada antes da troca de versão.
- Consultas lentas e capacidade acompanhadas por métricas.
- Nenhuma credencial ou cópia de produção no Git.

