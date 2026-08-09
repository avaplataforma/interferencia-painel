<?php

defined('MOODLE_INTERNAL') || die();

function local_mundointer_before_standard_html_head(): string
{
    $brand=\local_mundointer\local\brand_resolver::current();if($brand===null)return'';
    $primary=s((string)$brand['primary_color']);$secondary=s((string)$brand['secondary_color']);$favicon=s((string)$brand['favicon_url']);
    return'<link rel="icon" href="'.$favicon.'"><meta name="theme-color" content="'.$primary.'"><style>:root{--mundointer-primary:'.$primary.';--mundointer-secondary:'.$secondary.'}.btn-primary,.bg-primary{background-color:var(--mundointer-primary)!important;border-color:var(--mundointer-primary)!important}.text-primary,a{color:var(--mundointer-primary)}.mundointer-brand-ribbon{display:flex;align-items:center;gap:.75rem;padding:.55rem 1rem;border-bottom:3px solid var(--mundointer-primary);background:#fff;color:#17212b;position:relative;z-index:1040}.mundointer-brand-ribbon img{width:2.45rem;height:2.45rem;object-fit:contain}.mundointer-brand-ribbon strong,.mundointer-brand-ribbon small{display:block}.mundointer-brand-ribbon small{color:#647482}.pagelayout-login .mundointer-brand-ribbon{max-width:32rem;margin:2rem auto 0;border:1px solid #dce3e8;border-bottom:3px solid var(--mundointer-primary);border-radius:1rem 1rem 0 0;padding:1rem 1.2rem}.pagelayout-login .mundointer-brand-ribbon img{width:4rem;height:4rem}.pagelayout-login #page{margin-top:0}</style>';
}

function local_mundointer_before_standard_top_of_body_html(): string
{
    $brand=\local_mundointer\local\brand_resolver::current();if($brand===null)return'';
    $logo=s((string)$brand['logo_url']);$name=s((string)$brand['login_title']);$welcome=s((string)$brand['welcome_text']);
    return'<div class="mundointer-brand-ribbon" data-franquia="'.s((string)$brand['slug']).'"><img src="'.$logo.'" alt=""><span><strong>'.$name.'</strong><small>'.$welcome.'</small></span></div>';
}
