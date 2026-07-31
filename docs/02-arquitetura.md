# Arquitetura

## Estilo

A aplicação será um monólito modular em PHP 8.3. O desenho mantém implantação
simples na VPS e separa domínios para que cresçam sem acoplamento desnecessário.

```text
Navegador / Meta Webhooks
          |
          v
public/ e api/       Entradas HTTP
          |
          v
kernel/              Serviços técnicos compartilhados
          |
          v
modules/             Regras e casos de uso por domínio
          |
          v
MariaDB / storage/   Persistência controlada
```

## Responsabilidades

- `public/`: única raiz exposta; arquivos estáticos e controlador frontal.
- `api/`: contratos e pontos de entrada da API, incluindo futuros webhooks.
- `kernel/`: infraestrutura transversal, sem regras específicas de negócio.
- `modules/`: domínios independentes, com casos de uso e persistência próprios.
- `config/`: configuração versionável; valores sensíveis vêm do ambiente.
- `database/`: migrações e seeds versionados.
- `storage/`: cache, sessões e logs gerados em execução.
- `tests/`: testes unitários, integração, contrato e segurança.

## Fluxo HTTP planejado

1. O servidor recebe uma requisição sob `/painel`.
2. O controlador frontal inicializa configuração, erros, sessão e dependências.
3. O roteador encontra a rota e aplica autenticação, autorização e proteção CSRF.
4. Um caso de uso do módulo executa a regra de negócio.
5. A resposta é renderizada como HTML ou serializada como JSON.
6. Eventos relevantes são registrados de modo estruturado e auditável.

## Camada HTTP implementada

- `Request` normaliza método, caminho, query, cabeçalhos e corpo.
- `Router` remove o prefixo configurado, aceita parâmetros com restrições e
  distingue rotas ausentes (`404`) de métodos não permitidos (`405`).
- Rotas `GET` respondem também a `HEAD` conforme a semântica HTTP.
- `Response` centraliza status, cabeçalhos e corpo.
- `View` renderiza templates PHP com escape HTML explícito e layout comum.
- As rotas da interface ficam declaradas em `routes/web.php`.

## Estado e proteção de formulários

- Sessões usam apenas cookies, modo estrito, cookie `Secure` e `HttpOnly`,
  `SameSite=Lax` e caminho limitado a `/painel`.
- O identificador deve ser regenerado após autenticação e invalidado no logout.
- Mensagens flash sobrevivem exatamente à requisição seguinte.
- Tokens CSRF possuem 256 bits, ficam na sessão, usam comparação resistente a
  timing e são aplicados pelo roteador, por padrão, em métodos que alteram estado.
- O validador trabalha com campos declarados e não repassa entradas adicionais.

## Limites do kernel

O kernel poderá fornecer configuração, contêiner, roteamento, HTTP, sessão,
autenticação, autorização, banco, migrações, validação, logs e templates. Ele não
deve conhecer conceitos como lead, conversa, campanha ou unidade comercial.

## Convenções propostas

- Namespace raiz `Interferencia` e autoload PSR-4.
- `strict_types=1` em código PHP.
- Horário de negócio `America/Sao_Paulo`; datas persistidas preferencialmente em
  UTC e convertidas na borda.
- UTF-8 (`utf8mb4`) em toda a cadeia.
- IDs internos não devem ser usados como autorização implícita.
- URLs, assets e redirecionamentos devem respeitar o prefixo `/painel`.

## Segurança de implantação

- TLS obrigatório e cookies `Secure`, `HttpOnly` e `SameSite` apropriado.
- Privilégio mínimo no usuário do MariaDB e no sistema operacional.
- Validação de entrada e saída escapada por contexto.
- Consultas parametrizadas; CSRF em mutações via navegador.
- Rate limiting em login, API e webhooks.
- Assinatura de webhook validada antes de processar conteúdo.
- Backups criptografados e restauração testada periodicamente.
- Produção sem debug e sem exibição pública de exceções.

## Decisões pendentes

- Ambiente confirmado: Cloudflare, Nginx, Apache e PHP-FPM gerenciados pelo
  HestiaCP; detalhes em `09-operacao-e-deploy.md`.
- Biblioteca de testes e análise estática.
- Política de filas e execução assíncrona.
- Estratégia de deploy, rollback, backup e monitoramento.
- Modelo final de autorização e isolamento entre unidades.
