<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#ffffff">
<title><?= $escape($title) ?></title>
<link rel="icon" type="image/png" href="<?= $escape($assetBasePath.$brandFavicon) ?>">
<link rel="stylesheet" href="<?= $escape($assetBasePath) ?>/assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= $escape($assetBasePath) ?>/assets/vendor/fontawesome/css/all.min.css">
<style>
:root{--accent:#ed1c24;--navy:#082d72;--ink:#102235;--muted:#607387;--line:#d8e0e6;--surface:#fff;--canvas:#eef2f5;--success:#087443}
*{box-sizing:border-box}
body{margin:0;background:var(--canvas);color:var(--ink);font-family:Arial,sans-serif}
.contract-shell{width:min(70rem,calc(100% - 2rem));margin:2rem auto 3rem}
.document-header,.contract-card,.signature-card{border:1px solid var(--line);border-radius:1rem;background:var(--surface);box-shadow:0 .6rem 1.8rem #1022350d}
.document-header{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:1.5rem;align-items:center;padding:1.75rem 2rem;margin-bottom:1rem}
.brand-logo{width:10.5rem;max-height:6rem;object-fit:contain;object-position:left center}
.document-eyebrow{margin:0 0 .35rem;color:var(--accent);font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.document-header h1{margin:0;font-size:clamp(1.75rem,3vw,2.6rem);line-height:1.08;letter-spacing:-.035em}
.document-meta{display:flex;flex-wrap:wrap;gap:.45rem 1rem;margin:.75rem 0 0;color:var(--muted);font-size:.9rem}
.document-meta span{display:inline-flex;align-items:center;gap:.35rem}
.document-state{display:grid;justify-items:end;gap:.55rem;min-width:10rem;text-align:right}
.state-badge{display:inline-flex;align-items:center;gap:.45rem;padding:.48rem .72rem;border-radius:999px;background:#e9f8ef;color:var(--success);font-size:.78rem;font-weight:800}
.document-state small{color:var(--muted)}
.alert{margin-bottom:1rem;padding:1rem 1.15rem;border-radius:.75rem}.success{color:#067647;background:#e8f7ef}.danger{color:#b4232c;background:#fff0f1}
.contract-card{padding:2.5rem 3rem}
.contract-body{font:400 1.03rem/1.75 Georgia,serif;color:#192b3c}
.contract-body>*:first-child{margin-top:0}.contract-body>*:last-child{margin-bottom:0}
.contract-body h1,.contract-body h2,.contract-body h3{font-family:Arial,sans-serif;color:var(--ink);line-height:1.2}.contract-body h2{margin-top:2rem;font-size:1.42rem}.contract-body h3{margin-top:1.5rem;font-size:1.12rem}
.contract-body p{text-align:justify}.contract-body blockquote{margin:1.25rem 0;padding:.8rem 1.1rem;border-left:.25rem solid var(--accent);background:#fff7f7}.contract-body table{width:100%;border-collapse:collapse}.contract-body th,.contract-body td{padding:.65rem;border:1px solid var(--line)}
.signature-card{margin-top:1rem;padding:1.5rem 1.75rem}
.signature-heading{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:1rem}.signature-heading h2{margin:0;font-size:1.45rem}.signature-heading p{margin:.3rem 0 0;color:var(--muted)}
.signature-proof{display:grid;grid-template-columns:1.15fr .8fr 1.5fr;gap:.75rem}
.proof-item{min-width:0;padding:1rem;border:1px solid #e2e7eb;border-radius:.75rem;background:#f7f9fa}.proof-item span{display:block;margin-bottom:.35rem;color:var(--muted);font-size:.75rem;font-weight:800;text-transform:uppercase}.proof-item strong{display:block}.proof-item small{display:block;margin-top:.25rem;color:var(--muted);overflow-wrap:anywhere}
.integrity-code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:.78rem;line-height:1.45;overflow-wrap:anywhere}
.document-actions{display:flex;justify-content:flex-end;gap:.65rem;margin-top:1rem}.button{display:inline-flex;min-height:2.85rem;align-items:center;justify-content:center;gap:.5rem;padding:.75rem 1rem;border:1px solid transparent;border-radius:.65rem;color:#fff;background:var(--accent);font:800 .92rem Arial,sans-serif;cursor:pointer}.button:hover{filter:brightness(.94)}
.signature-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.signature-grid label{display:grid;gap:.35rem;font-weight:700}.signature-grid input{min-height:2.8rem;padding:.65rem;border:1px solid #aebbc5;border-radius:.55rem}.full{grid-column:1/-1}.accept{display:flex!important;align-items:flex-start;grid-template-columns:auto 1fr!important}.accept input{width:auto;min-height:auto}
@media(max-width:800px){.document-header{grid-template-columns:1fr;padding:1.35rem}.brand-logo{width:8rem}.document-state{justify-items:start;text-align:left}.contract-card{padding:1.5rem}.signature-proof{grid-template-columns:1fr 1fr}.proof-integrity{grid-column:1/-1}}
@media(max-width:650px){.contract-shell{width:min(100% - 1rem,70rem);margin:.5rem auto 1rem}.document-header,.contract-card,.signature-card{border-radius:.8rem}.signature-grid,.signature-proof{grid-template-columns:1fr}.proof-integrity{grid-column:auto}.document-actions .button{width:100%}.signature-heading{display:block}}
@page{size:A4 portrait;margin:14mm 15mm 16mm}
@media print{
 body{background:#fff;color:#000;-webkit-print-color-adjust:exact;print-color-adjust:exact}
 .contract-shell{width:auto;margin:0}
 .screen-only,.signature-card form{display:none!important}
 .document-header,.contract-card,.signature-card{border-color:#cbd3d9;border-radius:0;box-shadow:none}
 .document-header{grid-template-columns:auto 1fr auto;padding:0 0 8mm;margin:0 0 8mm;border:0;border-bottom:1px solid #aeb8c0}
 .brand-logo{width:35mm;max-height:22mm}
 .document-header h1{font-size:19pt}.document-state{min-width:auto}.document-meta{font-size:8.5pt}
 .contract-card{padding:0;border:0}.contract-body{font-size:10.5pt;line-height:1.55}.contract-body h2{font-size:14pt;break-after:avoid}.contract-body h3{font-size:11.5pt;break-after:avoid}.contract-body p{orphans:3;widows:3}
 .signature-card{break-inside:avoid;margin-top:9mm;padding:5mm}.signature-heading{margin-bottom:4mm}.signature-proof{grid-template-columns:1.1fr .85fr 1.5fr}.proof-item{padding:3.5mm;background:#fff}.state-badge{border:1px solid #9acdaf}
}
</style>
</head>
<body>
<main class="contract-shell">
 <header class="document-header">
  <img class="brand-logo" src="<?= $escape($assetBasePath.$brandLogo) ?>" alt="Mundo Inter">
  <div>
   <p class="document-eyebrow">Instrumento contratual eletrônico</p>
   <h1><?= $escape($contract['title']) ?></h1>
   <div class="document-meta"><span><i class="fa-solid fa-building"></i><?= $escape($contract['franchise_name']) ?></span><span><i class="fa-solid fa-id-card"></i>CNPJ <?= $escape($contract['cnpj']) ?></span></div>
  </div>
  <div class="document-state">
   <?php if($contract['status']==='signed'):?><span class="state-badge"><i class="fa-solid fa-circle-check"></i>Assinado</span><?php else:?><span class="state-badge"><i class="fa-solid fa-clock"></i>Aguardando assinatura</span><?php endif;?>
   <small>Documento Mundo Inter</small>
  </div>
 </header>
 <?php if(!empty($message)):?><div class="alert success screen-only"><?= $escape($message) ?></div><?php endif;?>
 <?php if(!empty($error)):?><div class="alert danger screen-only"><?= $escape($error) ?></div><?php endif;?>
 <article class="contract-card contract-body"><?= \Interferencia\Modules\Organization\ContractContent::toHtml((string)$contract['content']) ?></article>
 <section class="signature-card">
 <?php if($contract['status']==='signed'):?>
  <div class="signature-heading"><div><p class="document-eyebrow">Comprovante eletrônico</p><h2>Assinatura confirmada</h2><p>Identidade, data e integridade registradas no momento do aceite.</p></div><span class="state-badge"><i class="fa-solid fa-shield-halved"></i>Integridade verificada</span></div>
  <div class="signature-proof">
   <div class="proof-item"><span>Signatário</span><strong><?= $escape($contract['signer_name']) ?></strong><small><?= $escape($contract['signer_email']) ?></small></div>
   <div class="proof-item"><span>Data da assinatura</span><strong><?= $escape(date('d/m/Y H:i',strtotime((string)$contract['signed_at']))) ?></strong><small>Horário de Brasília</small></div>
   <div class="proof-item proof-integrity"><span>Código de integridade</span><strong class="integrity-code"><?= $escape($contract['evidence_hash']) ?></strong></div>
  </div>
  <div class="document-actions screen-only"><button class="button" type="button" data-print-page><i class="fa-solid fa-file-pdf"></i> Imprimir ou salvar em PDF</button></div>
 <?php else:?>
  <div class="signature-heading"><div><p class="document-eyebrow">Aceite eletrônico</p><h2>Assinatura do contrato</h2><p>Confirme sua identidade e o aceite integral deste instrumento.</p></div></div>
  <form class="signature-grid" method="post" action="<?= $escape($basePath) ?>/contrato/<?= $escape($token) ?>/assinar"><?= $csrfField ?><label>Nome completo *<input name="signer_name" required value="<?= $escape($contract['manager_name']) ?>"></label><label>E-mail *<input type="email" name="signer_email" required value="<?= $escape($contract['manager_email']) ?>"></label><label class="full">CPF ou CNPJ do signatário *<input name="signer_document" required inputmode="numeric"></label><label class="accept full"><input type="checkbox" name="accepted" value="1" required><span>Li o contrato, concordo integralmente com seus termos e reconheço a validade deste aceite eletrônico.</span></label><div class="full"><button class="button" type="submit" data-confirm-submit="Confirmar a assinatura deste contrato?"><i class="fa-solid fa-signature"></i> Assinar contrato</button></div></form>
 <?php endif;?>
 </section>
</main>
<script src="<?= $escape($assetBasePath) ?>/assets/js/app.js?v=19"></script>
</body>
</html>
