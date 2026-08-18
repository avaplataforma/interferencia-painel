# Notas do Projeto — Mundo Inter

Guia operacional do painel **Interferência/Mundo Inter** (PHP puro, VPS Ubuntu 24.04) e do plugin Moodle `local_mundointer`.

## Acesso e ambientes

| Item | Valor |
|---|---|
| VPS | Ubuntu 24.04, IP `15.204.121.10`, SSH `deploy@` (alias `mundointer`) |
| Produção painel | `/var/www/painel-inter` (user `deploy`, FPM `www-data`) |
| Domínios | `mundointer.com.br` (painel/site), `painel.mundointer.com.br`, `avacursos.com.br` (AVA/Moodle) |
| AVA | Moodle 4.3.8+ (tema **Trema**), plugin `local_mundointer` instalado por ZIP |
| Code Server (dev) | `dev.mundointer.com.br` → `127.0.0.1:8080`, senha em `/home/deploy/.code-server-senha.txt`, workspace `/home/deploy/dev/mundo-inter` |

## Fluxo de deploy

```
git add ... && git commit -m "..." && git push origin main
ssh mundointer "cd /var/www/painel-inter && git pull origin main"
sudo -u deploy php bin/console migrate        # se houver migração
bash scripts/smoke.sh                          # deve terminar em SMOKE OK
```

## Testes

```powershell
# local (Windows): C:\tools\php83\php.exe + extensões
& "C:\tools\php83\php.exe" -d extension_dir="C:\tools\php83\ext" -d extension=php_mbstring.dll -d extension=php_openssl.dll -d extension=php_fileinfo.dll -d extension=php_curl.dll -d extension=php_sodium.dll -d extension=php_zip.dll tests\run.php
```

Critério de commit: **121 teste(s), 0 falha(s)**.

## Plugin Moodle (local_mundointer)

- Fonte: `integrations/moodle/local_mundointer/`
- **Versão**: `version.php` (`$plugin->version` = `AAAAMMDDXX`, ex.: `2026081863`) + `$plugin->release` (ex.: `1.0.63`)
- **Upgrade**: adicionar bloco `if ($oldversion < XXXX) { ...; upgrade_plugin_savepoint(...); }` em `db/upgrade.php`
- **Build do ZIP**: no painel → ADM → Integrações → painel-inter → "Baixar ZIP oficial" (gerado na hora; estrutura raiz `mundointer/`)
- **Instalação no AVA**: Administração do site → Plug-ins → Instalar plug-ins → upload do ZIP → concluir upgrade como admin → limpar caches
- **Arquivos de raiz do Moodle** (fora do plugin): `integrations/moodle/root/` (`franquia.php`, etc.) — copiar manualmente quando mudarem

## Armadilhas conhecidas

1. **PowerShell/SSH**: nunca usar `$`/`$(...)` inline em `ssh "..."`; usar scripts `.sh`/`.php` via scp e **remover o BOM UTF-8** antes do scp
2. **JS dentro do lib.php**: o script do hook fica dentro de **string PHP com aspas simples** — strings JS só com **aspas duplas** (`\"`); aspas simples quebram o parse
3. **`$oldversion` em patch scripts PHP**: escapar como `\$oldversion` (não usar replace via PowerShell, que corrompe com backtick)
4. **Hook do body**: precisa de `global $USER, $PAGE, $DB;` — sem `global $PAGE` a detecção de página falha silenciosamente
5. **Marca da sessão**: `brand_resolver::current()` resolve por campos de perfil → sessão/cookie `MundoInterBrand` → `defaultbrand`. SSO carrega o slug da sessão (painel `createSsoSession(username, courseId, slug)`)
6. **`.env`**: após qualquer `sed -i`, reaplicar `chown deploy:www-data` + `chmod 640`
7. **FPM**: `pm.max_children=20` (`/etc/php/8.3/fpm/pool.d/`)
8. **CSRF**: Router valida POST por padrão; rotas externas (site, portal do aluno) usam `$router->postWithoutCsrf(...)`
9. **DigitalOcean Spaces**: documentos em `franquias/{ID}-{code}/Alunos/{CPF}/Documentos/...`; `mundointer.com.br/assets/organizations/{orgId}/` para marcas (logo/favicon/navbar)
10. **Trema/Moodle core**: foco de links `a:not([class])`/`a.aalink` usa fundo vermelho `#ff0a0a` — plugin neutraliza com `!important` no CSS "neutro" do head hook
11. **Sincronização pedagógica**: cron `*/5 * * * *` → `php bin/console moodle:pedagogical:sync` (progresso/notas/certificados no painel)
12. **Catálogo**: `catalog:release-ready` (liberação), loops AI (`catalog-ai:complete`, `catalog-images:process`), `sitemap:ping`; release loop a cada 15 min

## Portal do Aluno (AVA → plugin)

- Página: `/local/mundointer/portal.php` (painel de abas, KPIs, jornada, financeiro/PIX, tickets com anexo, documentos, materiais, certificados, satisfação por curso)
- API: `/portal/aluno` (HMAC `hash_hmac('sha256','student-portal|'.$cpf, encryption_key)`) + `/portal/aluno/ticket|document|satisfaction` + download de material
- Config por franquia: ADM da franquia → **PORTAL DO ALUNO** (Comunicados com expiração, Abas e seções, Materiais, Satisfação)
- Progresso da jornada: calculado ao vivo no Moodle (`course_modules_completion`) com fallback do painel

## Comandos úteis (bin/console)

```
migrate | migrate:status | db:check
moodle:pedagogical:sync | moodle:profiles:rebuild
catalog:release-ready | catalog-ai:complete | catalog-images:process | catalog:duplicates | catalog:covers:audit
ava-courses:process | ava-courses:link-catalog | site:recoveries:sync | sitemap:ping | whatsapp:media:sync
```

## Code Server (ambiente de dev na VPS)

- Serviço: `systemctl status code-server` (user `deploy`, porta local 8080)
- Workspace: `/home/deploy/dev/mundo-inter` (clone do repo; **nunca editar /var/www/painel-inter diretamente**)
- Fluxo no code-server: editar → commit → push → deploy na produção
- **Deploy dentro do Code Server: NÃO usar SSH** (a produção fica na mesma VPS). Rodar `bash scripts/deploy-prod.sh` (pull workspace + produção, migrate e smoke)
- Reconfigurar senha: `/home/deploy/.config/code-server/config.yaml`
