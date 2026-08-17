#!/bin/bash
# Smoke test pós-deploy: valida as páginas públicas essenciais.
# Uso: bash scripts/smoke.sh [base-url]
set -u

BASE="${1:-https://mundointer.com.br/interferencia}"
FAIL=0

check() {
  local url="$1"
  local needle="$2"
  local code body
  body=$(curl -sk --max-time 25 "$url" 2>/dev/null)
  code=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 25 "$url" 2>/dev/null)
  if [ "$code" != "200" ]; then
    echo "FALHA: $url -> HTTP $code"
    FAIL=1
    return
  fi
  if [ -n "$needle" ] && ! printf '%s' "$body" | grep -q "$needle"; then
    echo "FALHA: $url -> marcador '$needle' ausente"
    FAIL=1
    return
  fi
  echo "ok: $url"
}

check "$BASE/site" "data-course-grid"
check "$BASE/site/sitemap.xml" "<urlset"
check "$BASE/site/robots.txt" "Sitemap"
check "$BASE/site/catalogo-pro/31" "Sobre este Curso"
check "$BASE/login" "login"

if [ "$FAIL" = "1" ]; then
  echo "SMOKE FALHOU"
  exit 1
fi
echo "SMOKE OK"
