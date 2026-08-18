<?php
$ava=$avaSettings??[];$mode=(string)($ava['access_mode']??'shared');$primary=(string)($ava['primary_ava']??'shared');$shared=$ava['shared']??[];$own=$ava['own']??[];
$executionLabels=['shared_ava'=>'Dentro do AVA Cursos','provider_ava'=>'AVA do fornecedor','franchise_moodle'=>'Moodle exclusivo da franquia'];
$executionIcons=['shared_ava'=>'fa-earth-americas','provider_ava'=>'fa-arrow-up-right-from-square','franchise_moodle'=>'fa-school'];
$officialAvaUrl=(string)($organization['ava_access_url']??'')!==''?(string)$organization['ava_access_url']:'https://avacursos.com.br/franquia.php?slug='.(string)($organization['panel_slug']??'');
$brandAsset=static fn(?string $path):string=>is_string($path)&&trim($path)!==''?(string)$path:'';
?>
<section class="card organization-section" id="ava" data-organization-panel="ava" hidden>
 <header class="organization-section-header"><span class="organization-section-icon"><i class="fa-solid fa-laptop"></i></span><div><h2>AVA</h2><p class="meta">Personalização exclusiva do AVA da franquia: identidade visual, suporte e ambiente acadêmico.</p></div></header>
 <form class="organization-ava-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/ava"><?= $csrfField ?>
  <div class="ava-communication-card">
   <div class="ava-organization-title"><span><i class="fa-solid fa-id-card"></i></span><div><h3>Identidade e suporte no AVA</h3><p class="meta">Defina como esta franquia será identificada no Moodle e quais informações aparecerão na experiência personalizada do aluno.</p></div></div>
   <div class="organization-fields">
    <label>Título do login<input maxlength="160" name="login_title" value="<?= $escape($organization['login_title']??'') ?>" placeholder="Ex.: Portal da Franquia"></label>
    <label class="field-full">Link de acesso ao AVA (personalizado)<input type="url" maxlength="500" name="ava_access_url" value="<?= $escape($organization['ava_access_url']??'') ?>" placeholder="https://avacursos.com.br/franquia.php?slug=<?= $escape($organization['panel_slug']??'') ?>"><small>Endereço que a franquia usa para entrar no AVA. Se vazio, o botão Sala de Aula do site usa automaticamente o padrão com o login exclusivo.</small></label>
    <div class="field-full ava-url-copy"><code data-ava-url><?= $escape($officialAvaUrl) ?></code><button class="button button-secondary button-sm" type="button" data-copy-target="ava-url"><i class="fa-solid fa-copy"></i> Copiar</button></div>
    <div class="field-full organization-help"><i class="fa-solid fa-palette"></i><span>Identidade visual no AVA (cores e imagens)</span></div>
    <label class="field-third">Cor primária<div class="color-field"><input type="color" name="ava_primary_color" value="<?= $escape($organization['ava_primary_color']??'') ?>"><input aria-label="Cor primária em hexadecimal" maxlength="7" value="<?= $escape($organization['ava_primary_color']??'') ?>" placeholder="#ed1c24" data-color-text readonly></div><small>Se vazia, usa a cor do site da franquia.</small></label>
    <label class="field-third">Cor secundária<div class="color-field"><input type="color" name="ava_secondary_color" value="<?= $escape($organization['ava_secondary_color']??'') ?>"><input aria-label="Cor secundária em hexadecimal" maxlength="7" value="<?= $escape($organization['ava_secondary_color']??'') ?>" placeholder="#102a56" data-color-text readonly></div><small>Se vazia, usa a cor do site da franquia.</small></label>
    <label class="field-third">Logo do Login<input type="file" name="ava_logo" accept="image/png,image/jpeg,image/webp"><?php if($brandAsset($organization['logo_path']??null)!==''):?><span class="ava-brand-preview"><img src="<?= $escape($brandAsset($organization['logo_path'])) ?>" alt="Logo do Login atual"></span><?php else:?><small>Nenhuma imagem enviada ainda.</small><?php endif;?></label>
    <label class="field-third">Logo da Barra<input type="file" name="ava_navbar_logo" accept="image/png,image/jpeg,image/webp"><?php if($brandAsset($organization['ava_navbar_logo_path']??null)!==''):?><span class="ava-brand-preview"><img src="<?= $escape($brandAsset($organization['ava_navbar_logo_path'])) ?>" alt="Logo da Barra atual"></span><?php else:?><small>Se vazia, a barra usa o favicon.</small><?php endif;?></label>
    <label class="field-third">Favicon<input type="file" name="ava_favicon" accept="image/png,image/jpeg,image/webp"><?php if($brandAsset($organization['favicon_path']??null)!==''):?><span class="ava-brand-preview"><img src="<?= $escape($brandAsset($organization['favicon_path'])) ?>" alt="Favicon atual"></span><?php else:?><small>Nenhuma imagem enviada ainda.</small><?php endif;?></label>
    <label class="field-full">Mensagem de boas-vindas<input maxlength="500" name="login_welcome_text" value="<?= $escape($organization['login_welcome_text']??'') ?>" placeholder="Use suas credenciais para continuar."></label>
    <label>E-mail de suporte<input type="email" maxlength="190" name="support_email" value="<?= $escape($organization['support_email']??'') ?>" placeholder="suporte@franquia.com.br"></label>
    <label>Telefone de suporte<input maxlength="30" name="support_phone" inputmode="tel" data-mask="phone" value="<?= $escape($organization['support_phone']??'') ?>" placeholder="(00) 00000-0000"></label>
   </div>
  </div>
  <div class="ava-strategy-card">
   <div class="ava-organization-title"><span><i class="fa-solid fa-route"></i></span><div><h3>Ambiente acadêmico da franquia</h3><p class="meta">Esta escolha define os Moodles administrados pela franquia.</p></div></div>
   <div class="organization-fields">
    <label>Estratégia acadêmica *<select required name="access_mode"><option value="shared" <?= $mode==='shared'?'selected':'' ?>>AVA Cursos compartilhado</option><option value="own" <?= $mode==='own'?'selected':'' ?>>Moodle exclusivo da franquia</option><option value="both" <?= $mode==='both'?'selected':'' ?>>AVA Cursos + Moodle exclusivo</option></select></label>
    <label>Ambiente principal *<select required name="primary_ava"><option value="shared" <?= $primary==='shared'?'selected':'' ?>>AVA Cursos compartilhado</option><option value="own" <?= $primary==='own'?'selected':'' ?>>Moodle exclusivo da franquia</option></select><small>Usado como padrão nos cursos próprios e nas novas matrículas.</small></label>
   </div>
   <div class="ava-environment-grid">
    <article class="ava-environment-card"><div class="ava-organization-title"><span><i class="fa-solid fa-earth-americas"></i></span><div><h3>AVA Cursos compartilhado</h3><p class="meta"><?= $escape((string)($shared['base_url']?:'https://avacursos.com.br')) ?></p></div><strong class="connection-badge <?= !empty($shared['configured'])&&!empty($shared['is_active'])?'connection-approved':'connection-pending' ?>"><?= !empty($shared['configured'])&&!empty($shared['is_active'])?'Disponível':'Configuração pendente' ?></strong></div><p class="meta"><i class="fa-solid fa-book-open"></i> <?= (int)($shared['mapped_courses']??0) ?> curso(s) nativo(s) sincronizado(s).</p><?php if(!empty($shared['configured'])):?><button class="button button-secondary" type="submit" formaction="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/ava/test/shared"><i class="fa-solid fa-plug-circle-check"></i> Testar AVA Cursos</button><?php else:?><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/platform/integrations/ava-cursos">Configurar integração central</a><?php endif;?></article>
    <details class="ava-exclusive-card" <?= in_array($mode,['own','both'],true)?'open':'' ?>><summary><span><i class="fa-solid fa-school"></i></span><div><h3>Moodle exclusivo da franquia</h3><p class="meta">Use apenas quando a franquia possuir uma instalação Moodle própria.</p></div><strong class="connection-badge <?= !empty($own['configured'])&&!empty($own['is_active'])?'connection-approved':'connection-pending' ?>"><?= !empty($own['configured'])&&!empty($own['is_active'])?'Ativo':'Opcional' ?></strong><i class="fa-solid fa-chevron-down ava-exclusive-chevron"></i></summary><div class="ava-exclusive-body"><div class="organization-fields"><label>Endereço HTTPS<input type="url" name="own_base_url" value="<?= $escape((string)($own['base_url']??'')) ?>" placeholder="https://ava.franquia.com.br"></label><label>Token do serviço web<input type="password" name="own_token" autocomplete="new-password" placeholder="<?= !empty($own['configured'])?'Deixe vazio para preservar o atual':'Cole o token gerado no Moodle' ?>"></label></div><label class="checkbox-row"><input type="checkbox" name="own_is_active" value="1" <?= !empty($own['is_active'])?'checked':'' ?>> Ativar Moodle exclusivo</label><?php if(!empty($own['configured'])):?><p class="meta"><i class="fa-solid fa-link"></i> <?= (int)($own['mapped_courses']??0) ?> curso(s) correspondente(s).</p><div class="d-flex gap-2 flex-wrap"><button class="button button-secondary" type="submit" formaction="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/ava/test/own"><i class="fa-solid fa-plug-circle-check"></i> Testar Moodle exclusivo</button><?php if(!empty($own['is_active'])):?><button class="button button-secondary" type="submit" formaction="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/ava/sync-courses"><i class="fa-solid fa-rotate"></i> Sincronizar cursos</button><?php endif;?></div><?php endif;?></div></details>
   </div>
  </div>
  <div class="organization-savebar ava-savebar"><p class="meta"><i class="fa-solid fa-shield-halved"></i> Tokens criptografados e acessíveis somente ao ADM Central.</p><button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar configuração AVA</button></div>
 </form>
</section>
<style>.organization-ava-form{display:grid;gap:1rem}.ava-communication-card,.ava-strategy-card{display:grid;gap:1rem;padding:1.2rem;border:1px solid #dce3e8;border-radius:1rem;background:#fff}.ava-organization-title{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}.ava-organization-title>span{display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:.75rem;background:#fff0f1;color:var(--inter-accent)}.ava-organization-title h3,.ava-organization-title p{margin:0}.ava-organization-title strong{margin-left:auto}.ava-environment-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.ava-environment-card,.ava-exclusive-card{margin:0;padding:1rem;border:1px solid #dce3e8;border-radius:.9rem;background:#f8fafb}.ava-environment-card{display:grid;align-content:start;gap:1rem}.ava-exclusive-card>summary{display:grid;grid-template-columns:auto 1fr auto auto;align-items:center;gap:.8rem;cursor:pointer;list-style:none}.ava-exclusive-card>summary::-webkit-details-marker{display:none}.ava-exclusive-card>summary>span{display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:.75rem;background:#fff0f1;color:var(--inter-accent)}.ava-exclusive-card h3,.ava-exclusive-card p{margin:0}.ava-exclusive-card[open] .ava-exclusive-chevron{transform:rotate(180deg)}.ava-exclusive-body{display:grid;gap:1rem;margin-top:1rem;padding-top:1rem;border-top:1px solid #dce3e8}.ava-url-copy{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.ava-url-copy code{flex:1;min-width:16rem;padding:.55rem .7rem;border:1px solid #dce3e8;border-radius:.55rem;background:#f8fafb;overflow-wrap:anywhere}.ava-brand-preview{display:inline-flex;margin-top:.5rem}.ava-brand-preview img{width:4.2rem;height:4.2rem;object-fit:contain;padding:.3rem;border:1px solid #dce3e8;border-radius:.6rem;background:#fff}.field-third small{display:block;margin-top:.35rem}@media(max-width:900px){.ava-environment-grid{grid-template-columns:1fr}}@media(max-width:600px){.ava-exclusive-card>summary{grid-template-columns:auto 1fr}.ava-exclusive-card>summary .connection-badge,.ava-exclusive-chevron{grid-column:auto}}</style>
<script>
document.querySelectorAll("[data-copy-target]").forEach(function (copyButton) {
  copyButton.addEventListener("click", function () {
    var input = document.querySelector("input[name=ava_access_url]");
    var code = document.querySelector("code[data-" + copyButton.dataset.copyTarget + "]");
    var text = (input && input.value.trim() !== "") ? input.value.trim() : (code ? (code.textContent || "") : "");
    if (text === "") return;
    function copied() {
      copyButton.innerHTML = "<i class=\"fa-solid fa-check\"></i> Copiado!";
      setTimeout(function () { copyButton.innerHTML = "<i class=\"fa-solid fa-copy\"></i> Copiar"; }, 2000);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(copied);
    } else {
      var temp = document.createElement("textarea");
      temp.value = text;
      document.body.appendChild(temp);
      temp.select();
      document.execCommand("copy");
      document.body.removeChild(temp);
      copied();
    }
  });
});
</script>
