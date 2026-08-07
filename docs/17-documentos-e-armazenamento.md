# Documentos e armazenamento privado

O módulo de Documentos usa a integração central com o DigitalOcean Spaces. Nenhum arquivo é publicado por URL direta e os downloads passam pela autenticação e pelas permissões do Painel.

## Separação

- Franquias: `franquias/{id-codigo}/Documentos`.

No cadastro de cada franquia há uma aba documental própria. Ela destaca os tipos
obrigatórios definidos pela administração e aceita os demais anexos ativos. Esses
arquivos nunca são misturados entre franquias.
- Os arquivos são administrados somente no cadastro de cada franquia. Não existe
  uma biblioteca global de arquivos no ADM Central.
- O menu `ADM > Documentos` administra apenas a lista de tipos documentais, sua
  ordem, obrigatoriedade e disponibilidade para novos anexos.
- Upload, nova versão, visualização, download e arquivamento ficam reunidos nessa
  mesma área do cadastro.
- A consulta sempre valida o escopo e a franquia antes de ler o objeto.

## Segurança e histórico

- Tipos permitidos: PDF, imagens, Word, Excel, CSV e texto.
- Limite por arquivo: 25 MB.
- O MIME real é conferido no servidor.
- Cada substituição cria uma nova versão e preserva as anteriores.
- A exclusão operacional é um arquivamento lógico; o objeto externo é mantido para auditoria.
- Respostas usam cache privado, `nosniff` e sandbox para visualização.
