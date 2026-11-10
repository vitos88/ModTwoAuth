<?php
/** Frontend active session manager. Usage: [[!ModTwoAuthSessions? &tpl=`123` &showCurrent=`1`]] */
require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';
$T = static fn(string $key, array $placeholders = []): string => \ModTwoAuth\Localization::get($modx, $key, $placeholders);
$E = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
/* Пользователь может быть авторизован в любом контексте сайта, не только web. */
$m2aAuthOk = false;
if ($modx->user && (int) $modx->user->get('id') > 0) {
    $m2aSessionUserId = (int) $modx->user->get('id');
    $m2aContextTokens = $_SESSION['modx.user.contextTokens'] ?? [];
    if (is_array($m2aContextTokens)) {
        foreach ($m2aContextTokens as $m2aCtxKey => $m2aCtxUserId) {
            if (is_string($m2aCtxKey) && $m2aCtxKey !== 'mgr' && (int) $m2aCtxUserId === $m2aSessionUserId) {
                $m2aAuthOk = true;
                break;
            }
        }
    }
}
if (!$m2aAuthOk) return '<p>' . $E($T('modtwoauth_frontend_login_to_sessions')) . '</p>';
if (!(bool)$modx->getOption('modtwoauth_frontend_enable_2fa', null, true)) return '<p>' . $E($T('modtwoauth_frontend_sessions_disabled')) . '</p>';
$assets=rtrim((string)$modx->getOption('assets_url',null,MODX_ASSETS_URL),'/');
$csrf=(string)($_SESSION['modtwoauth_frontend_csrf']??'');if($csrf===''){$csrf=bin2hex(random_bytes(24));$_SESSION['modtwoauth_frontend_csrf']=$csrf;}
$tpl=trim((string)$modx->getOption('tpl',$scriptProperties,''));
$tplName='';
if($tpl!==''){
 if(ctype_digit($tpl)){ $obj=$modx->getObject(\MODX\Revolution\modChunk::class,(int)$tpl);if($obj)$tplName=(string)$obj->get('name');}
 else $tplName=$tpl;
}
if(!$tplName||!$modx->getObject(\MODX\Revolution\modChunk::class,['name'=>$tplName]))$tplName='modtwoauthSessionsTpl';
$markup=$modx->getChunk($tplName,[
 'api'=>$E($assets.'/components/modtwoauth/frontend-settings.php'),
 'csrf'=>$E($csrf),
 'show_current'=>((string)$modx->getOption('showCurrent',$scriptProperties,'1')==='0'?'0':'1'),
 'title'=>$E($T('modtwoauth_frontend_sessions_title')),
 'intro'=>$E($T('modtwoauth_frontend_sessions_intro')),
 'refresh_label'=>$E($T('modtwoauth_frontend_refresh')),
 'loading_label'=>$E($T('modtwoauth_frontend_sessions_loading')),
]);
$lexiconKeys=[
 'modtwoauth_frontend_bad_response','modtwoauth_frontend_request_failed',
 'modtwoauth_frontend_sessions_loading','modtwoauth_frontend_sessions_empty',
 'modtwoauth_frontend_unknown_browser','modtwoauth_frontend_ip_unknown',
 'modtwoauth_frontend_created','modtwoauth_frontend_last_active',
 'modtwoauth_frontend_current_session','modtwoauth_frontend_revoke_session',
 'modtwoauth_frontend_revoke_confirm',
];
$lexicon=[];foreach($lexiconKeys as $key)$lexicon[$key]=$T($key);
return $markup . "\n<script>\nconst modTwoAuthSessionsLexicon="
 .json_encode($lexicon,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)
 .";\n". <<<'JS'
(function(){
const L=key=>modTwoAuthSessionsLexicon[key]||key;
const root=document.getElementById('m2as-sessions');if(!root||root.dataset.ready)return;root.dataset.ready='1';
const api=root.dataset.api,csrf=root.dataset.csrf,showCurrent=root.dataset.showCurrent!=='0',list=document.getElementById('m2as-list');
async function call(action,data={}){const r=await fetch(api,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(Object.assign({action,csrf},data))});let j;try{j=await r.json()}catch(e){throw new Error(L('modtwoauth_frontend_bad_response').replace('[[+status]]',r.status));}if(!r.ok||!j.ok)throw new Error(j.message||L('modtwoauth_frontend_request_failed'));return j;}
function add(parent,tag,text,cls){const el=document.createElement(tag);el.textContent=text;if(cls)el.className=cls;parent.appendChild(el);return el;}
async function load(){
 list.textContent=L('modtwoauth_frontend_sessions_loading');
 try{
  const j=await call('sessions_list');list.innerHTML='';const rows=showCurrent?j.sessions:j.sessions.filter(s=>!s.is_current);
  if(!rows.length){list.textContent=L('modtwoauth_frontend_sessions_empty');return;}
  rows.forEach(s=>{
   const card=document.createElement('div');card.className='m2as-session';
   add(card,'strong',(s.user_agent||L('modtwoauth_frontend_unknown_browser')).slice(0,500));
   add(card,'div','IP: '+(s.ip_address||L('modtwoauth_frontend_ip_unknown')),'m2as-session-meta');
   add(card,'div',L('modtwoauth_frontend_created')+' '+(s.created_at||'—'),'m2as-session-meta');
   add(card,'div',L('modtwoauth_frontend_last_active')+' '+(s.last_active||'—'),'m2as-session-meta');
   if(s.is_current){add(card,'span',L('modtwoauth_frontend_current_session'),'m2as-session-current');}
   else{
    const btn=add(card,'button',L('modtwoauth_frontend_revoke_session'),'danger');btn.type='button';
    btn.onclick=async()=>{if(!window.confirm(L('modtwoauth_frontend_revoke_confirm')))return;btn.disabled=true;try{await call('session_revoke',{id:s.id});await load();}catch(e){btn.disabled=false;list.textContent=e.message;}};
   }
   list.appendChild(card);
  });
 }catch(e){list.textContent=e.message;}
}
document.getElementById('m2as-refresh').onclick=load;load();
})();
JS
. "\n</script>";
