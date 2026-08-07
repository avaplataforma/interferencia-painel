<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

final class FranchiseImplementation
{
    /**
     * @param array<string,mixed> $organization
     * @param list<array<string,mixed>> $domains
     * @param array<string,mixed>|null $contract
     * @param array<string,int> $facts
     * @return array{steps:list<array<string,mixed>>,required_total:int,required_done:int,progress:int,ready_to_activate:bool,missing:list<string>}
     */
    public static function evaluate(array $organization,array $domains,?array $contract,array $facts):array
    {
        $registrationComplete=self::filled($organization,['legal_name','display_name','cnpj','manager_name','manager_email','manager_phone','panel_slug']);
        $contractSigned=$contract!==null&&($contract['status']??'')==='signed';
        $commercialModel=$contract===null?'':(string)($contract['commercial_model']??'');
        $commercialDefined=$contractSigned&&in_array($commercialModel,['fixed_plus_percentage','split_only'],true);
        $percentage=$contract===null?0.0:(float)($contract['sales_fee_percentage']??0);
        $monthly=$contract===null?0.0:(float)($contract['monthly_fixed_amount']??0);
        $needsSplit=$percentage>0;
        $needsMonthly=$commercialModel==='fixed_plus_percentage'&&$monthly>0;
        $splitReady=!$needsSplit||(($organization['asaas_wallet_status']??'')==='validated'&&trim((string)($organization['asaas_wallet_id']??''))!==''&&(int)($organization['split_enabled']??0)===1);
        $monthlyReady=!$needsMonthly||trim((string)($contract['asaas_payment_link_url']??''))!=='';
        $financeReady=$commercialDefined&&$splitReady&&$monthlyReady;
        $brandingReady=trim((string)($organization['logo_path']??''))!==''&&trim((string)($organization['favicon_path']??''))!=='';
        $domainReady=false;
        foreach($domains as$domain){if(($domain['purpose']??'')==='site'&&($domain['status']??'')==='active'&&(int)($domain['is_primary']??0)===1){$domainReady=true;break;}}
        $adminReady=(int)($facts['active_admins']??0)>0;
        $avaReady=(int)($facts['active_ava_integrations']??0)>0;
        $active=($organization['status']??'')==='active';
        $slug=(string)($organization['panel_slug']??'');

        $steps=[
            self::step('cadastro','Cadastro conferido','Dados jurídicos, gestor e acesso exclusivo preenchidos.',$registrationComplete,'required','fa-building','/edit'),
            self::step('contrato','Contrato gerado e assinado','O contrato vigente precisa estar assinado.',$contractSigned,'required','fa-file-signature','/contracts'),
            self::step('comercial','Modelo comercial definido','Mensalidade e/ou percentual definidos no contrato.',$commercialDefined,'required','fa-scale-balanced','/contracts'),
            self::step('financeiro','Cobrança ou split configurado',self::financeDetail($needsMonthly,$needsSplit,$monthlyReady,$splitReady),$financeReady,'required','fa-wallet','#operacao-comercial'),
            self::step('administrador','Usuário administrador criado','A franquia precisa ter ao menos um administrador ativo.',$adminReady,'required','fa-user-shield','/'.$slug.'/users'),
            self::step('identidade','Identidade visual configurada','Logo e favicon próprios cadastrados.',$brandingReady,'required','fa-palette','/edit'),
            self::step('dominio','Domínio e site configurados','O domínio principal do site precisa estar validado.',$domainReady,'required','fa-globe','/edit'),
            self::step('ava','AVA e integrações vinculados','Ao menos uma conexão ativa com o AVA.',$avaReady,'required','fa-graduation-cap','/'.$slug.'/admin/integrations/moodle'),
            self::step('ativacao','Franquia ativada','Libera o login e a operação da franquia.',$active,'activation','fa-circle-check',''),
        ];
        $required=array_values(array_filter($steps,static fn(array $step):bool=>$step['kind']==='required'));
        $done=count(array_filter($required,static fn(array $step):bool=>$step['done']));
        $missing=array_values(array_map(static fn(array $step):string=>(string)$step['label'],array_filter($required,static fn(array $step):bool=>!$step['done'])));
        return['steps'=>$steps,'required_total'=>count($required),'required_done'=>$done,'progress'=>(int)round(($done/max(1,count($required)))*100),'ready_to_activate'=>$missing===[],'missing'=>$missing];
    }

    /** @param array<string,mixed> $data @param list<string> $keys */
    private static function filled(array $data,array $keys):bool{foreach($keys as$key)if(trim((string)($data[$key]??''))==='')return false;return true;}
    /** @return array<string,mixed> */
    private static function step(string$id,string$label,string$detail,bool$done,string$kind,string$icon,string$action):array{return compact('id','label','detail','done','kind','icon','action');}
    private static function financeDetail(bool$needsMonthly,bool$needsSplit,bool$monthlyReady,bool$splitReady):string
    {
        $pending=[];if($needsMonthly&&!$monthlyReady)$pending[]='gerar o link mensal';if($needsSplit&&!$splitReady)$pending[]='validar a Wallet e o split';
        return$pending===[]?'Regra financeira pronta para uso.':'Pendente: '.implode(' e ',$pending).'.';
    }
}
