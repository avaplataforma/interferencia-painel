# Documentos e armazenamento

## Estratégia

Arquivos de alunos, organizações e sites não devem permanecer definitivamente
no disco local da aplicação. A plataforma usa o bucket privado `mundointer`,
no DigitalOcean Spaces, por meio da API compatível com S3. Os metadados, a
autorização e os vínculos continuam no MariaDB.

O disco local é apenas cache público para logo e favicon e contingência para
arquivos antigos. Novos anexos de WhatsApp, tickets e personalizações são
enviados ao Spaces quando a integração está ativa.

## Organização dos objetos

Cada objeto recebe chave aleatória, sem dados pessoais legíveis, dentro desta
estrutura:

```text
adm-central/{categoria}/{ano}/{mes}/{uuid}-{arquivo-seguro}
franquias/{id}-{codigo}/{categoria}/{ano}/{mes}/{uuid}-{arquivo-seguro}
```

O ADM Central possui as categorias Personalização, Contratos, Solicitações,
Tickets, Documentos e Backups. Cada franquia possui Personalização, Alunos,
Tickets, Documentos, Contratos, Importações e Backups. A estrutura de uma nova
franquia é preparada automaticamente quando ela é cadastrada.

Metadados mínimos no banco:

- organização e categoria proprietárias;
- nome original e chave segura do objeto;
- tipo MIME detectado, tamanho e hash SHA-256;
- usuário que enviou e data de criação.

## Segurança

- Bucket e objetos privados, sem listagem ou URL pública permanente.
- Download autorizado pela aplicação.
- Criptografia em trânsito e em repouso.
- Limites de tamanho e tipos permitidos por categoria.
- Validação pelo conteúdo real, não apenas pela extensão.
- Registro de metadados e hash de integridade no banco.
- Backup e política de retenção independentes do banco.

Documentos pessoais nunca serão incluídos no repositório Git, logs ou mensagens
de erro. A exclusão lógica e a eliminação física seguirão a política da
organização e as obrigações legais aplicáveis.

## Provedor e credenciais

Endpoint, bucket, Access Key e Secret Key são administrados somente em
`ADM > Integrações > DigitalOcean Spaces`. Os segredos são criptografados com a
chave-mestra da aplicação e nunca voltam a ser exibidos. O código continua
isolado por um gerenciador interno para permitir troca futura do provedor.

O acesso deve usar uma chave dedicada e restrita ao bucket. Se uma credencial
for exposta, ela deve ser revogada e substituída imediatamente.

## Backup

Armazenamento de anexos e backup do banco são responsabilidades diferentes. O
Spaces protege os novos arquivos externos, mas o MariaDB ainda exige rotina
própria de dump criptografado, retenção, verificação de integridade e teste de
restauração. A pasta `Backups` está reservada para essa automação operacional.

## Documentos assinados

Contratos e termos assinados utilizarão o mesmo armazenamento privado, mas serão
imutáveis e versionados. PDF final, anexos e pacote de evidências terão hashes e
retenção próprios. A arquitetura do serviço está documentada em
`21-assinatura-eletronica.md`.
