# API

## Papel

A API servirá à interface interna e a integrações autorizadas. Seus contratos
serão versionados quando houver consumidores reais. Não existem endpoints nesta
fase.

## Convenções propostas

- Base interna sob `/painel/api`.
- JSON UTF-8 e cabeçalho `Content-Type: application/json`.
- Códigos HTTP coerentes e formato uniforme de erros.
- Paginação, filtros e ordenação com limites explícitos.
- Identificador de correlação por requisição.
- Datas em ISO 8601 com fuso explícito.
- Compatibilidade preservada dentro de uma versão publicada.

Exemplo conceitual de erro, ainda não contratual:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Verifique os dados informados.",
    "fields": {}
  },
  "request_id": "..."
}
```

## Segurança

- Autenticação adequada ao tipo de consumidor.
- Autorização em todo recurso e ação, incluindo escopo de unidade.
- Rate limiting e limites de tamanho.
- CORS negado por padrão e liberado apenas para origens necessárias.
- Segredos nunca em URL, log ou resposta.
- Auditoria de mutações sensíveis.

## Webhooks

Webhooks do WhatsApp terão entrada dedicada. A assinatura será validada usando o
corpo original antes do parse; o desafio de verificação será tratado de forma
restrita. Eventos aceitos devem ser persistidos/idempotentes e processados fora
do tempo crítico da requisição quando a infraestrutura de filas existir.

## Evolução

Uma especificação OpenAPI deverá acompanhar a primeira API consumível. Mudanças
incompatíveis exigirão versão e plano de transição.

