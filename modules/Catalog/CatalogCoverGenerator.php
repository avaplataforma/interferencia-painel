<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use Interferencia\Modules\Storage\SpacesStorageManager;
use RuntimeException;
use Throwable;

final readonly class CatalogCoverGenerator
{
    public function __construct(
        private ImageGenerationRepository $images,
        private CourseProviderRepository $catalogs,
        private CatalogMediaStorage $media,
        private SpacesStorageManager $spaces,
    ) {}

    public function queue(string $entityType,int $entityId,?string $prompt,?int $userId):int
    {
        if($this->catalogs->catalogEntity($entityType,$entityId)===null)throw new RuntimeException('Curso ou conteúdo não encontrado.');
        $settings=$this->images->settings();
        if(!(bool)$settings['configured']||!(bool)$settings['is_active'])throw new RuntimeException('Ative a integração de IA de imagens no ADM Central.');
        if(!$this->spaces->active())throw new RuntimeException('Ative a integração DigitalOcean Spaces antes de gerar capas.');
        return$this->images->queue($entityType,$entityId,$prompt,$userId);
    }

    public function queueAfterApproval(string $entityType,int $entityId,string $commercialCoverUrl,?int $userId):bool
    {
        $settings=$this->images->settings();
        if(!(bool)($settings['auto_generate_missing']??false)||!(bool)$settings['configured']||!(bool)$settings['is_active'])return false;
        if(trim($commercialCoverUrl)!==''||$this->catalogs->entityMediaAsset($entityType,$entityId)!==null)return false;
        try{$this->queue($entityType,$entityId,null,$userId);return true;}catch(Throwable){return false;}
    }

    /** @return array{processed:int,failed:int} */
    public function process(int $limit=1):array
    {
        $processed=0;$failed=0;$limit=max(1,min(20,$limit));
        for($i=0;$i<$limit;$i++){
            $job=$this->images->claimNext();if($job===null)break;
            try{$this->generate($job);$this->images->complete((int)$job['id']);$processed++;}
            catch(Throwable$e){$this->images->fail((int)$job['id'],$e->getMessage());$failed++;}
        }
        return['processed'=>$processed,'failed'=>$failed];
    }

    /** @param array<string,mixed> $job */
    private function generate(array $job):void
    {
        $entity=$this->catalogs->catalogEntity((string)$job['entity_type'],(int)$job['entity_id']);if($entity===null)throw new RuntimeException('O item da fila não existe mais.');
        $settings=$this->images->settings(true);if(!(bool)$settings['is_active'])throw new RuntimeException('A geração de imagens está desativada.');
        $prompt=$this->prompt($entity,(string)($job['prompt']??''),(string)$settings['style_prompt']);
        $result=(new OpenAiImageClient((string)$settings['api_key'],(string)$settings['model'],(string)$settings['quality'],(string)$settings['size']))->generate($prompt);
        $asset=$this->media->storeGenerated((string)$entity['catalog_code'],(string)$result['contents'],(string)$result['mime_type'],(string)$entity['name'],(int)($job['requested_by']??0)?:null,(string)$settings['model'],$prompt);
        $old=$this->catalogs->entityMediaAsset((string)$job['entity_type'],(int)$job['entity_id']);
        $this->catalogs->saveMediaAsset((string)$job['entity_type'],(int)$job['entity_id'],$asset,(int)($job['requested_by']??0)?:null);
        if($old!==null&&trim((string)($old['storage_path']??''))!==''&&$old['storage_path']!==$asset['storage_path'])$this->spaces->delete((string)$old['storage_path']);
    }

    /** @param array<string,mixed> $entity */
    private function prompt(array $entity,string $extra,string $style):string
    {
        $description=mb_substr(trim((string)($entity['description']??'')),0,800);
        $prompt="Crie uma capa horizontal 3:2 para a vitrine de um curso online chamado \"".(string)$entity['name']."\".";
        if(trim((string)($entity['category']??''))!=='')$prompt.=' Área: '.(string)$entity['category'].'.';
        if($description!=='')$prompt.=' Contexto: '.$description.'.';
        $prompt.=' A imagem deve comunicar claramente o tema por meio de uma cena ou objetos pertinentes. '.$style.' Não inclua palavras, letras, números, certificados, interfaces, marcas ou logotipos. Evite clichês genéricos de formatura e mantenha área de respiro para uso em card responsivo.';
        if(trim($extra)!=='')$prompt.=' Orientação adicional do curador: '.mb_substr(trim($extra),0,1000).'.';
        return$prompt;
    }
}
