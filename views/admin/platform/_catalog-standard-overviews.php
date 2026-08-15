<?php if($isInter):?>
<section class="catalog-subpanel catalog-overview-panel" data-catalog-subpanel="<?= $escape($provider) ?>:commercial" hidden>
 <div class="catalog-area-hero"><span class="catalog-icon"><i class="fa-solid fa-store"></i></span><div><span class="eyebrow">Acervo Comercial</span><h3>Vitrine da Formação INTER</h3><p>Visão consolidada dos títulos recebidos do AVA Cursos. A curadoria comercial e a disponibilidade para as franquias permanecem centralizadas.</p></div></div>
 <div class="catalog-summary wide-summary"><article class="catalog-stat"><small>Títulos sincronizados</small><strong><?= (int)($catalog['course_count']??0) ?></strong></article><article class="catalog-stat"><small>Franquias com acesso</small><strong><?= (int)($catalog['organization_count']??0) ?></strong></article></div>
</section>
<section class="catalog-subpanel catalog-overview-panel" data-catalog-subpanel="<?= $escape($provider) ?>:courses" hidden>
 <div class="catalog-area-hero"><span class="catalog-icon"><i class="fa-solid fa-graduation-cap"></i></span><div><span class="eyebrow">Cursos</span><h3>Cursos Individuais da Formação INTER</h3><p>Os cursos são administrados no AVA Cursos e reutilizados pelas franquias autorizadas, sem duplicar o conteúdo acadêmico.</p></div></div>
 <div class="catalog-actions"><a class="btn btn-primary" href="<?= $escape((string)($catalog['ava_url']?:'https://avacursos.com.br')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir AVA Cursos</a></div>
</section>
<?php endif;?>

<section class="catalog-subpanel catalog-overview-panel" data-catalog-subpanel="<?= $escape($provider) ?>:modules" hidden>
 <div class="catalog-area-hero"><span class="catalog-icon"><i class="fa-solid fa-cubes-stacked"></i></span><div><span class="eyebrow">Módulos</span><h3>Conteúdo interno dos Cursos</h3><p>Aulas, livros, avaliações e demais recursos ficam organizados dentro do Curso correspondente. Eles não são vendidos nem liberados separadamente nesta área.</p></div></div>
 <?php if($provider==='iesde'):?><div class="catalog-note"><i class="fa-solid fa-circle-info"></i><div><strong>Formação MASTER:</strong> abra um Curso na aba Cursos e use “Aulas e recursos” para revisar seus módulos oficiais.</div></div><?php elseif($isConted):?><div class="catalog-note"><i class="fa-solid fa-circle-info"></i><div><strong>Formação EXPERT:</strong> os componentes importados são relacionados ao curso de origem e preservam a rastreabilidade do fornecedor.</div></div><?php else:?><div class="catalog-note"><i class="fa-solid fa-circle-info"></i><div>Os módulos aparecerão aqui quando o conector deste fornecedor disponibilizar a estrutura interna dos cursos.</div></div><?php endif;?>
</section>

<section class="catalog-subpanel catalog-overview-panel" data-catalog-subpanel="<?= $escape($provider) ?>:trails" hidden>
 <div class="catalog-area-hero"><span class="catalog-icon"><i class="fa-solid fa-route"></i></span><div><span class="eyebrow">Trilhas</span><h3>Pacotes comerciais desta Formação</h3><p>Combine dois ou mais Cursos Individuais em uma única oferta, preservando a origem acadêmica de cada item.</p></div></div>
 <div class="catalog-actions"><a class="btn btn-primary" href="<?= $escape($basePath) ?>/admin/platform/catalog-trails?tab=trails<?= $provider==='iesde'?'&amp;catalog=iesde':'' ?>"><i class="fa-solid fa-route"></i> Gerenciar Trilhas</a></div>
</section>
