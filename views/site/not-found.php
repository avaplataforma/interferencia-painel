<?php
$report = is_array($report ?? null) ? $report : ['host' => null, 'count' => 0, 'recent' => []];
$recent = is_array($report['recent'] ?? null) ? $report['recent'] : [];
?>
<style>
.not-found-page{width:100%;max-width:100rem;margin:0 auto}.not-found-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-bottom:1rem}.not-found-kpi{padding:1rem;border:1px solid #dbe3e8;border-radius:.9rem;background:#fff}.not-found-kpi span,.not-found-kpi strong{display:block}.not-found-kpi span{color:var(--inter-muted);font-size:.78rem}.not-found-kpi strong{font-size:1.15rem;overflow-wrap:anywhere}.not-found-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #dbe3e8;border-radius:.9rem;overflow:hidden}.not-found-table th,.not-found-table td{padding:.7rem .85rem;border-bottom:1px solid #eef2f5;text-align:left;font-size:.88rem;vertical-align:top}.not-found-table th{background:#f6f8fa;font-size:.76rem;text-transform:uppercase;letter-spacing:.04em}.not-found-table code{word-break:break-all;color:var(--inter-ink)}
</style>
<div class="not-found-page">
 <header class="page-header"><div><p class="eyebrow">ADM · Site Institucional</p><h1>Páginas não encontradas (404)</h1><p>Acessos a endereços que não existem no site. Útil para encontrar links quebrados.</p></div><div class="page-actions"><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/site"><i class="fa-solid fa-arrow-left"></i> Voltar</a></div></header>
 <?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
 <?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
 <?php if($report['host']===null):?>
  <div class="alert alert-warning">Nenhum domínio publicado configurado para este site. Os 404s serão contabilizados assim que o domínio estiver ativo.</div>
 <?php else:?>
  <div class="not-found-kpis">
   <div class="not-found-kpi"><span>Domínio</span><strong><?= $escape((string)$report['host']) ?></strong></div>
   <div class="not-found-kpi"><span>404s registrados</span><strong><?= (int)$report['count'] ?></strong></div>
   <div class="not-found-kpi"><span>Período</span><strong>Últimos registros</strong></div>
  </div>
  <?php if($recent===[]):?><p class="meta">Nenhum 404 registrado até agora.</p>
  <?php else:?>
   <table class="not-found-table"><thead><tr><th>Data</th><th>Caminho</th><th>Origem (referer)</th><th>IP</th></tr></thead><tbody>
   <?php foreach($recent as $row):?>
    <tr><td><?= $escape((string)substr((string)($row['created_at']??''),0,16)) ?></td><td><code><?= $escape((string)$row['path']) ?></code></td><td><?= $escape((string)($row['referer']??'')) ?></td><td><?= $escape((string)($row['ip']??'')) ?></td></tr>
   <?php endforeach;?>
   </tbody></table>
  <?php endif;?>
  <?php if($recent!==[]):?>
   <form method="post" action="<?= $escape($basePath) ?>/admin/site/not-found/clear" onsubmit="return confirm('Limpar o histórico de 404s deste domínio?')"><?= $csrfField ?? '' ?><p style="margin-top:1rem"><button class="button button-danger" type="submit"><i class="fa-solid fa-broom"></i> Limpar histórico</button></p></form>
  <?php endif;?>
 <?php endif;?>
</div>
