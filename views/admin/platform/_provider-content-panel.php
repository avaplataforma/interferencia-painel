<div class="catalog-subpanel" data-catalog-subpanel="<?= $escape($provider) ?>:contents" hidden>
 <div class="catalog-note" style="margin:0 0 1rem"><i class="fa-solid fa-diagram-project"></i><div><strong>Venda cada disciplina, unidade ou objeto como um produto independente.</strong><br>Esta estrutura é comum a todos os fornecedores. Quando faltarem imagem, descrição, categoria ou carga horária, o conteúdo herda os dados do curso-pai sem criar cópias desnecessárias.</div></div>
 <div class="content-toolbar">
  <form method="get" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers">
   <input type="hidden" name="catalog" value="<?= $escape($provider) ?>">
   <input type="hidden" name="section" value="contents">
   <label>Localizar conteúdo, disciplina, curso ou código<input name="content_q" value="<?= $escape($contentQuery) ?>" placeholder="Ex.: Atendimento ao cliente"></label>
   <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Pesquisar</button>
   <?php if($contentQuery!==''):?><a class="btn btn-secondary" href="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers?catalog=<?= $escape($provider) ?>&amp;section=contents">Limpar</a><?php endif;?>
  </form>
  <p><strong><?= (int)($contentPage['total']??0) ?></strong> conteúdo(s)</p>
 </div>
 <?php if($contentRows===[]):?>
  <div class="catalog-empty"><i class="fa-solid fa-puzzle-piece fa-2x"></i><h3>Nenhum conteúdo individual sincronizado</h3><p>Use “Sincronizar cursos” na aba Conexão e API. A hierarquia dos cursos será importada automaticamente.</p></div>
 <?php else:?>
  <div class="table-responsive"><table><thead><tr><th>Conteúdo individual</th><th>Origem</th><th>Curadoria</th><th>Venda</th><th>Ações</th></tr></thead><tbody>
  <?php foreach($contentRows as$content):
   $contentReview=(string)($content['review_status']??'imported');
   $contentRelease=(string)($content['release_status']??'private');
   $courseNames=array_values(array_filter(explode('||',(string)($content['course_names']??''))));
  ?>
   <tr>
    <td><div class="catalog-course"><?php $contentCover=!empty($content['media_asset_id'])?$basePath.'/catalog-media/'.(int)$content['media_asset_id']:(string)($content['effective_cover_url']??'');if($contentCover!==''):?><img class="catalog-cover" src="<?= $escape($contentCover) ?>" alt="" loading="lazy"><?php else:?><span class="catalog-cover catalog-icon"><i class="fa-solid fa-play"></i></span><?php endif;?><div><strong><?= $escape((string)($content['effective_name']??$content['name'])) ?></strong><small><?= $escape((string)($content['content_type']??'unit')) ?> · código <?= $escape((string)$content['external_key']) ?></small><div class="catalog-course-state"><span class="catalog-badge <?= (int)$content['is_available']===1?'ok':'' ?>"><?= (int)$content['is_available']===1?'Disponível':'Retirado' ?></span><?php if((string)($content['sync_state']??'')==='changed'):?><span class="catalog-badge changed">Alterado</span><?php endif;?><?php if(($content['media_inheritance']??'')==='inherited'&&$contentCover!==''):?><span class="catalog-badge private">Capa herdada</span><?php endif;?></div></div></div></td>
    <td><div class="content-origin"><strong><?= $escape((string)($content['discipline_name']?:'Disciplina não informada')) ?></strong><small><?= $content['semester_number']!==null?'Semestre '.(int)$content['semester_number'].' · ':'' ?><?= (int)$content['course_count'] ?> curso(s) relacionado(s)</small><?php if($courseNames!==[]):?><small title="<?= $escape(implode(' · ',$courseNames)) ?>"><?= $escape(implode(' · ',array_slice($courseNames,0,2))) ?><?= count($courseNames)>2?' e mais '.(count($courseNames)-2):'' ?></small><?php endif;?></div></td>
    <td><div class="catalog-course-state"><span class="catalog-badge <?= $contentReview==='approved'?'ok':'' ?>"><?= $escape($reviewLabels[$contentReview]??'Importado') ?></span><span class="catalog-badge <?= $contentRelease==='private'?'private':'ok' ?>"><?= $escape($releaseLabels[$contentRelease]??'Somente ADM Central') ?></span></div></td>
    <td><strong><?= (int)($content['offer_count']??0) ?> franquia(s)</strong><small>Venda assistida com acesso exato a este conteúdo.</small></td>
    <td><div class="content-actions">
     <details><summary><i class="fa-solid fa-pen-to-square"></i> Curadoria</summary>
      <form class="review-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers/contents/<?= (int)$content['id'] ?>/review"><?= $csrfField ?><input type="hidden" name="provider" value="<?= $escape($provider) ?>">
       <div class="review-form-grid">
        <label>Situação<select name="review_status"><?php foreach($reviewLabels as$key=>$label):?><option value="<?= $key ?>" <?= $contentReview===$key?'selected':'' ?>><?= $label ?></option><?php endforeach;?></select></label>
        <label>Liberação<select name="release_status"><?php foreach($releaseLabels as$key=>$label):?><option value="<?= $key ?>" <?= $contentRelease===$key?'selected':'' ?>><?= $label ?></option><?php endforeach;?></select></label>
        <label class="wide">Nome comercial<input name="commercial_name" maxlength="500" value="<?= $escape((string)($content['commercial_name']?:$content['name'])) ?>"></label>
        <label>Categoria<input name="commercial_category" maxlength="255" value="<?= $escape((string)($content['commercial_category']??'')) ?>" placeholder="<?= $escape((string)($content['effective_category']??$content['discipline_name']??'')) ?>"></label>
        <label>Carga horária<input name="commercial_workload" maxlength="100" value="<?= $escape((string)($content['commercial_workload']??'')) ?>" placeholder="<?= $escape((string)($content['effective_workload']??'')) ?>"></label>
        <label class="wide">Imagem comercial (URL)<input type="url" name="commercial_cover_url" value="<?= $escape((string)($content['commercial_cover_url']??'')) ?>"></label>
        <label class="wide catalog-image-upload">Capa otimizada no Spaces<input type="file" name="commercial_cover_file" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG ou WebP. A imagem será reduzida para até 1280 px e convertida para WebP quando possível.</small><?php if(!empty($content['media_asset_id'])):?><span class="catalog-image-current"><img src="<?= $escape($basePath) ?>/catalog-media/<?= (int)$content['media_asset_id'] ?>" alt="Capa atual"><span><?= ($content['media_inheritance']??'')==='own'?'Capa própria':'Capa herdada do curso-pai' ?></span></span><?php if(($content['media_inheritance']??'')==='own'):?><span class="checkbox-row"><input type="checkbox" name="remove_cover" value="1"> Remover capa própria</span><?php endif;?><?php endif;?></label>
        <label class="wide">Descrição comercial<textarea name="commercial_description"><?= $escape((string)($content['commercial_description']??'')) ?></textarea></label>
        <label class="wide">Observações internas<textarea name="review_notes"><?= $escape((string)($content['review_notes']??'')) ?></textarea></label>
       </div>
       <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> Salvar curadoria</button>
      </form>
     </details>
     <details><summary><i class="fa-solid fa-store"></i> Vender</summary>
      <form class="content-offer-form" method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers/content-offers"><?= $csrfField ?><input type="hidden" name="provider" value="<?= $escape($provider) ?>"><input type="hidden" name="content_id" value="<?= (int)$content['id'] ?>">
       <label class="wide">Franquia<select required name="organization_id"><option value="">Selecione</option><?php foreach($organizationRows as$organization):?><option value="<?= (int)$organization['id'] ?>"><?= $escape((string)($organization['display_name']?:$organization['legal_name'])) ?></option><?php endforeach;?></select></label>
       <label class="wide">Nome na loja<input name="commercial_name" maxlength="500" value="<?= $escape((string)($content['effective_name']??$content['name'])) ?>"></label>
       <label class="wide">Descrição<textarea name="commercial_description"><?= $escape((string)($content['effective_description']??'')) ?></textarea></label>
       <label>Preço (R$)<input required inputmode="decimal" name="price" value="0,00"></label>
       <label>Máximo de parcelas<input required type="number" min="1" max="60" name="max_installments" value="1"></label>
       <label class="wide"><span class="checkbox-row"><input type="checkbox" name="is_active" value="1" checked> Oferta ativa</span></label>
       <label class="wide"><span class="checkbox-row"><input type="checkbox" name="is_visible" value="1"> Exibir no site da franquia</span></label>
       <button class="btn btn-primary" type="submit"><i class="fa-solid fa-store"></i> Salvar oferta</button>
      </form>
     </details>
    </div></td>
   </tr>
  <?php endforeach;?>
  </tbody></table></div>
  <?php if((int)($contentPage['pages']??1)>1):
   $currentPage=(int)$contentPage['page'];
   $lastPage=(int)$contentPage['pages'];
   $visiblePages=array_values(array_unique(array_filter([1,$currentPage-2,$currentPage-1,$currentPage,$currentPage+1,$currentPage+2,$lastPage],static fn(int$page):bool=>$page>=1&&$page<=$lastPage)));
   sort($visiblePages);
  ?><nav class="content-pagination" aria-label="Paginação de conteúdos"><?php $previousPage=0;foreach($visiblePages as$pageNumber):if($previousPage>0&&$pageNumber>$previousPage+1):?><span aria-hidden="true">…</span><?php endif;$pageUrl=$basePath.'/admin/platform/integrations/course-providers?catalog='.$provider.'&section=contents&content_page='.$pageNumber.($contentQuery!==''?'&content_q='.rawurlencode($contentQuery):'');?><a class="<?= $pageNumber===$currentPage?'is-current':'' ?>" href="<?= $escape($pageUrl) ?>"><?= $pageNumber ?></a><?php $previousPage=$pageNumber;endforeach;?></nav><?php endif;?>
 <?php endif;?>
</div>
