<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use RuntimeException;

/**
 * Keeps the Moodle academic tree independent from the commercial categories
 * used by the storefront.
 */
final readonly class MoodleAcademicCategoryManager
{
    public function __construct(private MoodleClient $client) {}

    /** @return array{root:int,formation:int,individuals:int,trails:int,code:string} */
    public function ensureFormation(string $formation): array
    {
        $code=$this->formationCode($formation);
        $categories=$this->client->courseCategories();
        $root=$this->ensureCategory($categories,'MUNDO INTER','mi-mundo-inter',0);
        $formationCategory=$this->ensureCategory($categories,'Formação '.$code,'mi-formacao-'.strtolower($code),(int)$root['id']);
        $modulesIdNumber='mi-formacao-'.strtolower($code).'-modulos';
        $legacyIndividualsIdNumber='mi-formacao-'.strtolower($code).'-individuais';
        $individuals=$this->ensureCategory(
            $categories,
            'Módulos',
            $modulesIdNumber,
            (int)$formationCategory['id'],
            [$legacyIndividualsIdNumber]
        );
        $trails=$this->ensureCategory($categories,'Trilhas','mi-formacao-'.strtolower($code).'-trilhas',(int)$formationCategory['id']);
        return['root'=>(int)$root['id'],'formation'=>(int)$formationCategory['id'],'individuals'=>(int)$individuals['id'],'trails'=>(int)$trails['id'],'code'=>$code];
    }

    public function moveCourse(int $courseId,int $categoryId): void
    {
        $this->client->moveCourse($courseId,$categoryId);
    }

    /** @param list<array<string,mixed>> $categories @return array<string,mixed> */
    private function ensureCategory(array &$categories,string $name,string $idNumber,int $parent,array $legacyIdNumbers=[]): array
    {
        $category=$this->findCategory($categories,$idNumber);
        if($category===null){
            foreach($legacyIdNumbers as$legacyIdNumber){
                $category=$this->findCategory($categories,(string)$legacyIdNumber);
                if($category!==null)break;
            }
        }
        if($category===null){
            $category=$this->client->createCourseCategory($name,$idNumber,$parent);
            $categories[]=$category+['name'=>$name,'idnumber'=>$idNumber,'parent'=>$parent];
            return$category+['name'=>$name,'idnumber'=>$idNumber,'parent'=>$parent];
        }
        if(trim((string)($category['name']??''))!==$name||trim((string)($category['idnumber']??''))!==$idNumber||(int)($category['parent']??0)!==$parent){
            $this->client->updateCourseCategory((int)$category['id'],$name,$idNumber,$parent);
            $category['name']=$name;$category['idnumber']=$idNumber;$category['parent']=$parent;
        }
        return$category;
    }

    /** @param list<array<string,mixed>> $categories @return array<string,mixed>|null */
    private function findCategory(array $categories,string $idNumber): ?array
    {
        foreach($categories as$category)if(trim((string)($category['idnumber']??''))===$idNumber)return$category;
        return null;
    }

    private function formationCode(string $formation): string
    {
        $formation=trim((string)preg_replace('/^(?:Catálogo|Formação)\s+/iu','',$formation));
        $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$formation);
        $code=strtoupper(trim((string)preg_replace('/[^A-Z0-9]+/','-',strtoupper((string)($ascii===false?$formation:$ascii))),'-'));
        if($code===''||strlen($code)>40)throw new RuntimeException('A Formação não possui um código válido para organizar o AVA.');
        return$code;
    }
}
