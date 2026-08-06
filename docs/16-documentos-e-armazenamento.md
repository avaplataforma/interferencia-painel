# Documentos e armazenamento

## Estratégia

Arquivos de alunos, organizações e sites não devem permanecer definitivamente
no disco local da aplicação. A plataforma adotará armazenamento compatível com
S3, mantendo metadados e autorização no MariaDB.

Para a fase inicial, o disco local poderá ser usado por um adaptador temporário.
A interface de armazenamento será a mesma para permitir migração posterior para
Object Storage sem mudar os módulos.

## Organização dos objetos

Cada objeto terá chave sem dados pessoais legíveis:

```text
organizations/{organization_uuid}/{category}/{record_uuid}/{file_uuid}
```

Metadados mínimos no banco:

- organização, unidade e registro proprietário;
- nome original e nome seguro;
- tipo MIME detectado, tamanho e hash;
- provedor e chave do objeto;
- usuário que enviou e datas;
- classificação, situação e prazo de retenção;
- resultado da análise antimalware.

## Segurança

- Buckets privados, sem listagem ou URL pública permanente.
- Download autorizado pela aplicação e URL assinada de curta duração.
- Criptografia em trânsito e em repouso.
- Limites de tamanho e tipos permitidos por categoria.
- Validação pelo conteúdo real, não apenas pela extensão.
- Varredura antimalware antes de disponibilizar o arquivo.
- Logs de envio, consulta, substituição e exclusão.
- Backup e política de retenção independentes do banco.

Documentos pessoais nunca serão incluídos no repositório Git, logs ou mensagens
de erro. A exclusão lógica e a eliminação física seguirão a política da
organização e as obrigações legais aplicáveis.

## Provedores

A decisão do provedor será feita por custo, região, compatibilidade S3,
versionamento, lifecycle, criptografia e recuperação. Opções possíveis incluem
OVH Object Storage, DigitalOcean Spaces e Google Cloud Storage. O código dependerá
de um contrato interno, e não de uma API específica espalhada pelos módulos.
