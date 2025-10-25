<?php
declare(strict_types=1);

/* === Mixed Content 対策（最小） === */
/* サーバが注入する Link: <http://...>; rel=icon を除去 → 安全な favicon に強制 */
if (function_exists('header_remove')) { @header_remove('Link'); }
@header('Link: </pointcard/assets/logo80x80.ico>; rel="icon"', false);
/* 念のため：混在コンテンツの強制アップグレード/ブロック */
@header("Content-Security-Policy: upgrade-insecure-requests; block-all-mixed-content");

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/auth/session.php';
start_secure_session();

/* 既ログインならホームへ */
if (!empty($_SESSION['uid'])) {
  header('Location: /pointcard/');
  exit;
}

/* ★キャッシュ禁止：戻るでフォームが復元されないように */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ログイン｜ポイントカード</title>
  <link rel="icon" href="/pointcard/assets/logo80x80.ico">
  <link rel="stylesheet" href="/pointcard/styles.css" />
  <style>
    .auth-wrap{max-width:520px;margin:32px auto;padding:20px;background:#fff;border:1px solid var(--border,#e5e7eb);border-radius:12px}
    .tabs-auth{display:flex;gap:8px;margin-bottom:16px}
    .tabs-auth button{flex:1}
    .muted{color:#6b7280}
    .fld{display:grid;gap:6px;margin:10px 0}
    input[type="email"],input[type="password"],input[type="text"]{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px}
    .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
    .link{color:#2563eb;text-decoration:underline;cursor:pointer}
    .msg{margin-top:8px;font-size:14px}
    .ok{color:#047857}.err{color:#b91c1c}
    .field-error{border-color:#f87171; box-shadow:0 0 0 2px rgba(248,113,113,.15)}
    .pw-wrap{position:relative;display:flex;align-items:center}
    .pw-wrap input{flex:1; padding-right:44px}
    .pw-toggle{position:absolute;right:6px;top:50%;transform:translateY(-50%);border:0;background:transparent;cursor:pointer;padding:6px;border-radius:8px}
    .pw-toggle:focus-visible{outline:2px solid #93c5fd}
    .toast{position:fixed;left:50%;bottom:26px;transform:translateX(-50%);background:#111827;color:#fff;padding:10px 14px;border-radius:10px;z-index:9999}
    .is-loading{opacity:.7;pointer-events:none}
    ul.err-list{margin:.4em 0 0 .8em; padding:0; list-style:disc}
  </style>
</head>
<body>
  <div class="auth-wrap" aria-label="ログイン/新規登録">
    <div class="tabs-auth" role="tablist">
      <button id="tabLogin" class="btn" type="button" aria-controls="panelLogin" aria-selected="true">ログイン</button>
      <button id="tabSignup" class="btn-ghost" type="button" aria-controls="panelSignup" aria-selected="false">新規会員登録</button>
    </div>

    <!-- ログイン -->
    <section id="panelLogin" role="tabpanel" aria-labelledby="tabLogin">
      <div class="fld">
        <label class="muted" for="loginEmail">ID（メールアドレス）</label>
        <input type="email" id="loginEmail" name="login_email"
               placeholder="you@example.com" autocomplete="username"
               autocapitalize="off" autocorrect="off" spellcheck="false">
      </div>

      <div class="fld">
        <label class="muted" for="loginPass">パスワード</label>
        <div class="pw-wrap">
          <input type="password" id="loginPass" name="login_password"
                 placeholder="8文字以上" autocomplete="current-password">
          <button class="pw-toggle" type="button" aria-label="パスワードを表示" data-toggle="#loginPass">
            <!-- eye (表示) -->
            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" aria-hidden="true" width="22" height="22">
              <path d="M12 5c4.7 0 8.9 3.1 10.5 7-1.6 3.9-5.8 7-10.5 7S3.1 15.9 1.5 12C3.1 8.1 7.3 5 12 5zm0 3a4 4 0 100 8 4 4 0 000-8z" fill="#6b7280"/>
            </svg>
            <!-- eye-off (非表示) -->
            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true" width="22" height="22" style="display:none">
              <path d="M3 4.3L4.3 3 21 19.7 19.7 21l-3.2-3.2A12.7 12.7 0 0112 19C7.3 19 3.1 15.9 1.5 12C3.1 8.1 7.3 5 12 5zm7 6a7 7 0 11-14 0 7 7 0 0114 0z" fill="#6b7280"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="actions">
        <button class="btn" id="btnLogin" type="button">ログイン</button>
        <span class="muted">／</span>
        <a class="link" id="linkForgot">パスワードが不明な方はこちら</a>
      </div>
      <div id="loginMsg" class="msg" aria-live="polite"></div>
    </section>

    <!-- 新規登録 -->
    <section id="panelSignup" role="tabpanel" aria-labelledby="tabSignup" hidden>
      <div class="fld">
        <label class="muted" for="signEmail">メールアドレス</label>
        <input type="email" id="signEmail" name="signup_email"
               placeholder="you@example.com" autocomplete="email"
               autocapitalize="off" autocorrect="off" spellcheck="false">
      </div>

      <div class="fld">
        <label class="muted" for="signPass">パスワード</label>
        <div class="pw-wrap">
          <input type="password" id="signPass" name="signup_password"
                 placeholder="8文字以上" autocomplete="new-password">
          <button class="pw-toggle" type="button" aria-label="パスワードを表示" data-toggle="#signPass">
            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" aria-hidden="true" width="22" height="22">
              <path d="M12 5c4.7 0 8.9 3.1 10.5 7-1.6 3.9-5.8 7-10.5 7S3.1 15.9 1.5 12C3.1 8.1 7.3 5 12 5zm0 3a4 4 0 100 8 4 4 0 000-8z" fill="#6b7280"/>
            </svg>
            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true" width="22" height="22" style="display:none">
              <path d="M3 4.3L4.3 3 21 19.7 19.7 21l-3.2-3.2A12.7 12.7 0 0112 19C7.3 19 3.1 15.9 1.5 12a13.5 13.5 0 013.9-5.1L3 4.3zm7.1 3.7l1.9 1.9a2.7 2.7 0 00-3.6 3.6l1.9 1.9A4 4 0 0010.1 8zM12 5c1.3 0 2.5.3 3.7.8l-1.6 1.6A6 6 0 006 12c0 1 .2 2 .7 2.9l-1.6 1.6A13.5 13.5 0 0112 5c4.7 0 8.9 3.1 10.5 7-.5 1.2-1.2 2.2-2 3.2l-1.5-1.5c.4-.7.7-1.5.9-2.3z" fill="#6b7280"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="fld">
        <label class="muted" for="signNick">ニックネーム（任意）</label>
        <input type="text" id="signNick" name="signup_nickname" placeholder="あなた" maxlength="24" autocomplete="nickname">
      </div>
      <div class="actions">
        <button class="btn" id="btnSignup" type="button">登録する</button>
        <button class="btn-ghost" id="goLoginFromSignup" type="button">ログインへ戻る</button>
      </div>
      <div id="signupMsg" class="msg" aria-live="polite"></div>
    </section>

    <!-- パスワード再設定（2段階） -->
    <section id="panelReset" role="dialog" hidden aria-labelledby="resetTitle" aria-modal="true">
      <h3 id="resetTitle">パスワード再設定</h3>
      <ol class="muted" style="margin:8px 0 12px">
        <li>メールアドレスに6桁コードを送信します</li>
        <li>コード確認後、新しいパスワードを設定します</li>
      </ol>

      <!-- Step1 -->
      <div id="resetStep1">
        <div class="fld">
          <label class="muted" for="resetEmail">メールアドレス</label>
          <input type="email" id="resetEmail" placeholder="you@example.com" autocomplete="email">
        </div>
        <div class="actions">
          <button class="btn" id="btnSendCode" type="button">6桁コードを送信</button>
          <button class="btn-ghost" id="resetCancel" type="button">キャンセル</button>
        </div>
        <div id="resetMsg1" class="msg" aria-live="polite"></div>
      </div>

      <!-- Step2 -->
      <div id="resetStep2" hidden>
        <div class="fld">
          <label class="muted" for="resetCode">6桁コード</label>
          <input type="text" id="resetCode" placeholder="例）123456" inputmode="numeric" pattern="\d{6}">
        </div>
        <div class="actions">
          <button class="btn" id="btnVerifyCode" type="button">コード確認</button>
        </div>
        <div id="resetMsg2" class="msg" aria-live="polite"></div>
      </div>

      <!-- Step3 -->
      <div id="resetStep3" hidden>
        <div class="fld">
          <label class="muted" for="resetNewPass">新しいパスワード</label>
          <div class="pw-wrap">
            <input type="password" id="resetNewPass" name="reset_new_password"
                   placeholder="8文字以上" autocomplete="new-password">
            <button class="pw-toggle" type="button" aria-label="パスワードを表示" data-toggle="#resetNewPass">
              <svg class="icon-eye" viewBox="0 0 24 24" fill="none" aria-hidden="true" width="22" height="22">
                <path d="M12 5c4.7 0 8.9 3.1 10.5 7-1.6 3.9-5.8 7-10.5 7S3.1 15.9 1.5 12C3.1 8.1 7.3 5 12 5zm0 3a4 4 0 100 8 4 4 0 000-8z" fill="#6b7280"/>
              </svg>
              <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true" width="22" height="22" style="display:none">
                <path d="M3 4.3L4.3 3 21 19.7 19.7 21l-3.2-3.2A12.7 12.7 0 0112 19C7.3 19 3.1 15.9 1.5 12a13.5 13.5 0 013.9-5.1L3 4.3zm7.1 3.7l1.9 1.9a2.7 2.7 0 00-3.6 3.6l1.9 1.9A4 4 0 0010.1 8zM12 5c1.3 0 2.5.3 3.7.8l-1.6 1.6A6 6 0 006 12c0 1 .2 2 .7 2.9l-1.6 1.6A13.5 13.5 0 0112 5c4.7 0 8.9 3.1 10.5 7-.5 1.2-1.2 2.2-2 3.2l-1.5-1.5c.4-.7.7-1.5.9-2.3z" fill="#6b7280"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="actions">
          <button class="btn" id="btnSetNewPass" type="button">パスワードを更新</button>
        </div>
        <div id="resetMsg3" class="msg" aria-live="polite"></div>
      </div>
    </section>
  </div>

<script>
/* ===== APIエンドポイント ===== */
const API = {
  login:  '/pointcard/auth/api/login.php',
  logout: '/pointcard/auth/api/logout.php',
  signup: '/pointcard/auth/api/register.php',
  reqRes: '/pointcard/auth/api/request_reset.php',
  verCd:  '/pointcard/auth/api/verify_code.php',
  setPw:  '/pointcard/auth/api/set_password.php',
};

/* ===== ユーティリティ ===== */
const $ = sel => document.querySelector(sel);
function toast(msg, ms=2200){
  const t = document.createElement('div');
  t.className='toast'; t.textContent=msg;
  document.body.appendChild(t);
  setTimeout(()=>{ t.remove(); }, ms);
}
function setLoading(btn, on){ if(!btn) return; btn.classList.toggle('is-loading', !!on); btn.disabled = !!on; }
function showErrors(container, message, errors){
  container.innerHTML = '';
  const p = document.createElement('div');
  p.className = 'err';
  p.textContent = message || 'エラーが発生しました';
  container.appendChild(p);

  if (errors && typeof errors === 'object') {
    const ul = document.createElement('ul'); ul.className='err-list';
    Object.entries(errors).forEach(([field, msg])=>{
      const li = document.createElement('li');
      li.textContent = `${msg}`;
      ul.appendChild(li);
      const inp = document.querySelector(`#${field}`);
      if (inp) { inp.classList.add('field-error'); inp.addEventListener('input', ()=>inp.classList.remove('field-error'), {once:true}); }
    });
    container.appendChild(ul);
  }
}
function clearErrors(...ids){ ids.forEach(id=>{ const el = document.getElementById(id); if (el) el.classList.remove('field-error'); }); }
async function post(url, data){
  const r = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)});
  let json = {}; try { json = await r.json(); } catch (_){}
  return {ok:r.ok, json};
}

/* ===== パスワード欄の“空＆非表示”を保証（戻る/bfcache対策） ===== */
function hardClearPasswords() {
  const pairs = [
    ['#loginPass',      '[data-toggle="#loginPass"]'],
    ['#signPass',       '[data-toggle="#signPass"]'],
    ['#resetNewPass',   '[data-toggle="#resetNewPass"]'],
  ];
  for (const [sel, btnSel] of pairs) {
    const inp = document.querySelector(sel);
    if (inp) { inp.value = ''; inp.type  = 'password'; }
    const btn = document.querySelector(btnSel);
    if (btn) {
      const eye    = btn.querySelector('.icon-eye');
      const eyeOff = btn.querySelector('.icon-eye-off');
      if (eye && eyeOff) { eye.style.display=''; eyeOff.style.display='none'; }
      btn.setAttribute('aria-label', 'パスワードを表示');
    }
  }
}
document.addEventListener('DOMContentLoaded', hardClearPasswords);
window.addEventListener('pageshow', (e) => { if (e.persisted) hardClearPasswords(); });

/* ===== タブ切替 ===== */
function resetSignupFields(){
  const ids = ['signEmail','signPass','signNick'];
  ids.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  clearErrors(...ids);
  const msg = $('#signupMsg'); if (msg) msg.textContent='';
}
function clearLoginPasswordOnly(){
  const p = document.querySelector('#loginPass'); if (p) p.value = '';
  const btn = document.querySelector('[data-toggle="#loginPass"]');
  if (btn) {
    const eye=btn.querySelector('.icon-eye'), eyeOff=btn.querySelector('.icon-eye-off');
    if (eye && eyeOff){ eye.style.display=''; eyeOff.style.display='none'; }
    btn.setAttribute('aria-label','パスワードを表示');
  }
}
function swapTab(toSignup){
  $('#tabLogin').className = toSignup ? 'btn-ghost':'btn';
  $('#tabSignup').className = toSignup ? 'btn':'btn-ghost';
  $('#panelLogin').hidden = !!toSignup;
  $('#panelSignup').hidden = !toSignup;
  $('#panelReset').hidden = true;
  $('#loginMsg').textContent='';
  if (toSignup) resetSignupFields(); else clearLoginPasswordOnly();
}
document.addEventListener('click', (ev) => {
  const trg = ev.target;
  const a = trg.closest('#tabLogin, #tabSignup, #goLoginFromSignup');
  if (!a) return;
  if (a.id === 'tabLogin' || a.id === 'goLoginFromSignup') swapTab(false);
  if (a.id === 'tabSignup') swapTab(true);
});

/* ===== パスワード可視化（イベント委譲） ===== */
document.addEventListener('click', (ev) => {
  const btn = ev.target.closest('.pw-toggle');
  if (!btn) return;
  ev.preventDefault();
  const sel = btn.getAttribute('data-toggle');
  let inp = null;
  if (sel) inp = document.querySelector(sel);
  if (!inp) {
    const wrap = btn.closest('.pw-wrap');
    if (wrap) inp = wrap.querySelector('input[type="password"], input[type="text"]');
  }
  if (!inp) return;

  const isPw = inp.type === 'password';
  try { inp.type = isPw ? 'text' : 'password'; } catch(_) {}

  const eye    = btn.querySelector('.icon-eye');
  const eyeOff = btn.querySelector('.icon-eye-off');
  if (eye && eyeOff) { eye.style.display = isPw ? 'none' : ''; eyeOff.style.display = isPw ? '' : 'none'; }
  btn.setAttribute('aria-label', isPw ? 'パスワードを隠す' : 'パスワードを表示');
});

/* ===== ログイン ===== */
document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('#btnLogin');
  if (!btn) return;
  clearErrors('loginEmail','loginPass');
  const email = document.querySelector('#loginEmail').value.trim();
  const password = document.querySelector('#loginPass').value;
  const msg = document.querySelector('#loginMsg');
  msg.textContent = '';
  btn.classList.add('is-loading'); btn.disabled = true;
  try {
    const {ok, json} = await post('/pointcard/auth/api/login.php', {email, password});
    if(ok && json.ok){
      msg.innerHTML = '<span class="ok">ログインしました。トップへ移動します…</span>';
      location.href = '/pointcard/';
    }else{
      const message = (json && (json.error || json.message)) || 'メールアドレスまたはパスワードが違います';
      showErrors(msg, message, json && json.errors);
    }
  } finally {
    btn.classList.remove('is-loading'); btn.disabled = false;
  }
});

/* ===== パスワード不明リンク（ダイアログ表示） ===== */
document.addEventListener('click', (ev) => {
  const link = ev.target.closest('#tabLogin, #tabSignup, #linkForgot');
  if (!link || link.id !== 'linkForgot') return;
  document.getElementById('panelLogin').hidden = true;
  document.getElementById('panelSignup').hidden = true;
  document.getElementById('panelReset').hidden = false;
  document.getElementById('resetStep1').hidden = false;
  document.getElementById('resetMsg1').textContent = '';
  document.getElementById('resetEmail').value = document.getElementById('loginEmail').value.trim();
  document.getElementById('resetStep2').hidden = true;
  document.getElementById('resetMsg2').textContent = '';
  document.getElementById('resetCode').value = '';
  document.getElementById('resetStep3').hidden = true;
  document.getElementById('resetMsg3').textContent = '';
});

/* Reset: Step1 コード送信 */
document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('#btnSendCode');
  if (!btn) return;
  const emailEl = document.getElementById('resetEmail');
  const msg = document.getElementById('resetMsg1');
  const email = (emailEl.value || '').trim();
  if(!email){
    showErrors(msg, 'メールアドレスを入力してください', {resetEmail:'メールアドレスを入力してください'});
    return;
  }
  btn.classList.add('is-loading'); btn.disabled = true;
  try {
    const {ok} = await post('/pointcard/auth/api/request_reset.php', {email});
    if (ok) {
      msg.innerHTML = '<span class="ok">もし登録済みであれば、6桁コードを送信しました（有効期限15分）。</span>';
      document.getElementById('resetStep1').hidden = true;
      document.getElementById('resetStep2').hidden = false;
      document.getElementById('resetMsg2').textContent = '';
      document.getElementById('resetCode').value = '';
      document.getElementById('resetCode').focus();
    } else {
      showErrors(msg, '送信に失敗しました', null);
    }
  } finally {
    btn.classList.remove('is-loading'); btn.disabled = false;
  }
});

/* Reset: Step2 コード検証 */
document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('#btnVerifyCode');
  if (!btn) return;
  const codeEl = document.getElementById('resetCode');
  const msg = document.getElementById('resetMsg2');
  const code = (codeEl.value || '').trim();
  if(!/^\d{6}$/.test(code)){
    showErrors(msg, '6桁の数字を入力してください', {resetCode:'6桁の数字のみ有効です'});
    return;
  }
  btn.classList.add('is-loading'); btn.disabled = true;
  try {
    const {ok, json} = await post('/pointcard/auth/api/verify_code.php', {code});
    if (ok && json && json.ok) {
      window.__reset_token = json.reset_token;
      msg.innerHTML = '<span class="ok">コードを確認しました。新しいパスワードを設定してください。</span>';
      document.getElementById('resetStep2').hidden = true;
      document.getElementById('resetStep3').hidden = false;
      const np = document.getElementById('resetNewPass'); if (np) { np.value=''; np.focus(); }
    } else {
      showErrors(msg, (json && json.error) || 'コード確認に失敗しました', json && json.errors);
    }
  } finally {
    btn.classList.remove('is-loading'); btn.disabled = false;
  }
});

/* Reset: Step3 新パス設定 */
document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('#btnSetNewPass');
  if (!btn) return;
  const passEl = document.getElementById('resetNewPass');
  const msg = document.getElementById('resetMsg3');
  const new_password = passEl.value || '';
  if (new_password.length < 8) {
    showErrors(msg, 'パスワードは8文字以上にしてください', {resetNewPass:'8文字以上が必要です'});
    return;
  }
  btn.classList.add('is-loading'); btn.disabled = true;
  try {
    const token = window.__reset_token || '';
    const {ok, json} = await post('/pointcard/auth/api/set_password.php', {reset_token: token, new_password});
    if (ok && json && json.ok) {
      msg.innerHTML = '<span class="ok">パスワードを更新しました。ログインしてください。</span>';
      setTimeout(()=>{ location.href = '/pointcard/login.php'; }, 800);
    } else {
      showErrors(msg, (json && json.error) || '更新に失敗しました', json && json.errors);
    }
  } finally {
    btn.classList.remove('is-loading'); btn.disabled = false;
  }
});
</script>
</body>
</html>
