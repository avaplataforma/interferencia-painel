<?php
$primaryCandidate = (string) ($site['site_primary_color'] ?? '');
$primary = preg_match('/^#[0-9a-fA-F]{6}$/', $primaryCandidate) === 1 ? $primaryCandidate : (preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($site['primary_color'] ?? '')) === 1 ? (string) $site['primary_color'] : '#ed1c24');
$secondaryCandidate = (string) ($site['site_secondary_color'] ?? '');
$secondary = preg_match('/^#[0-9a-fA-F]{6}$/', $secondaryCandidate) === 1 ? $secondaryCandidate : (preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($site['secondary_color'] ?? '')) === 1 ? (string) $site['secondary_color'] : '#102a56');
$publicBase = rtrim((string) $basePath, '/') . '/site';
$messages = [
 'success' => ['Pedido recebido', 'O checkout foi concluído. A confirmação financeira será atualizada automaticamente e nossa equipe dará sequência à matrícula.', '✓'],
 'cancel' => ['Pagamento não concluído', 'Você cancelou o checkout. Nenhuma nova tentativa será feita automaticamente.', '←'],
 'expired' => ['Checkout expirado', 'O prazo deste checkout terminou. Volte à vitrine para iniciar uma nova solicitação.', '!'],
];
$result = $messages[$status] ?? $messages['cancel'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $escape($result[0]) ?></title><style>:root{--primary:<?= $escape($primary) ?>;--secondary:<?= $escape($secondary) ?>}*{box-sizing:border-box}body{display:grid;place-items:center;min-height:100vh;margin:0;padding:1rem;color:#14202d;background:#f3f6f8;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}.card{width:min(34rem,100%);padding:2.5rem;border:1px solid #dee5e9;border-radius:1.1rem;background:#fff;text-align:center;box-shadow:0 1rem 3rem rgb(29 48 67 / 10%)}.icon{display:grid;place-items:center;width:4rem;height:4rem;margin:0 auto 1.2rem;border-radius:50%;color:#fff;background:var(--primary);font-size:1.8rem;font-weight:900}.card h1{margin:0;font-size:2rem}.card p{margin:1rem 0 1.5rem;color:#607181;line-height:1.65}.button{display:inline-flex;align-items:center;justify-content:center;min-height:3rem;padding:.75rem 1.1rem;border-radius:.7rem;color:#fff;background:var(--secondary);text-decoration:none;font-weight:850}</style></head><body><main class="card"><span class="icon"><?= $escape($result[2]) ?></span><h1><?= $escape($result[0]) ?></h1><p><?= $escape($result[1]) ?></p><a class="button" href="<?= $escape($publicBase) ?>">Voltar ao site</a></main></body></html>
