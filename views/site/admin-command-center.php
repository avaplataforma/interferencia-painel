<?php
$metricCards=[
    ['fa-eye','Visitas',$analytics['page_view']??0],
    ['fa-book-open','Cursos vistos',$analytics['course_view']??0],
    ['fa-arrow-pointer','Cliques em cursos',$analytics['course_click']??0],
    ['fa-brands fa-whatsapp','WhatsApp',$analytics['whatsapp_click']??0],
    ['fa-address-card','Contatos',$analytics['contact_submit']??0],
    ['fa-graduation-cap','Bolsas',$analytics['scholarship_submit']??0],
];
$publicationLabels=['draft'=>'Rascunho','scheduled'=>'Agendada','published'=>'Publicada','archived'=>'Arquivada'];
?>
<section class="site-command" aria-label="Centro de comando do site">
 <div class="site-kpis">
  <?php foreach($metricCards as$metric):?><article class="site-kpi"><i class="<?= str_starts_with($metric[0],'fa-brands')?$escape($metric[0]):'fa-solid '.$escape($metric[0]) ?>"></i><div><span><?= $escape($metric[1]) ?> · 30 dias</span><strong><?= number_format((int)$metric[2],0,',','.') ?></strong></div></article><?php endforeach;?>
 </div>
 <div class="site-ops-grid">
  <article class="site-ops-card">
   <header class="site-ops-head"><div><p class="eyebrow">Publicação</p><h2>Rascunho e site no ar</h2><p class="meta">Pré-visualize antes de publicar ou restaure uma versão anterior.</p></div><span class="badge <?= !empty($settings['live_version'])?'badge-success':'badge-warning' ?>"><?= !empty($settings['live_version'])?'Versão '.$escape((string)$settings['live_version']).' no ar':'Ainda não publicado' ?></span></header>
   <div class="site-publish-line"><div><span>Editor</span><strong>Rascunho atual</strong></div><div><span>Publicado</span><strong><?= !empty($settings['published_at'])?date('d/m/Y H:i',strtotime((string)$settings['published_at'])):'—' ?></strong></div><div><span>Agendamento</span><strong><?= !empty($settings['scheduled_publish_at'])?date('d/m/Y H:i',strtotime((string)$settings['scheduled_publish_at'])):'Nenhum' ?></strong></div></div>
   <div class="site-ops-actions" style="margin-top:1rem"><a class="button button-secondary" target="_blank" href="<?= $escape($publicUrl) ?>?preview=1&device=desktop"><i class="fa-solid fa-desktop"></i> Ver no computador</a><a class="button button-secondary" target="_blank" href="<?= $escape($publicUrl) ?>?preview=1&device=mobile"><i class="fa-solid fa-mobile-screen"></i> Ver no celular</a><?php if(!empty($settings['scheduled_publish_at'])):?><form method="post" action="<?= $escape($basePath) ?>/admin/site/schedule/cancel"><?= $csrfField ?><button class="button button-danger" type="submit"><i class="fa-solid fa-ban"></i> Cancelar agendamento</button></form><?php endif;?></div>
   <?php if($versions!==[]):?><details class="site-details"><summary><span class="site-summary"><i class="fa-solid fa-clock-rotate-left"></i><span>Histórico de versões</span></span><i class="fa-solid fa-chevron-down"></i></summary><div class="site-detail-form site-version-list"><?php foreach($versions as$version):?><div class="site-version-row"><span class="badge"><?= (int)$version['version_number'] ?></span><p><strong><?= $escape($publicationLabels[$version['status']]??$version['status']) ?></strong><small><?= $escape($version['label']??'Versão do site') ?> · <?= date('d/m/Y H:i',strtotime((string)$version['created_at'])) ?><?= !empty($version['created_by_name'])?' · '.$escape($version['created_by_name']):'' ?></small></p><?php if($version['status']==='published'):?><span class="badge badge-success">No ar</span><?php else:?><form method="post" action="<?= $escape($basePath) ?>/admin/site/versions/<?= (int)$version['id'] ?>/publish" onsubmit="return confirm('Publicar esta versão no site?')"><?= $csrfField ?><button class="button button-secondary" type="submit"><i class="fa-solid fa-rotate-left"></i> Restaurar</button></form><?php endif;?></div><?php endforeach;?></div></details><?php endif;?>
  </article>
  <article class="site-ops-card">
   <header class="site-ops-head"><div><p class="eyebrow">Domínio</p><h2>Saúde do endereço público</h2><p class="meta">Diagnóstico do DNS e do certificado de segurança.</p></div><form method="post" action="<?= $escape($basePath) ?>/admin/site/domain/check"><?= $csrfField ?><button class="button button-secondary" type="submit"><i class="fa-solid fa-stethoscope"></i> Verificar</button></form></header>
   <div class="site-health"><div><span>Domínio</span><strong><?= $escape($domainStatus['host']??'Não configurado') ?></strong></div><div><span>DNS</span><strong><?= (int)($domainStatus['dns_ok']??0)===1?'Conectado':'Pendente' ?></strong></div><div><span>HTTPS / SSL</span><strong><?= (int)($domainStatus['ssl_ok']??0)===1?'Protegido':'Pendente' ?></strong></div></div>
   <?php if(!empty($domainStatus['error_message'])):?><p class="alert alert-warning" style="margin:1rem 0 0"><?= $escape($domainStatus['error_message']) ?></p><?php elseif(!empty($domainStatus['checked_at'])):?><p class="alert alert-success" style="margin:1rem 0 0">Domínio verificado em <?= date('d/m/Y H:i',strtotime((string)$domainStatus['checked_at'])) ?>.</p><?php endif;?>
  </article>
 </div>
</section>
