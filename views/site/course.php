<?php
$primaryCandidate = (string) ($site['site_primary_color'] ?? '');
$primary = preg_match('/^#[0-9a-fA-F]{6}$/', $primaryCandidate) === 1 ? $primaryCandidate : (preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($site['primary_color'] ?? '')) === 1 ? (string) $site['primary_color'] : '#ed1c24');
$secondaryCandidate = (string) ($site['site_secondary_color'] ?? '');
$secondary = preg_match('/^#[0-9a-fA-F]{6}$/', $secondaryCandidate) === 1 ? $secondaryCandidate : (preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($site['secondary_color'] ?? '')) === 1 ? (string) $site['secondary_color'] : '#102a56');
$siteTitle = (string) ($site['site_title'] ?: $site['display_name']);
$logo = (string) ($site['logo_path'] ?? '');
$favicon = (string) ($site['favicon_path'] ?? '');
$store = ($product['selected_mode'] ?? 'catalog') === 'store';
$publicBase = rtrim((string) $basePath, '/') . '/site';
$whatsappDigits = preg_replace('/\D+/', '', (string) ($site['whatsapp'] ?? '')) ?? '';
$whatsapp = $whatsappDigits !== '' ? (str_starts_with($whatsappDigits, '55') ? $whatsappDigits : '55' . $whatsappDigits) : '';
$whatsappMessage = rawurlencode('Olá! Tenho interesse no curso ' . $product['name'] . '.');
?>
<!doctype html>
<html lang="pt-BR">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <title><?= $escape($product['name']) ?> · <?= $escape($siteTitle) ?></title>
 <?php if ($favicon !== ''): ?><link rel="icon" href="<?= $escape($assetBasePath . $favicon) ?>"><?php endif; ?>
 <style>
 :root{--primary:<?= $escape($primary) ?>;--secondary:<?= $escape($secondary) ?>;--ink:#14202d;--muted:#607181;--line:#dfe6ea;--soft:#f3f6f8}*{box-sizing:border-box}body{margin:0;color:var(--ink);background:var(--soft);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}.shell{width:min(75rem,calc(100% - 2rem));margin:auto}.top{border-bottom:1px solid var(--line);background:#fff}.nav{display:flex;align-items:center;justify-content:space-between;min-height:4.8rem;gap:1rem}.brand{color:inherit;text-decoration:none;font-weight:850}.brand img{width:9rem;height:3rem;object-fit:contain;object-position:left}.back,.secondary-button{display:inline-flex;align-items:center;justify-content:center;min-height:2.9rem;padding:.68rem 1rem;border:1px solid #c8d3db;border-radius:.7rem;color:var(--ink);background:#fff;text-decoration:none;font-weight:800}.course-layout{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(19rem,.75fr);gap:1.3rem;padding:2.2rem 0 4rem}.content,.action{border:1px solid var(--line);border-radius:1.1rem;background:#fff;box-shadow:0 .55rem 1.8rem rgb(27 45 63 / 7%)}.content{overflow:hidden}.cover{min-height:15rem;padding:2rem;color:#fff;background:linear-gradient(135deg,var(--secondary),color-mix(in srgb,var(--secondary) 70%,var(--primary)))}.eyebrow{margin:0 0 .65rem;color:color-mix(in srgb,var(--primary) 55%,#fff);font-size:.78rem;font-weight:900;letter-spacing:.09em;text-transform:uppercase}.cover h1{max-width:42rem;margin:0;font-size:clamp(2rem,5vw,3.7rem);line-height:1.05;letter-spacing:-.04em}.body{padding:1.6rem}.body h2{margin:0 0 .75rem;font-size:1.5rem}.description{color:var(--muted);font-size:1.02rem;line-height:1.75}.benefits{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-top:1.5rem}.benefit{padding:.95rem;border:1px solid var(--line);border-radius:.8rem;background:#fafcfd}.benefit strong{display:block;margin-bottom:.25rem}.benefit span{color:var(--muted);font-size:.85rem}.action{position:sticky;top:1rem;align-self:start;padding:1.35rem}.price-label{color:var(--muted);font-size:.8rem;font-weight:750}.price{display:block;margin:.2rem 0 1rem;color:var(--secondary);font-size:2rem;font-weight:950}.price small{display:block;margin-top:.15rem;color:var(--muted);font-size:.78rem;font-weight:650}.button{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:3.15rem;padding:.75rem 1.15rem;border:0;border-radius:.72rem;color:#fff;background:var(--primary);text-decoration:none;font:inherit;font-weight:900;cursor:pointer;box-shadow:0 .45rem 1rem color-mix(in srgb,var(--primary) 20%,transparent)}.secondary-button{width:100%;margin-top:.65rem}.hint{margin:1rem 0 0;color:var(--muted);font-size:.84rem;line-height:1.5}.alert{margin-bottom:1rem;padding:.85rem .95rem;border-radius:.72rem}.alert.error{color:#a51c27;background:#fff0f1;border:1px solid #ef9aa0}.alert.success{color:#087641;background:#ecfaf3;border:1px solid #8ed5b2}.interest-title{margin:0}.interest-copy{margin:.35rem 0 1rem;color:var(--muted);line-height:1.5}.fields{display:grid;gap:.8rem}.fields label{display:grid;gap:.35rem;font-size:.88rem;font-weight:800}.fields input,.fields select{width:100%;min-height:2.9rem;padding:.68rem .75rem;border:1px solid #becbd4;border-radius:.65rem;background:#fff;color:var(--ink);font:inherit}.fields input:focus,.fields select:focus{outline:3px solid color-mix(in srgb,var(--primary) 18%,transparent);border-color:var(--primary)}.privacy{display:flex!important;grid-template-columns:auto 1fr!important;align-items:start!important;color:var(--muted);font-size:.78rem!important;font-weight:600!important;line-height:1.4}.privacy input{width:auto;min-height:auto;margin-top:.18rem}.submit-space{margin-top:.3rem}@media(max-width:850px){.course-layout{grid-template-columns:1fr}.action{position:static}.benefits{grid-template-columns:1fr}}@media(max-width:540px){.brand img{width:7.5rem}.back{padding:.6rem .75rem}.cover{padding:1.4rem}.body{padding:1.2rem}}
 </style>
</head>
<body>
<header class="top"><nav class="shell nav"><a class="brand" href="<?= $escape($publicBase) ?>"><?php if ($logo !== ''): ?><img src="<?= $escape($assetBasePath . $logo) ?>" alt="<?= $escape($siteTitle) ?>"><?php else: ?><?= $escape($siteTitle) ?><?php endif; ?></a><a class="back" href="<?= $escape($publicBase) ?>#cursos">← Voltar aos cursos</a></nav></header>
<main class="shell course-layout">
 <article class="content">
  <header class="cover"><p class="eyebrow">Curso disponível</p><h1><?= $escape($product['name']) ?></h1></header>
  <div class="body"><h2>Sobre esta formação</h2><div class="description"><?= nl2br($escape((string) ($product['description'] ?: 'Uma formação preparada para desenvolver novas competências e ampliar suas oportunidades.'))) ?></div>
   <div class="benefits"><div class="benefit"><strong>Atendimento local</strong><span>Escolha o polo mais conveniente.</span></div><div class="benefit"><strong>Acesso organizado</strong><span>Matrícula integrada ao ambiente do aluno.</span></div><div class="benefit"><strong>Suporte da franquia</strong><span>Acompanhamento durante sua jornada.</span></div></div>
  </div>
 </article>
 <aside class="action">
  <?php if (!empty($error)): ?><div class="alert error"><?= $escape($error) ?></div><?php endif; ?>
  <?php if (!empty($message)): ?><div class="alert success"><?= $escape($message) ?></div><?php endif; ?>
  <?php if ($store): ?>
   <span class="price-label">Investimento</span><strong class="price">R$ <?= number_format((float) $product['value'], 2, ',', '.') ?><?php if ((int) $product['max_installments'] > 1): ?><small>parcelamento disponível no checkout</small><?php endif; ?></strong>
   <a class="button" href="<?= $escape($publicBase) ?>/checkout/<?= (int) $product['id'] ?>">Ir para o pagamento →</a>
   <?php if ($whatsapp !== ''): ?><a class="secondary-button" target="_blank" href="https://wa.me/<?= $escape($whatsapp) ?>?text=<?= $escape($whatsappMessage) ?>">Falar com um atendente</a><?php endif; ?>
   <p class="hint">Seus dados e o pagamento serão tratados em ambiente seguro. Após a confirmação, a matrícula seguirá o fluxo definido pela franquia.</p>
  <?php else: ?>
   <h2 class="interest-title">Quero receber atendimento</h2><p class="interest-copy">Preencha seus dados. A equipe da franquia receberá seu interesse como um novo lead.</p>
   <form method="post" action="<?= $escape($publicBase) ?>/curso/<?= (int) $product['id'] ?>/interesse"><?= $csrfField ?><div class="fields">
    <label>Nome completo *<input required maxlength="160" autocomplete="name" name="name"></label>
    <label>E-mail *<input required type="email" maxlength="190" autocomplete="email" name="email"></label>
    <label>Celular/WhatsApp *<input required maxlength="20" inputmode="tel" autocomplete="tel" name="phone"></label>
    <label>CPF ou CNPJ <input maxlength="18" inputmode="numeric" name="document" placeholder="Opcional nesta etapa"></label>
    <label>Polo de atendimento *<select required name="unit_id"><option value="">Selecione o polo</option><?php foreach ($units as $unit): ?><option value="<?= (int) $unit['id'] ?>"><?= $escape($unit['name']) ?><?= !empty($unit['city']) ? ' · ' . $escape($unit['city']) : '' ?></option><?php endforeach; ?></select></label>
    <label class="privacy"><input required type="checkbox" name="privacy_consent" value="1"><span>Autorizo o uso destes dados para receber atendimento sobre este curso.</span></label>
    <div class="submit-space"><button class="button" type="submit">Solicitar atendimento</button></div>
   </div></form>
  <?php endif; ?>
 </aside>
</main>
</body>
</html>
