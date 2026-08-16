<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

/**
 * Applies the official Mundo Inter spelling for public academic titles.
 *
 * Important words start with an uppercase letter, while Portuguese articles,
 * conjunctions and prepositions stay lowercase. Known academic and platform
 * acronyms are preserved.
 */
final class PortugueseCourseTitle
{
    /** @var array<string,true> */
    private const LOWERCASE_WORDS = [
        'a'=>true,'as'=>true,'ao'=>true,'aos'=>true,'à'=>true,'às'=>true,
        'com'=>true,'contra'=>true,'da'=>true,'das'=>true,'de'=>true,'do'=>true,'dos'=>true,
        'e'=>true,'em'=>true,'entre'=>true,'na'=>true,'nas'=>true,'no'=>true,'nos'=>true,
        'o'=>true,'os'=>true,'ou'=>true,'para'=>true,'pela'=>true,'pelas'=>true,'pelo'=>true,'pelos'=>true,
        'por'=>true,'sem'=>true,'sob'=>true,'sobre'=>true,'um'=>true,'uma'=>true,'uns'=>true,'umas'=>true,
    ];

    /** @var array<string,string> */
    private const ACRONYMS = [
        'adm'=>'ADM','ava'=>'AVA','bncc'=>'BNCC','cnh'=>'CNH','crm'=>'CRM','eja'=>'EJA',
        'enem'=>'ENEM','ia'=>'IA','lgpd'=>'LGPD','lti'=>'LTI','mba'=>'MBA','mec'=>'MEC',
        'pdf'=>'PDF','rh'=>'RH','ti'=>'TI','tti'=>'TTI',
    ];

    public static function format(string $title): string
    {
        $title=trim((string)preg_replace('/\s+/u',' ',$title));
        if($title==='')return'';

        $words=preg_split('/\s+/u',mb_strtolower($title,'UTF-8'))?:[];
        foreach($words as$index=>$word){
            $words[$index]=self::formatWord($word,$index===0);
        }
        return implode(' ',$words);
    }

    private static function formatWord(string $word,bool $first):string
    {
        if($word==='')return'';
        if(!preg_match('/^([^\p{L}\p{N}]*)([\p{L}\p{N}][\p{L}\p{N}\-\/]*)([^\p{L}\p{N}]*)$/u',$word,$matches)){
            return$word;
        }
        $prefix=$matches[1];$core=$matches[2];$suffix=$matches[3];
        $lookup=mb_strtolower($core,'UTF-8');
        if(isset(self::ACRONYMS[$lookup]))return$prefix.self::ACRONYMS[$lookup].$suffix;
        if(preg_match('/^[ivxlcdm]+$/i',$core)===1)return$prefix.mb_strtoupper($core,'UTF-8').$suffix;
        if(!$first&&isset(self::LOWERCASE_WORDS[$lookup]))return$prefix.$lookup.$suffix;

        $parts=preg_split('/([\-\/])/u',$lookup,-1,PREG_SPLIT_DELIM_CAPTURE)?:[$lookup];
        foreach($parts as$position=>$part){
            if($part==='-'||$part==='/')continue;
            if(isset(self::ACRONYMS[$part])){$parts[$position]=self::ACRONYMS[$part];continue;}
            $parts[$position]=$position===0
                ?mb_strtoupper(mb_substr($part,0,1,'UTF-8'),'UTF-8').mb_substr($part,1,null,'UTF-8')
                :$part;
        }
        return$prefix.implode('',$parts).$suffix;
    }
}
