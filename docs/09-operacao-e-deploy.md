# Operação e Deploy

## Ambiente atual

- Ubuntu 24.04 LTS em VPS própria.
- Cloudflare na borda.
- HestiaCP gerenciando Nginx como proxy e Apache como aplicação.
- PHP 8.3 via pool PHP-FPM dedicado ao domínio.
- MariaDB 11.4 gerenciado pelo HestiaCP; banco de produção
  `jonathan_painel_inter` acessível somente pelo usuário local correspondente.
- Projeto privado em
  `/home/jonathan/web/interferencia.com.br/private/interferencia-painel`.
- Link público `/home/jonathan/web/interferencia.com.br/public_html/painel`
  apontando exclusivamente para `public/`.

Arquivos gerados pelo HestiaCP não devem ser editados diretamente.
Credenciais do banco existem somente no `.env` de produção, pertencente a
`jonathan:jonathan` com permissão `0600`.

## Primeira preparação

Os comandos do Composer e Git devem ser executados como `jonathan`, nunca como
`root`. Como o projeto ainda não possui bibliotecas externas, basta gerar o
autoload após o clone:

```bash
sudo -u jonathan -H composer \
  --working-dir=/home/jonathan/web/interferencia.com.br/private/interferencia-painel \
  dump-autoload --classmap-authoritative
```

Crie `.env` a partir do exemplo, mantenha-o com permissão `0600` e configure
produção antes de disponibilizar uma versão funcional.

## Atualização

```bash
sudo -u jonathan -H git \
  -C /home/jonathan/web/interferencia.com.br/private/interferencia-painel \
  pull --ff-only

sudo -u jonathan -H composer \
  --working-dir=/home/jonathan/web/interferencia.com.br/private/interferencia-painel \
  dump-autoload --classmap-authoritative

sudo -u jonathan -H composer \
  --working-dir=/home/jonathan/web/interferencia.com.br/private/interferencia-painel \
  check
```

Quando dependências externas forem adicionadas, o projeto deverá versionar
`composer.lock` e o deploy passará a usar `composer install --no-dev
--classmap-authoritative`.

## Verificação

- `/painel/` exibe o estado da fundação.
- `/painel/status` responde pela mesma aplicação.
- Rotas inexistentes retornam `404`.
- Métodos não aceitos em rotas existentes retornam `405` e o cabeçalho `Allow`.
- Respostas incluem `X-Request-ID` para correlação com logs.
- Logs ficam em `storage/logs/` e nunca dentro da raiz pública.
- O cookie `painel_inter_session` deve ser `Secure`, `HttpOnly`, `SameSite=Lax`
  e possuir `Path=/painel`.

## Reversão

Antes de atualizar, registre o commit ativo. Em uma emergência, retorne ao commit
anterior aprovado e regenere o autoload. Não use `git reset --hard` em um
diretório com mudanças não investigadas.

## Sincronização pedagógica diária

Executar `php bin/console moodle:pedagogical:sync` uma vez ao dia como o usuário do domínio. O processo atualiza progresso, nota, último acesso, certificado e alertas do painel Pedagógico. A VPS registra a saída em `storage/logs/pedagogical-sync.log` e impede duas execuções simultâneas.
