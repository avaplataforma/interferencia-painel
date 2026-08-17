<?php
$validColor = static fn (string $value, string $fallback): string => preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : $fallback;
$primary = $validColor((string) ($site['site_primary_color'] ?? ''), $validColor((string) ($site['primary_color'] ?? ''), '#ed1c24'));
$secondary = $validColor((string) ($site['site_secondary_color'] ?? ''), $validColor((string) ($site['secondary_color'] ?? ''), '#102a56'));
$siteTitle = (string) ($site['site_title'] ?: $site['display_name']);
$logo = (string) ($site['logo_path'] ?? '');
$favicon = (string) ($site['favicon_path'] ?? '');
$store = ($site['selected_mode'] ?? 'catalog') === 'store';
$publicBase = rtrim((string) $basePath, '/') . '/site';
$isExternal = (int)($product['is_external'] ?? 0) === 1;
$isIndividualContent = ($product['product_kind'] ?? '') === 'provider_content';
$isTrail = ($product['product_kind'] ?? '') === 'trail';
$formationName = trim((string)preg_replace('/^Cat[aá]logo\s+/iu', '', (string)($product['catalog_name'] ?? ''))) ?: 'INTER';
$coverUrl = !empty($product['media_asset_id']) ? rtrim((string)$basePath, '/') . '/catalog-media/' . (int)$product['media_asset_id'] : trim((string)($product['cover_url'] ?? ''));
$coursePath = $isTrail ? '/trilha/' . (int)$product['id'] : ($isIndividualContent ? '/conteudo/' . (int)$product['id'] : ($isExternal ? '/catalogo-pro/' . (int)$product['id'] : '/curso/' . (int)$product['id']));
$whatsappDigits = preg_replace('/\D+/', '', (string) ($site['whatsapp'] ?? '')) ?? '';
$whatsapp = $whatsappDigits !== '' ? (str_starts_with($whatsappDigits, '55') ? $whatsappDigits : '55' . $whatsappDigits) : '';
$whatsappMessage = rawurlencode('Olá! Tenho interesse no curso ' . $product['name'] . '.');
$seoTitle = trim((string) ($product['seo_title'] ?? '')) ?: (string) $product['name'];
$seoDescription = trim((string) ($product['seo_description'] ?? '')) ?: trim((string) ($product['description'] ?? '')) ?: 'Conheça esta formação, consulte o conteúdo e solicite sua matrícula.';
$category = trim((string) ($product['category'] ?? '')) ?: 'Formação profissional';
$modality = trim((string) ($product['modality'] ?? '')) ?: 'Consulte a modalidade';
$workload = (int) ($product['workload_hours'] ?? 0);
$targetAudience = trim((string) ($product['target_audience'] ?? ''));
$curriculum = array_values(array_filter(array_map('trim', preg_split('/\R+/', (string) ($product['curriculum'] ?? '')) ?: [])));
$requirements = trim((string) ($product['requirements'] ?? ''));
$certificateText = trim((string) ($product['certificate_text'] ?? ''));
$faq = [];
foreach (preg_split('/\R+/', (string) ($product['faq_text'] ?? '')) ?: [] as $faqLine) {
    [$question, $answer] = array_pad(array_map('trim', explode('|', $faqLine, 2)), 2, '');
    if ($question !== '') $faq[] = ['question' => $question, 'answer' => $answer];
}
$rating = (float) ($product['rating_average'] ?? 0);
$ratingCount = (int) ($product['rating_count'] ?? 0);
$moduleCount = max(0, (int) ($product['lesson_count'] ?? count($curriculum)));
$accessMonths = max(0, (int) ($product['access_months'] ?? 0));
$kindLabel = $isTrail ? 'Trilha' : 'Curso';
$detailHeading = $isTrail ? 'Sobre esta Trilha' : 'Sobre este Curso';
$siteHost = preg_replace('/[^a-z0-9.-]/i', '', (string) ($site['site_host'] ?? '')) ?: 'mundointer.com.br';
$canonicalUrl = 'https://' . $siteHost . $publicBase . $coursePath;
$absoluteCoverUrl = $coverUrl === '' ? '' : (preg_match('#^https?://#i', $coverUrl) === 1 ? $coverUrl : 'https://' . $siteHost . '/' . ltrim($coverUrl, '/'));
$relatedProducts = [];
foreach (($isExternal ? ($site['external_products'] ?? []) : ($site['products'] ?? [])) as $related) {
    if ((int) $related['id'] === (int) $product['id']) continue;
    if ($category !== 'Formação profissional' && trim((string) ($related['category'] ?? '')) !== $category) continue;
    $relatedProducts[] = $related;
    if (count($relatedProducts) === 3) break;
}
$structuredCourse = ['@type' => 'Course', 'name' => (string) $product['name'], 'description' => $seoDescription, 'url' => $canonicalUrl, 'provider' => ['@type' => 'Organization', 'name' => $siteTitle]];
$structuredCourse['courseMode'] = $modality;
$structuredCourse['educationalLevel'] = $category;
$structuredCourse['offers'] = ['@type' => 'Offer', 'priceCurrency' => 'BRL', 'price' => number_format((float)($product['value'] ?? 0), 2, '.', ''), 'availability' => 'https://schema.org/InStock', 'url' => $canonicalUrl];
if ($absoluteCoverUrl !== '') $structuredCourse['image'] = $absoluteCoverUrl;
if ($isTrail && $curriculum !== []) $structuredCourse['hasPart'] = array_map(static fn(string $item): array => ['@type' => 'Course', 'name' => $item], $curriculum);
$structuredData = ['@context' => 'https://schema.org', '@graph' => [$structuredCourse, ['@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Cursos', 'item' => 'https://' . $siteHost . $publicBase . '#cursos'], ['@type' => 'ListItem', 'position' => 2, 'name' => (string)$product['name'], 'item' => $canonicalUrl]]]]];
if ($workload > 0) $structuredData['@graph'][0]['timeRequired'] = 'PT' . $workload . 'H';
if ($rating > 0 && $ratingCount > 0) $structuredData['@graph'][0]['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $rating, 'reviewCount' => $ratingCount];
?>
<!doctype html>
<html lang="pt-BR">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <meta name="description" content="<?= $escape($seoDescription) ?>"><meta property="og:type" content="website"><meta property="og:title" content="<?= $escape($seoTitle) ?>"><meta property="og:description" content="<?= $escape($seoDescription) ?>"><meta property="og:url" content="<?= $escape($canonicalUrl) ?>"><?php if ($absoluteCoverUrl !== ''): ?><meta property="og:image" content="<?= $escape($absoluteCoverUrl) ?>"><?php endif; ?>
 <link rel="canonical" href="<?= $escape($canonicalUrl) ?>">
 <title><?= $escape($seoTitle) ?> · <?= $escape($siteTitle) ?></title>
 <?php if ($favicon !== ''): ?><link rel="icon" href="<?= $escape($assetBasePath . $favicon) ?>"><?php endif; ?>
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
 <script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
 <style>
 :root{--primary:<?= $escape($primary) ?>;--secondary:<?= $escape($secondary) ?>;--ink:#14202d;--muted:#607181;--line:#dfe6ea;--soft:#f3f6f8}*{box-sizing:border-box}body{margin:0;color:var(--ink);background:var(--soft);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}.skip-link{position:fixed;left:1rem;top:-5rem;z-index:30;padding:.7rem 1rem;color:#fff;background:var(--secondary);border-radius:.5rem}.skip-link:focus{top:1rem}.shell{width:min(75rem,calc(100% - 2rem));margin:auto}.top{border-bottom:1px solid var(--line);background:#fff}.nav{display:flex;align-items:center;justify-content:space-between;min-height:4.8rem;gap:1rem}.brand{color:inherit;text-decoration:none;font-weight:850}.brand img{width:9rem;height:3rem;object-fit:contain;object-position:left}.back,.secondary-button{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;min-height:2.9rem;padding:.68rem 1rem;border:1px solid #c8d3db;border-radius:.7rem;color:var(--ink);background:#fff;text-decoration:none;font-weight:800}.course-layout{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(19rem,.75fr);gap:1.3rem;padding:2.2rem 0 4rem}.breadcrumb{grid-column:1/-1;margin-bottom:-.6rem}.breadcrumb ol{display:flex;flex-wrap:wrap;align-items:center;gap:.45rem;margin:0;padding:0;list-style:none;font-size:.85rem}.breadcrumb li{display:inline-flex;align-items:center;gap:.45rem;color:var(--muted)}.breadcrumb li+li::before{content:"/";color:#b7c2ca;margin-right:.45rem}.breadcrumb a{display:inline-flex;align-items:center;gap:.4rem;color:var(--muted);text-decoration:none;font-weight:700}.breadcrumb a:hover{color:var(--primary)}.breadcrumb [aria-current]{color:var(--ink);font-weight:850}.cover-rating{display:inline-flex;align-items:center;gap:.4rem;margin:.9rem 0 0;padding:.35rem .7rem;border-radius:999px;color:#2b1d05;background:#ffd98a;font-size:.85rem;font-weight:850}.cover-rating small{font-weight:700;opacity:.85}a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 55%,#fff);outline-offset:2px;border-radius:.4rem}.content,.action{border:1px solid var(--line);border-radius:1.1rem;background:#fff;box-shadow:0 .55rem 1.8rem rgb(27 45 63 / 7%)}.content{overflow:hidden}.cover{min-height:17rem;padding:2rem;color:#fff;background:linear-gradient(135deg,var(--secondary),color-mix(in srgb,var(--secondary) 70%,var(--primary)))}.eyebrow{margin:0 0 .65rem;color:color-mix(in srgb,var(--primary) 55%,#fff);font-size:.78rem;font-weight:900;letter-spacing:.09em;text-transform:uppercase}.cover h1{max-width:42rem;margin:0;font-size:clamp(2rem,5vw,3.7rem);line-height:1.05;letter-spacing:-.04em}.cover-meta{display:flex;flex-wrap:wrap;gap:.55rem;margin-top:1.25rem}.cover-meta span{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .65rem;border:1px solid rgb(255 255 255 / 24%);border-radius:999px;background:rgb(255 255 255 / 10%);font-size:.8rem;font-weight:800}.body{padding:1.6rem}.body h2{margin:0 0 .75rem;font-size:1.5rem}.description{color:var(--muted);font-size:1.02rem;line-height:1.75}.benefits,.facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-top:1.5rem}.benefit,.fact{padding:.95rem;border:1px solid var(--line);border-radius:.8rem;background:#fafcfd}.benefit{display:flex;gap:.7rem;align-items:flex-start}.benefit i{display:grid;place-items:center;flex:0 0 auto;width:2.4rem;height:2.4rem;border-radius:.7rem;color:var(--primary);background:color-mix(in srgb,var(--primary) 10%,#fff);font-size:1.05rem}.benefit strong,.fact strong{display:flex;align-items:center;gap:.4rem;margin-bottom:.25rem}.benefit span,.fact span{color:var(--muted);font-size:.85rem}.detail-section{margin-top:1.8rem;padding-top:1.5rem;border-top:1px solid var(--line)}.detail-section p{color:var(--muted);line-height:1.7;white-space:pre-line}.curriculum{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;padding:0;list-style:none}.curriculum li{display:flex;align-items:flex-start;gap:.5rem;padding:.8rem;border:1px solid var(--line);border-radius:.7rem;background:#fafcfd}.faq{display:grid;gap:.6rem}.faq details{padding:.85rem 1rem;border:1px solid var(--line);border-radius:.75rem}.faq summary{font-weight:850;cursor:pointer}.faq p{margin:.65rem 0 0}.related{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.related a{display:grid;gap:.4rem;padding:1rem;border:1px solid var(--line);border-radius:.8rem;color:inherit;text-decoration:none}.related small{color:var(--muted)}.action{position:sticky;top:1rem;align-self:start;padding:1.35rem}.price-label{color:var(--muted);font-size:.8rem;font-weight:750}.price{display:block;margin:.2rem 0 1rem;color:var(--secondary);font-size:2rem;font-weight:950}.price small{display:block;margin-top:.15rem;color:var(--muted);font-size:.78rem;font-weight:650}.button{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;width:100%;min-height:3.15rem;padding:.75rem 1.15rem;border:0;border-radius:.72rem;color:#fff;background:var(--primary);text-decoration:none;font:inherit;font-weight:900;cursor:pointer;box-shadow:0 .45rem 1rem color-mix(in srgb,var(--primary) 20%,transparent)}.secondary-button{width:100%;margin-top:.65rem}.hint{margin:1rem 0 0;color:var(--muted);font-size:.84rem;line-height:1.5}.alert{margin-bottom:1rem;padding:.85rem .95rem;border-radius:.72rem}.alert.error{color:#a51c27;background:#fff0f1;border:1px solid #ef9aa0}.alert.success{color:#087641;background:#ecfaf3;border:1px solid #8ed5b2}.interest-title{margin:0}.interest-copy{margin:.35rem 0 1rem;color:var(--muted);line-height:1.5}.fields{display:grid;gap:.8rem}.fields label{display:grid;gap:.35rem;font-size:.88rem;font-weight:800}.fields input,.fields select{width:100%;min-height:2.9rem;padding:.68rem .75rem;border:1px solid #becbd4;border-radius:.65rem;background:#fff;color:var(--ink);font:inherit}.fields input:focus,.fields select:focus{outline:3px solid color-mix(in srgb,var(--primary) 18%,transparent);border-color:var(--primary)}.privacy{display:flex!important;grid-template-columns:auto 1fr!important;align-items:start!important;color:var(--muted);font-size:.78rem!important;font-weight:600!important;line-height:1.4}.privacy input{width:auto;min-height:auto;margin-top:.18rem}.submit-space{margin-top:.3rem}@media(max-width:850px){.course-layout{grid-template-columns:1fr}.action{position:static}.benefits,.facts,.related{grid-template-columns:1fr 1fr}}@media(max-width:600px){.benefits,.facts,.related,.curriculum{grid-template-columns:1fr}}@media(max-width:540px){.brand img{width:7.5rem}.back{display:inline-flex;align-items:center;gap:.45rem;padding:.6rem .75rem}.cover{padding:1.4rem}.body{padding:1.2rem}}
 </style>
 <?php if ($coverUrl !== ''): ?><style>.cover{position:relative;isolation:isolate;background-image:url('<?= $escape($coverUrl) ?>');background-position:center;background-size:cover}.cover:before{content:"";position:absolute;z-index:-1;inset:0;background:linear-gradient(90deg,rgb(4 14 28 / 92%),rgb(4 14 28 / 38%))}</style><?php endif; ?>
</head>
<body data-site-organization="<?= (int) ($site['organization_id'] ?? 0) ?>" data-site-event-url="<?= $escape($publicBase) ?>/events" data-site-event-type="course_view" data-site-entity-type="course" data-site-entity-id="<?= (int) $product['id'] ?>" data-site-ga4="<?= $escape($ga4Id ?? '') ?>">
<a class="skip-link" href="#conteudo">Ir para o conteúdo</a>
<header class="top"><nav class="shell nav"><a class="brand" href="<?= $escape($publicBase) ?>"><?php if ($logo !== ''): ?><img src="<?= $escape($assetBasePath . $logo) ?>" alt="<?= $escape($siteTitle) ?>"><?php else: ?><?= $escape($siteTitle) ?><?php endif; ?></a><a class="back" href="<?= $escape($publicBase) ?>#cursos"><i class="fa-solid fa-arrow-left"></i> Voltar aos cursos</a></nav></header>
<main class="shell course-layout" id="conteudo">
 <nav class="breadcrumb" aria-label="Trilha de navegação"><ol><li><a href="<?= $escape($publicBase) ?>#inicio"><i class="fa-solid fa-house" aria-hidden="true"></i> Início</a></li><li><a href="<?= $escape($publicBase) ?>#cursos">Cursos</a></li><li aria-current="page"><?= $escape($product['name']) ?></li></ol></nav>
 <article class="content">
   <header class="cover"><p class="eyebrow"><?= $escape($kindLabel) ?> · Formação <?= $escape($formationName) ?></p><h1><?= $escape($product['name']) ?></h1><?php $rating=(float)($product['rating_average']??0);$ratingCount=(int)($product['rating_count']??0);if($rating<=0&&isset($testimonialRatings[mb_strtolower((string)$product['name'])])){$rating=(float)$testimonialRatings[mb_strtolower((string)$product['name'])]['avg'];$ratingCount=(int)$testimonialRatings[mb_strtolower((string)$product['name'])]['count'];}if($rating>0):?><p class="cover-rating" aria-label="Nota <?= number_format($rating,1,',','.') ?> de 5"><i class="fa-solid fa-star"></i> <?= number_format($rating,1,',','.') ?><?php if($ratingCount>0):?> <small>(<?= $ratingCount ?> avaliação(ões))</small><?php endif;?></p><?php endif;?><div class="cover-meta"><span><i class="fa-solid <?= $isTrail ? 'fa-route' : 'fa-book-open' ?>"></i> <?= $escape($kindLabel) ?></span><span><i class="fa-regular fa-clock"></i> Carga Horária: <?= $workload ?> horas</span><?php if ($accessMonths > 0): ?><span><i class="fa-solid fa-calendar-check"></i> Acesso por <?= $accessMonths ?> <?= $accessMonths === 1 ? 'mês' : 'meses' ?></span><?php endif; ?></div></header>
   <div class="body"><h2><?= $escape($detailHeading) ?></h2><div class="description"><?= nl2br($escape((string) ($product['description'] ?: 'Uma formação preparada para desenvolver novas competências e ampliar suas oportunidades.'))) ?></div>
     <div class="benefits"><div class="benefit"><i class="fa-solid <?= $isTrail ? 'fa-route' : 'fa-bullseye' ?>"></i><div><strong><?= $isTrail ? 'Jornada completa' : 'Conteúdo objetivo' ?></strong><span><?= $isTrail ? 'Cursos organizados em uma sequência de aprendizagem.' : 'Um Curso focado em uma competência específica.' ?></span></div></div><div class="benefit"><i class="fa-solid fa-award"></i><div><strong>Certificação</strong><span>Certificado de conclusão para valorizar o seu currículo.</span></div></div><div class="benefit"><i class="fa-solid fa-headset"></i><div><strong>Suporte da franquia</strong><span>Acompanhamento local durante sua jornada.</span></div></div></div>
   <?php if ($targetAudience !== ''): ?><section class="detail-section"><h2>Para quem é este curso?</h2><p><?= nl2br($escape($targetAudience)) ?></p></section><?php endif; ?>
     <?php if ($curriculum !== []): ?><section class="detail-section"><h2><?= $isTrail ? 'Cursos desta Trilha' : 'Conteúdo programático' ?></h2><ul class="curriculum"><?php foreach ($curriculum as $index => $item): ?><li><i class="fa-regular fa-circle-check"></i> <?php if ($isTrail): ?><strong>Curso <?= $index + 1 ?>:</strong> <?php endif; ?><?= $escape($item) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
   <?php if ($requirements !== '' || $certificateText !== ''): ?><section class="detail-section"><div class="facts"><?php if ($requirements !== ''): ?><div class="fact"><strong><i class="fa-solid fa-list-check"></i> Requisitos</strong><span><?= nl2br($escape($requirements)) ?></span></div><?php endif; ?><?php if ($certificateText !== ''): ?><div class="fact"><strong><i class="fa-solid fa-certificate"></i> Certificado</strong><span><?= nl2br($escape($certificateText)) ?></span></div><?php endif; ?><div class="fact"><strong><i class="fa-solid fa-shield-halved"></i> Compra segura</strong><span>Atendimento e pagamento integrados à franquia.</span></div></div></section><?php endif; ?>
   <?php if ($faq !== []): ?><section class="detail-section"><h2>Perguntas frequentes</h2><div class="faq"><?php foreach ($faq as $item): ?><details><summary><?= $escape($item['question']) ?></summary><?php if ($item['answer'] !== ''): ?><p><?= nl2br($escape($item['answer'])) ?></p><?php endif; ?></details><?php endforeach; ?></div></section><?php endif; ?>
    <?php if ($relatedProducts !== []): ?><section class="detail-section"><h2>Você também pode gostar</h2><div class="related"><?php foreach ($relatedProducts as $related): $relatedKind=(string)($related['product_kind']??'finance_product');$relatedPath=$relatedKind==='provider_content'?'/conteudo/':($relatedKind==='trail'?'/trilha/':($relatedKind==='provider_course'?'/catalogo-pro/':'/curso/'));?><a href="<?= $escape($publicBase.$relatedPath) ?><?= (int) $related['id'] ?>"><strong><?= $escape($related['name']) ?></strong><small><?= $escape($relatedKind === 'trail' ? 'Trilha' : 'Curso') ?> · <?= $escape($related['category'] ?? 'Formação profissional') ?></small></a><?php endforeach; ?></div></section><?php endif; ?>
  </div>
 </article>
 <aside class="action">
  <?php if (!empty($error)): ?><div class="alert error"><?= $escape($error) ?></div><?php endif; ?>
  <?php if (!empty($message)): ?><div class="alert success"><?= $escape($message) ?></div><?php endif; ?>
  <?php if ($store && !$isExternal): ?>
   <span class="price-label">Investimento</span><strong class="price">R$ <?= number_format((float) $product['value'], 2, ',', '.') ?><?php if ((int) $product['max_installments'] > 1): ?><small>em até <?= (int) $product['max_installments'] ?>x, conforme a forma de pagamento</small><?php endif; ?></strong>
   <a class="button" href="<?= $escape($publicBase) ?>/checkout/<?= (int) $product['id'] ?>"><i class="fa-solid fa-lock"></i> Ir para o pagamento</a>
   <?php if ($whatsapp !== ''): ?><a class="secondary-button" target="_blank" rel="noopener" href="https://wa.me/<?= $escape($whatsapp) ?>?text=<?= $escape($whatsappMessage) ?>"><i class="fa-brands fa-whatsapp"></i> Falar com um atendente</a><?php endif; ?>
   <p class="hint">Seus dados e o pagamento serão tratados em ambiente seguro. Após a confirmação, a matrícula seguirá o fluxo definido pela franquia.</p>
  <?php else: ?>
   <?php if($isExternal): ?><span class="price-label">Investimento</span><?php $priceValue=(float)$product['value'];$installments=max(1,(int)($product['max_installments']??1));$displayPrice=$installments>1?round($priceValue/$installments,2):$priceValue;?><strong class="price">R$ <?= number_format($displayPrice,2,',','.') ?><?php if($installments>1): ?><small>em até <?= $installments ?>x · total R$ <?= number_format($priceValue,2,',','.') ?></small><?php endif; ?></strong><?php endif; ?>
    <h2 class="interest-title"><?= $isTrail ? 'Quero fazer esta Trilha' : 'Quero fazer este Curso' ?></h2><p class="interest-copy"><?= $isTrail ? 'A equipe confirmará as condições comerciais, a matrícula e o acesso aos Cursos desta Trilha.' : ($isExternal?'Este Curso pertence à Formação '.$escape($formationName).'. A equipe confirmará pagamento, matrícula e acesso seguro ao AVA definido.':'Preencha seus dados. A equipe da franquia receberá seu interesse como um novo lead.') ?></p>
   <form method="post" action="<?= $escape($publicBase.$coursePath) ?>/interesse"><?= $csrfField ?><div class="fields">
    <label>Nome completo *<input required maxlength="160" autocomplete="name" name="name"></label>
    <label>E-mail *<input required type="email" maxlength="190" autocomplete="email" name="email"></label>
    <label>Celular/WhatsApp *<input required maxlength="20" inputmode="tel" autocomplete="tel" name="phone"></label>
    <label>CPF ou CNPJ <input maxlength="18" inputmode="numeric" name="document" placeholder="Opcional nesta etapa"></label>
    <label>Polo de atendimento *<select required name="unit_id"><option value="">Selecione o polo</option><?php foreach ($units as $unit): ?><option value="<?= (int) $unit['id'] ?>"><?= $escape($unit['name']) ?><?= !empty($unit['city']) ? ' · ' . $escape($unit['city']) : '' ?></option><?php endforeach; ?></select></label>
    <label class="privacy"><input required type="checkbox" name="privacy_consent" value="1"><span>Autorizo o uso destes dados para receber atendimento sobre este curso.</span></label>
     <div class="submit-space"><button class="button" type="submit"><i class="fa-regular fa-paper-plane"></i> Solicitar matrícula</button></div>
   </div></form>
  <?php endif; ?>
 </aside>
</main>
<script src="<?= $escape($assetBasePath) ?>/assets/js/site-public.js?v=13" defer></script>
</body>
</html>
