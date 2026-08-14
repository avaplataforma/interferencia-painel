<?php $courseCurationIsEmbedded = (bool) ($courseCurationEmbedded ?? false); ?>
<?php if (!$courseCurationIsEmbedded): ?><tr class="course-curation-row content-curation-row" id="<?= $escape($editorId) ?>" hidden><td colspan="6"><?php endif; ?>
<div class="course-curation-shell <?= $provider === 'iesde' ? 'is-master-curation' : '' ?>">
 <form class="course-curation-form content-curation-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers/courses/<?= (int) $course['id'] ?>/review" <?= $provider === 'iesde' ? 'data-master-course-curation-form' : '' ?>>
  <?= $csrfField ?><input type="hidden" name="provider" value="<?= $escape($provider) ?>">
  <?php if ($provider === 'iesde'): ?><input type="hidden" name="ai_cover_preview" value="" data-master-ai-cover-data><input type="hidden" name="ai_cover_prompt" value="" data-master-ai-cover-prompt><?php endif; ?>
  <header>
   <div><span class="eyebrow">Curadoria comercial</span><h3><?= $escape((string) ($course['effective_name'] ?? $course['name'])) ?></h3><p>Edite a apresentação comercial sem alterar os dados recebidos do fornecedor.</p></div>
   <button class="action-icon" type="button" data-course-curation-close="<?= $escape($editorId) ?>" title="Fechar curadoria"><i class="fa-solid fa-xmark"></i></button>
  </header>
  <div class="catalog-source-data">
   <span>Categoria original<strong><?= $escape((string) ($course['category'] ?: '—')) ?></strong></span>
   <span>Carga original<strong><?= $escape((string) ($course['workload'] ?: '—')) ?></strong></span>
   <span>Certificado original<strong><?= $escape((string) ($course['certificate'] ?: '—')) ?></strong></span>
   <span>ID externo<strong><?= $escape((string) ($course['remote_id'] ?: $course['external_key'])) ?></strong></span>
  </div>
  <div class="content-curation-grid course-curation-grid">
   <label>Situação<select name="review_status"><?php foreach ($reviewLabels as $key => $label): ?><option value="<?= $key ?>" <?= $review === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
   <label>Liberação comercial<select name="release_status"><?php foreach ($releaseLabels as $key => $label): ?><option value="<?= $key ?>" <?= $release === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
   <label class="span-2">Nome comercial<input name="commercial_name" maxlength="500" value="<?= $escape((string) ($course['commercial_name'] ?: $course['name'])) ?>"></label>
   <label>Categoria comercial<input name="commercial_category" maxlength="255" value="<?= $escape((string) ($course['commercial_category'] ?? '')) ?>" placeholder="<?= $escape((string) ($course['category'] ?? '')) ?>"></label>
   <label>Carga horária comercial<input name="commercial_workload" maxlength="100" value="<?= $escape((string) ($course['commercial_workload'] ?? '')) ?>" placeholder="<?= $escape((string) ($course['workload'] ?? '')) ?>"></label>
   <label class="span-2">Certificado<input name="commercial_certificate" maxlength="190" value="<?= $escape((string) ($course['commercial_certificate'] ?? '')) ?>" placeholder="<?= $escape((string) ($course['certificate'] ?? '')) ?>"></label>
   <label class="span-2">Imagem comercial por URL<input type="url" name="commercial_cover_url" maxlength="1000" value="<?= $escape((string) ($course['commercial_cover_url'] ?? '')) ?>" placeholder="<?= $escape((string) ($course['cover_url'] ?? '')) ?>"></label>
   <label class="span-2 catalog-image-upload">Capa otimizada no Spaces<input type="file" name="commercial_cover_file" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG ou WebP, até 8 MB. O arquivo será reduzido e convertido para WebP.</small><?php if (!empty($course['media_asset_id'])): ?><span class="catalog-image-current"><img src="<?= $escape($basePath) ?>/catalog-media/<?= (int) $course['media_asset_id'] ?>" alt="Capa atual"><span>Capa própria armazenada no Spaces</span></span><span class="checkbox-row"><input type="checkbox" name="remove_cover" value="1"> Remover capa própria</span><?php endif; ?></label>
   <label class="span-2">Resumo comercial<input name="commercial_summary" maxlength="1000" value="<?= $escape((string) ($course['commercial_summary'] ?? '')) ?>" placeholder="Uma apresentação curta para a vitrine e os cards."></label>
   <label class="span-2">Descrição comercial<textarea name="commercial_description"><?= $escape((string) ($course['commercial_description'] ?: $course['description'])) ?></textarea></label>
   <label class="span-2">Observações internas<textarea name="review_notes"><?= $escape((string) ($course['review_notes'] ?? '')) ?></textarea></label>
  </div>
  <footer><span><i class="fa-solid fa-shield-halved"></i> Aprovar não publica sozinho. Escolha também a liberação comercial desejada.</span><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar curadoria</button></footer>
 </form>

 <aside class="course-curation-aside">
  <?php if ($provider === 'iesde'): ?>
   <section class="master-pilot-ai master-ai-assistant">
    <div><span class="eyebrow">Assistente de curadoria</span><h3>Resumo, descrição e capa</h3><p>Um único comando prepara a apresentação comercial. Os resultados aparecem nos campos ao lado para sua revisão antes de salvar.</p></div>
    <img class="course-curation-preview" src="<?= $escape($cover) ?>" alt="Prévia da capa comercial" loading="lazy" data-master-ai-cover-image <?= $cover === '' ? 'hidden' : '' ?>>
    <div class="course-curation-placeholder" data-master-ai-cover-placeholder <?= $cover !== '' ? 'hidden' : '' ?>><i class="fa-solid fa-image"></i><span>Nenhuma capa definida</span></div>
    <form class="master-ai-form <?= $imageAiReady ? '' : 'is-disabled' ?>" method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers/courses/<?= (int) $course['id'] ?>/prepare-master-pilot" data-master-ai-preview-form>
     <?= $csrfField ?>
     <label>Orientação para os textos <span>opcional</span><input name="guidance" maxlength="800" placeholder="Ex.: linguagem objetiva e foco nos conceitos centrais"></label>
     <label>Orientação para a imagem <span>opcional</span><input name="cover_guidance" maxlength="500" placeholder="Ex.: ambiente educacional contemporâneo"></label>
     <button class="btn btn-primary" type="submit" <?= $imageAiReady ? '' : 'disabled' ?>><i class="fa-solid fa-wand-magic-sparkles"></i> Gerar resumo, descrição e capa</button>
     <small><?= $imageAiReady ? 'Nada será salvo agora. Revise os campos e use Salvar curadoria quando aprovar. A IA não cria nem altera questões.' : 'Ative a integração IA - OpenAI no ADM Central.' ?></small>
     <div class="master-ai-feedback" data-master-ai-feedback hidden></div>
    </form>
   </section>
   <?php $providerAssessmentReady = (int) ($course['assessment_resource_count'] ?? 0) > 0; $providerBookReady = (int) ($course['book_resource_count'] ?? 0) > 0; ?>
   <section class="master-pilot-ai official-resources-card">
    <div><span class="eyebrow">Recursos acadêmicos oficiais</span><h3>Apostila e avaliação oficial</h3><p><?= $providerBookReady && $providerAssessmentReady ? 'A apostila e a avaliação oficial estão vinculadas a este Curso Individual.' : 'Sincronize novamente a seleção LTI quando algum recurso oficial estiver pendente.' ?></p></div>
    <div class="status-pill-stack">
     <span class="status-pill <?= $providerBookReady ? 'is-ready' : 'is-draft' ?>"><i class="fa-solid <?= $providerBookReady ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i> <?= $providerBookReady ? 'Apostila pronta' : 'Apostila pendente' ?></span>
     <span class="status-pill <?= $providerAssessmentReady ? 'is-ready' : 'is-draft' ?>"><i class="fa-solid <?= $providerAssessmentReady ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i> <?= $providerAssessmentReady ? 'Banco oficial vinculado' : 'Avaliação pendente' ?></span>
    </div>
   </section>
  <?php else: ?>
   <div><span class="eyebrow">Capa inteligente</span><h3>Gerar imagem com IA</h3><p>Crie uma capa contextual e leve, pronta para a vitrine.</p></div>
   <?php if ($cover !== ''): ?><img class="course-curation-preview" src="<?= $escape($cover) ?>" alt="Capa comercial atual" loading="lazy"><?php else: ?><div class="course-curation-placeholder"><i class="fa-solid fa-image"></i><span>Nenhuma capa definida</span></div><?php endif; ?>
   <form class="catalog-ai-form <?= $imageAiReady ? '' : 'is-disabled' ?>" method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers/courses/<?= (int) $course['id'] ?>/generate-cover"><?= $csrfField ?><input type="hidden" name="provider" value="<?= $escape($provider) ?>"><label>Orientação opcional para a IA<input name="prompt" maxlength="500" placeholder="Ex.: ambiente profissional, pessoas diversas"></label><button class="btn btn-secondary" type="submit" <?= $imageAiReady ? '' : 'disabled' ?>><i class="fa-solid fa-wand-magic-sparkles"></i> Gerar capa com IA</button><small><?= $imageAiReady ? 'A tarefa entra na fila e a capa final será otimizada no Spaces.' : 'Ative a integração de imagens no ADM Central.' ?></small></form>
  <?php endif; ?>
 </aside>
</div>
<?php if (!$courseCurationIsEmbedded): ?></td></tr><?php endif; ?>
