# Banco de dados

As alterações de esquema serão feitas exclusivamente por migrações versionadas.
Seeds devem conter somente dados seguros para desenvolvimento ou referências
controladas. Veja `docs/04-banco-de-dados.md`.

O diretório `migrations/` está intencionalmente sem migrações de domínio nesta
fase. A tabela técnica `schema_migrations` é criada pelo próprio executor.
