<?php
/** Frontend self-service 2FA settings. Usage: [[!ModTwoAuthFrontend? &tpl=`123` &methods=`totp,telegram`]] */
/** @var \MODX\Revolution\modX $modx */
require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';
$T = static fn(string $key, array $placeholders = []): string => \ModTwoAuth\Localization::get($modx, $key, $placeholders);
$E = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
if (!(bool) $modx->getOption('modtwoauth_frontend_enable_2fa', null, true)) return '<p>' . $E($T('modtwoauth_frontend_disabled')) . '</p>';
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
if (!$m2aAuthOk) return '<p>' . $E($T('modtwoauth_frontend_login_to_manage')) . '</p>';
$modx->addPackage('ModTwoAuth\\Model', MODX_CORE_PATH . 'components/modtwoauth/src/', null, 'ModTwoAuth\\');
$assets = rtrim((string)$modx->getOption('assets_url', null, MODX_ASSETS_URL), '/');
$api = $E($assets . '/components/modtwoauth/frontend-settings.php');
$register = $E($assets . '/components/modtwoauth/webauthn-register.php?context=web');
$qrScript = $E($assets . '/components/modtwoauth/js/qrcode.local.js');
$userId = (int)$modx->user->get('id');
$setting = $modx->getObject(\ModTwoAuth\Model\TwoAuthUserSetting::class, ['user_id'=>$userId]);
$provider = $setting ? (string)$setting->get('provider') : '';
$enabled = $setting && (int)$setting->get('is_enabled');
$verified = $setting && (int)$setting->get('is_verified');
$available = [
    'totp' => $T('modtwoauth_frontend_provider_totp'),
    'email' => 'Email',
    'telegram' => 'Telegram',
    'webauthn' => 'Passkey / WebAuthn',
];
$current = $available[$provider] ?? $T('modtwoauth_frontend_not_configured');
$csrf = (string)($_SESSION['modtwoauth_frontend_csrf'] ?? '');
if ($csrf === '') { $csrf = bin2hex(random_bytes(24)); $_SESSION['modtwoauth_frontend_csrf'] = $csrf; }
$rawMethods = trim((string)$modx->getOption('methods', $scriptProperties, ''));
$methods = $rawMethods === '' || strtolower($rawMethods) === 'all' ? array_keys($available) : array_values(array_intersect(array_keys($available), array_map('trim', explode(',', strtolower($rawMethods)))));
if (!$methods) $methods = array_keys($available);
$options = '';
foreach ($methods as $method) $options .= '<option value="' . $E($method) . '">' . $E($available[$method]) . '</option>';
$tpl = trim((string)$modx->getOption('tpl', $scriptProperties, ''));
$tplName = '';
if ($tpl !== '') {
    if (ctype_digit($tpl)) { $chunkObject = $modx->getObject(\MODX\Revolution\modChunk::class, (int)$tpl); if ($chunkObject) $tplName = (string)$chunkObject->get('name'); }
    else $tplName = $tpl;
}
if (!$tplName || !$modx->getObject(\MODX\Revolution\modChunk::class, ['name'=>$tplName])) $tplName = 'modtwoauthFrontendSettingsTpl';
$verifyNote = $enabled && !$verified ? ' (' . $T('modtwoauth_frontend_requires_verification') . ')' : '';
$disabledAttr = (!$verified || $enabled) ? 'disabled' : '';
$markup = $modx->getChunk($tplName, [
    'api' => $api,
    'register' => $register,
    'csrf' => $E($csrf),
    'provider' => $E($provider),
    'current' => $E($current),
    'state' => $E($T($enabled ? 'modtwoauth_frontend_state_enabled' : 'modtwoauth_frontend_state_disabled')),
    'verify_note' => $E($verifyNote),
    'enable_disabled' => $disabledAttr,
    'method_options' => $options,
    'title' => $E($T('modtwoauth_frontend_security_title')),
    'intro' => $E($T('modtwoauth_frontend_security_intro')),
    'current_label' => $E($T('modtwoauth_frontend_current_method')),
    'state_label' => $E($T('modtwoauth_frontend_status')),
    'method_label' => $E($T('modtwoauth_frontend_method')),
    'setup_label' => $E($T('modtwoauth_frontend_setup_selected')),
    'enable_label' => $E($T('modtwoauth_frontend_enable')),
    'disable_title' => $E($T('modtwoauth_frontend_disable_title')),
    'disable_hint' => $E($T('modtwoauth_frontend_disable_hint')),
    'password_label' => $E($T('modtwoauth_frontend_current_password')),
    'disable_label' => $E($T('modtwoauth_frontend_disable')),
]);
$lexiconKeys = [
    'modtwoauth_frontend_provider_totp', 'modtwoauth_frontend_qr_library_failed',
    'modtwoauth_frontend_bad_response', 'modtwoauth_frontend_request_failed',
    'modtwoauth_mgr_totp_setup', 'modtwoauth_frontend_totp_instruction',
    'modtwoauth_frontend_secret_key', 'modtwoauth_mgr_enter_app_code',
    'modtwoauth_frontend_confirm_save', 'modtwoauth_frontend_totp_configured_enable',
    'modtwoauth_mgr_email_setup', 'modtwoauth_mgr_enter_email_code',
    'modtwoauth_frontend_confirm_email', 'modtwoauth_frontend_email_configured_enable',
    'modtwoauth_frontend_browser_no_passkey', 'modtwoauth_frontend_passkey_begin_failed',
    'modtwoauth_frontend_passkey_save_failed', 'modtwoauth_frontend_passkey_registered_enable',
    'modtwoauth_frontend_telegram_connect', 'modtwoauth_frontend_telegram_instruction',
    'modtwoauth_frontend_open_telegram', 'modtwoauth_frontend_waiting_confirmation',
    'modtwoauth_frontend_telegram_connected_enable', 'modtwoauth_frontend_connected',
    'modtwoauth_frontend_link_expired', 'modtwoauth_frontend_state_enabled',
    'modtwoauth_frontend_state_disabled', 'modtwoauth_frontend_enabled',
    'modtwoauth_frontend_disabled_success',
];
$lexicon = [];
foreach ($lexiconKeys as $key) $lexicon[$key] = $T($key);
return $markup . "\n<script src=\"" . $qrScript . "\"></script>\n<script>\nconst modTwoAuthLexicon = "
    . json_encode($lexicon, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    . ";\n" . <<<'JS'
(function(){
 const L=key=>modTwoAuthLexicon[key]||key;
 const root=document.getElementById('m2af-settings'); if(!root||root.dataset.ready)return;root.dataset.ready='1';
 const api=root.dataset.api,reg=root.dataset.register,csrf=root.dataset.csrf,box=document.getElementById('m2af-setup-box'),msg=document.getElementById('m2af-message'),sel=document.getElementById('m2af-provider'),enable=document.getElementById('m2af-enable');
 if(['totp','email','telegram','webauthn'].includes(root.dataset.provider))sel.value=root.dataset.provider;
 function renderQr(el,data,size){if(!window.ModTwoAuthQR)throw new Error(L('modtwoauth_frontend_qr_library_failed'));window.ModTwoAuthQR.render(el,data,size||280);}
 const labels={totp:L('modtwoauth_frontend_provider_totp'),email:'Email',telegram:'Telegram',webauthn:'Passkey / WebAuthn'};
 function message(text,ok){msg.textContent=text;msg.className='m2af-message '+(ok?'ok':'error');}
 async function call(action,data={}){const r=await fetch(api,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(Object.assign({action,csrf},data))});let j;try{j=await r.json()}catch(e){throw new Error(L('modtwoauth_frontend_bad_response').replace('[[+status]]',r.status));}if(!r.ok||!j.ok)throw new Error(j.message||L('modtwoauth_frontend_request_failed'));return j;}
 function b64url(s){s=s.replace(/-/g,'+').replace(/_/g,'/');while(s.length%4)s+='=';const bin=atob(s),out=new Uint8Array(bin.length);for(let i=0;i<bin.length;i++)out[i]=bin.charCodeAt(i);return out;}
 function b64encode(b){let s='';new Uint8Array(b).forEach(x=>s+=String.fromCharCode(x));return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');}
 async function setup(){box.style.display='block';box.innerHTML='';msg.className='m2af-message';const p=sel.value;
  if(p==='totp'){
   const j=await call('begin_totp');
   box.innerHTML='<h3>'+L('modtwoauth_mgr_totp_setup')+'</h3><p>'+L('modtwoauth_frontend_totp_instruction')+'</p><div class="m2af-qr" aria-label="QR TOTP"></div><p><strong>'+L('modtwoauth_frontend_secret_key')+'</strong> <code></code></p><label>'+L('modtwoauth_mgr_enter_app_code')+'</label><input class="wide" id="m2af-code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"><div class="m2af-row"><button type="button" id="m2af-confirm">'+L('modtwoauth_frontend_confirm_save')+'</button></div>';
   renderQr(box.querySelector('.m2af-qr'),j.qr_data,280);box.querySelector('code').textContent=j.secret;
   box.querySelector('#m2af-confirm').onclick=async()=>{try{await call('confirm_totp',{code:box.querySelector('#m2af-code').value});message(L('modtwoauth_frontend_totp_configured_enable'),true);document.getElementById('m2af-current').textContent=labels.totp;enable.disabled=false;}catch(e){message(e.message,false)}};
  }else if(p==='email'){
   const j=await call('send_email_code');
   box.innerHTML='<h3>'+L('modtwoauth_mgr_email_setup')+'</h3><p id="m2af-email-message"></p><label>'+L('modtwoauth_mgr_enter_email_code')+'</label><input class="wide" id="m2af-code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"><div class="m2af-row"><button type="button" id="m2af-confirm">'+L('modtwoauth_frontend_confirm_email')+'</button></div>';
   box.querySelector('#m2af-email-message').textContent=j.message;
   box.querySelector('#m2af-confirm').onclick=async()=>{try{await call('confirm_email',{code:box.querySelector('#m2af-code').value});message(L('modtwoauth_frontend_email_configured_enable'),true);document.getElementById('m2af-current').textContent=labels.email;enable.disabled=false;}catch(e){message(e.message,false)}};
  }else if(p==='webauthn'){
   if(!window.PublicKeyCredential||!navigator.credentials)throw new Error(L('modtwoauth_frontend_browser_no_passkey'));
   const r=await fetch(reg+'&_='+Date.now(),{credentials:'same-origin'});const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||L('modtwoauth_frontend_passkey_begin_failed'));
   const o=j.publicKey;o.challenge=b64url(o.challenge);o.user.id=b64url(o.user.id);if(o.excludeCredentials)o.excludeCredentials=o.excludeCredentials.map(c=>Object.assign({},c,{id:b64url(c.id)}));
   const c=await navigator.credentials.create({publicKey:o});const credential={id:c.id,rawId:b64encode(c.rawId),type:c.type,response:{clientDataJSON:b64encode(c.response.clientDataJSON),attestationObject:b64encode(c.response.attestationObject),transports:c.response.getTransports?c.response.getTransports():[]}};
   const rr=await fetch(reg,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({context:'web',credential})});const result=await rr.json();if(!rr.ok||!result.ok)throw new Error(result.message||L('modtwoauth_frontend_passkey_save_failed'));
   message(result.message||L('modtwoauth_frontend_passkey_registered_enable'),true);document.getElementById('m2af-current').textContent=labels.webauthn;enable.disabled=false;
  }else if(p==='telegram'){
   const j=await call('telegram_start');
   box.innerHTML='<h3>'+L('modtwoauth_frontend_telegram_connect')+'</h3><p>'+L('modtwoauth_frontend_telegram_instruction')+'</p><p><a target="_blank" rel="noopener noreferrer">'+L('modtwoauth_frontend_open_telegram')+'</a></p><div class="m2af-qr" aria-label="QR Telegram"></div><p id="m2af-tg-status">'+L('modtwoauth_frontend_waiting_confirmation')+'</p>';
   box.querySelector('a').href=j.deep_link;box.querySelector('a').textContent=j.deep_link;renderQr(box.querySelector('.m2af-qr'),j.qr_data,280);
   const timer=setInterval(async()=>{try{const s=await call('telegram_status');if(s.status==='confirmed'){clearInterval(timer);message(L('modtwoauth_frontend_telegram_connected_enable'),true);document.getElementById('m2af-current').textContent=labels.telegram;enable.disabled=false;box.querySelector('#m2af-tg-status').textContent=L('modtwoauth_frontend_connected');}else if(s.status==='expired'){clearInterval(timer);message(L('modtwoauth_frontend_link_expired'),false);}}catch(e){clearInterval(timer);message(e.message,false)}},2500);
  }
 }
 document.getElementById('m2af-setup').onclick=()=>setup().catch(e=>message(e.message,false));
 enable.onclick=async()=>{try{await call('enable');document.getElementById('m2af-state').textContent=L('modtwoauth_frontend_state_enabled');enable.disabled=true;message(L('modtwoauth_frontend_enabled'),true);}catch(e){message(e.message,false)}};
 document.getElementById('m2af-disable').onclick=async()=>{try{await call('disable',{password:document.getElementById('m2af-password').value});document.getElementById('m2af-state').textContent=L('modtwoauth_frontend_state_disabled');enable.disabled=false;document.getElementById('m2af-password').value='';message(L('modtwoauth_frontend_disabled_success'),true);}catch(e){message(e.message,false)}};
})();
JS
. "\n</script>";
