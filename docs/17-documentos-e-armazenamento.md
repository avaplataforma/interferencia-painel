# Documentos e armazenamento privado

O módulo de Documentos usa a integração central com o DigitalOcean Spaces. Nenhum arquivo é publicado por URL direta e os downloads passam pela autenticação e pelas permissões do Painel.

## Separação

- ADM Central: `adm-central/Documentos`.
- Franquias: `franquias/{id-codigo}/Documentos`.
- A consulta sempre valida o escopo e a franquia antes de ler o objeto.

## Segurança e histórico

- Tipos permitidos: PDF, imagens, Word, Excel, CSV e texto.
- Limite por arquivo: 25 MB.
- O MIME real é conferido no servidor.
- Cada substituição cria uma nova versão e preserva as anteriores.
- A exclusão operacional é um arquivamento lógico; o objeto externo é mantido para auditoria.
- Respostas usam cache privado, `nosniff` e sandbox para visualização.
