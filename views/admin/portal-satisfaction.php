<?php
$summary=$summary??['avg'=>0.0,'count'=>0,'stars'=>[1=>0,2=>0,3=>0,4=>0,5=>0]];$responses=$responses??[];
?>
<style>
.satisfaction-page{max-width:82rem;margin:auto}.satisfaction-summary{display:grid;grid-template-columns:minmax(14rem,260px) minmax(0,1fr);gap:1.4rem;margin-bottom:1.4rem}.satisfaction-score{padding:1.5rem;border-radius:1rem;background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:#fff;display:grid;place-items:center;text-align:center}.satisfaction-score strong{font-size:2.6rem;line-height:1}.satisfaction-score small{opacity:.9;margin-top:.4rem}.satisfaction-bars{padding:1.4rem;border-radius:1rem;display:grid;align-content:center;gap:.5rem}.satisfaction-bar{display:grid;grid-template-columns:6.5rem 1fr 3rem;gap:.7rem;align-items:center;font-size:.88rem}.satisfaction-track{height:.65rem;border-radius:999px;background:#eef2f5;overflow:hidden}.satisfaction-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#f59e0b,#8b5cf6)}.responses-list{display:grid;gap:.8rem}.response-card{border:1px solid #e2e8ee;border-radius:1rem;padding:1rem 1.15rem;background:#fff}.response-head{display:flex;align-items:center;justify-content:space-between;gap:.8rem}.response-stars{color:#f59e0b;letter-spacing:.12rem}.response-card p{margin:.5rem 0 0;color:var(--inter-muted)}.response-card small{display:block;margin-top:.45rem;color:var(--inter-muted)}
</style>
<div class="satisfaction-page">
 <div class="page-header"><div><p class="eyebrow">ADM · Portal do Aluno · Satisfação</p><h1>Satisfação dos alunos</h1><p>Avaliações enviadas pelos estudantes direto do Portal do Aluno.</p></div></div>
 <div class="satisfaction-summary">
  <section class="satisfaction-score"><div><strong><?= number_format((float)$summary['avg'],1,',','.') ?></strong><small>média de <?= (int)$summary['count'] ?> avaliação(ões)</small></div></section>
  <section class="card satisfaction-bars">
   <?php for($star=5;$star>=1;$star--): $count=(int)($summary['stars'][$star]??0); $percent=$summary['count']>0?round($count/$summary['count']*100):0; ?>
   <div class="satisfaction-bar"><span><?= $star ?> <?= $star===1?'estrela':'estrelas' ?></span><div class="satisfaction-track"><div class="satisfaction-fill" style="width:<?= $percent ?>%"></div></div><span><?= $count ?></span></div>
   <?php endfor; ?>
  </section>
 </div>
 <section class="card satisfaction-page-list" style="padding:1.4rem;border-radius:1rem">
  <div class="card-header"><div><h2>Respostas recentes</h2><p class="meta"><?= count($responses) ?> resposta(s).</p></div></div>
  <div class="responses-list">
   <?php foreach($responses as$item):?>
   <article class="response-card">
    <div class="response-head"><span class="response-stars"><?= str_repeat('★',(int)$item['rating']).str_repeat('☆',5-(int)$item['rating']) ?></span><strong><?= $escape((string)$item['student_name']) ?></strong></div>
    <?php if(!empty($item['comment'])):?><p><?= $escape((string)$item['comment']) ?></p><?php endif;?>
    <small><?= $escape(substr((string)$item['created_at'],0,16)) ?></small>
   </article>
   <?php endforeach;?>
   <?php if($responses===[]):?><p class="meta">Nenhuma avaliação recebida ainda.</p><?php endif;?>
  </div>
 </section>
</div>
