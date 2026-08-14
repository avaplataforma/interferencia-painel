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

    /** @return array{contents:string,mime_type:string,provider:string,prompt:string} */
    public function previewTrail(string$name,string$category,string$description,string$extra=''):array
    {
        $name=trim($name);if($name==='')throw new RuntimeException('Informe o nome da Trilha antes de gerar a capa.');
        $settings=$this->images->settings(true);
        if(!(bool)$settings['configured']||!(bool)$settings['is_active'])throw new RuntimeException('Ative a integração IA - OpenAI no ADM Central.');
        $prompt=$this->prompt(['name'=>$name,'category'=>trim($category),'description'=>trim($description)],$extra,(string)$settings['style_prompt']);
        return(new OpenAiImageClient((string)$settings['api_key'],(string)$settings['model'],(string)$settings['quality'],(string)$settings['size']))->generate($prompt);
    }

    /** @return array{contents:string,mime_type:string,provider:string,prompt:string} */
    public function previewCourse(int$entityId,string$description,string$extra=''):array
    {
        $entity=$this->catalogs->catalogEntity('course',$entityId);
        if($entity===null)throw new RuntimeException('Curso não encontrado para gerar a prévia.');
        $settings=$this->images->settings(true);
        if(!(bool)$settings['configured']||!(bool)$settings['is_active'])throw new RuntimeException('Ative a integração IA - OpenAI no ADM Central.');
        if(trim($description)!=='')$entity['description']=trim($description);
        $prompt=$this->prompt($entity,$extra,(string)$settings['style_prompt']);
        return(new OpenAiImageClient((string)$settings['api_key'],(string)$settings['model'],(string)$settings['quality'],(string)$settings['size']))->generate($prompt);
    }

    public function attachPreview(string$entityType,int$entityId,string$dataUrl,string$prompt,?int$userId):void
    {
        $entity=$this->catalogs->catalogEntity($entityType,$entityId);if($entity===null)throw new RuntimeException('Trilha não encontrada para salvar a capa.');
        if(!$this->spaces->active())throw new RuntimeException('Ative a integração DigitalOcean Spaces antes de salvar a capa.');
        if(!preg_match('#^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=]+)$#',$dataUrl,$match))throw new RuntimeException('A prévia da capa é inválida. Gere a imagem novamente.');
        $contents=base64_decode($match[2],true);
        if(!is_string($contents)||$contents===''||strlen($contents)>8*1024*1024)throw new RuntimeException('A prévia da capa está vazia ou excede 8 MB.');
        $settings=$this->images->settings();
        $asset=$this->media->storeGenerated((string)$entity['catalog_code'],$contents,$match[1],(string)$entity['name'],$userId,(string)($settings['model']??'OpenAI'),mb_substr(trim($prompt),0,4000));
        $old=$this->catalogs->entityMediaAsset($entityType,$entityId);
        $this->catalogs->saveMediaAsset($entityType,$entityId,$asset,$userId);
        if($old!==null&&trim((string)($old['storage_path']??''))!==''&&$old['storage_path']!==$asset['storage_path'])$this->spaces->delete((string)$old['storage_path']);
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
