<?php

namespace local_mundointer\local;

defined('MOODLE_INTERNAL') || die();

final class brand_resolver
{
    private const COOKIE_NAME = 'MundoInterBrand';

    /** @return array<string,mixed>|null */
    public static function current(): ?array
    {
        global $DB,$SESSION,$USER;
        if(!get_config('local_mundointer','enabled'))return null;
        $catalog=self::catalog();$brands=$catalog['brands']??[];if(!is_array($brands)||$brands===[])return null;
        if(isloggedin()&&!isguestuser()&&!empty($USER->id)){
            $shortname=(string)($catalog['profile_field']??get_config('local_mundointer','profilefield')?:'polo_presencial');
            $sql='SELECT d.data FROM {user_info_data} d JOIN {user_info_field} f ON f.id=d.fieldid WHERE d.userid=:userid AND f.shortname=:shortname';
            $polo=(string)($DB->get_field_sql($sql,['userid'=>(int)$USER->id,'shortname'=>$shortname])?:'');
            if($polo!==''&&($brand=self::byPolo($brands,$polo))!==null){self::remember((string)$brand['slug']);return$brand;}
        }
        $slug=(string)($SESSION->local_mundointer_brand??($_COOKIE[self::COOKIE_NAME]??''));
        $slug=clean_param($slug,PARAM_ALPHANUMEXT);
        if($slug!==''&&($brand=self::bySlug($brands,$slug))!==null){$SESSION->local_mundointer_brand=$slug;return$brand;}
        $default=(string)get_config('local_mundointer','defaultbrand');
        return$default!==''?self::bySlug($brands,$default):null;
    }

    public static function remember(string $slug):void
    {
        global $CFG,$SESSION;
        $slug=clean_param($slug,PARAM_ALPHANUMEXT);
        if($slug==='')return;
        $SESSION->local_mundointer_brand=$slug;
        if(headers_sent()||($_COOKIE[self::COOKIE_NAME]??'')===$slug)return;
        setcookie(self::COOKIE_NAME,$slug,[
            'expires'=>time()+180*DAYSECS,
            'path'=>'/',
            'secure'=>str_starts_with((string)$CFG->wwwroot,'https://'),
            'httponly'=>true,
            'samesite'=>'Lax',
        ]);
        $_COOKIE[self::COOKIE_NAME]=$slug;
    }

    /** @return array<string,mixed>|null */
    public static function bySlug(array$brands,string$slug):?array{foreach($brands as$brand)if(is_array($brand)&&hash_equals((string)($brand['slug']??''),$slug))return$brand;return null;}

    /** @return array<string,mixed> */
    public static function catalog():array{$decoded=json_decode((string)get_config('local_mundointer','brandcatalog'),true);return is_array($decoded)?$decoded:[];}

    /** @return array<string,mixed>|null */
    private static function byPolo(array$brands,string$polo):?array{$needle=self::normalize($polo);foreach($brands as$brand)if(is_array($brand))foreach((array)($brand['poles']??[])as$value)if(self::normalize((string)$value)===$needle)return$brand;return null;}

    private static function normalize(string$value):string{$value=\core_text::strtolower(trim($value));return preg_replace('/\s+/u',' ',$value)??$value;}
}
