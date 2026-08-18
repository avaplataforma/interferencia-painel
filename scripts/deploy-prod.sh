#!/usr/bin/env bash
# Deploy da produção (VPS Mundo Inter) a partir do ambiente dev.
# Uso: bash scripts/deploy-prod.sh
set -euo pipefail

echo "== 1/4 Atualizando workspace =="
git -C /home/deploy/dev/mundo-inter pull origin main --ff-only

echo "== 2/4 Atualizando produção =="
git -C /var/www/painel-inter pull origin main --ff-only

echo "== 3/4 Migrações (se houver) =="
php /var/www/painel-inter/bin/console migrate || true

echo "== 4/4 Smoke =="
bash /var/www/painel-inter/scripts/smoke.sh