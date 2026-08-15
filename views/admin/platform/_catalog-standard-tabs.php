<?php
$standardCommercialCount = $provider === 'iesde' ? (int)($commercialData['total'] ?? 0) : (int)($catalog['course_count'] ?? count($catalogCourses));
$standardCourseCount = $provider === 'iesde' || $isInter ? count($catalogCourses) : (int)($catalog['content_count'] ?? 0);
$standardModuleCount = $provider === 'iesde' ? (int)($catalog['content_count'] ?? 0) : 0;
?>
<nav class="catalog-subtabs catalog-standard-tabs" role="tablist" aria-label="Áreas da Formação">
 <button class="catalog-subtab is-active" type="button" data-catalog-subtab="commercial" data-provider="<?= $escape($provider) ?>"><i class="fa-solid fa-store"></i> Acervo Comercial <span><?= $standardCommercialCount ?></span></button>
 <button class="catalog-subtab" type="button" data-catalog-subtab="courses" data-provider="<?= $escape($provider) ?>"><i class="fa-solid fa-graduation-cap"></i> Cursos <span><?= $standardCourseCount ?></span></button>
 <button class="catalog-subtab" type="button" data-catalog-subtab="modules" data-provider="<?= $escape($provider) ?>"><i class="fa-solid fa-cubes-stacked"></i> Módulos <span><?= $standardModuleCount ?></span></button>
 <button class="catalog-subtab" type="button" data-catalog-subtab="trails" data-provider="<?= $escape($provider) ?>"><i class="fa-solid fa-route"></i> Trilhas</button>
 <button class="catalog-subtab" type="button" data-catalog-subtab="policy" data-provider="<?= $escape($provider) ?>"><i class="fa-solid fa-tags"></i> Política Comercial</button>
 <button class="catalog-subtab" type="button" data-catalog-subtab="connection" data-provider="<?= $escape($provider) ?>"><i class="fa-solid fa-plug-circle-bolt"></i> Conexão e API</button>
</nav>
