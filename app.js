/* ========= 小ユーティリティ ========= */
const $ = (sel, root=document) => root.querySelector(sel);
const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

/* ========= 効果音（軽量） ========= */
const AudioFX = (() => {
  let ctx;
  function ensureCtx(){ if(!ctx) ctx = new (window.AudioContext||window.webkitAudioContext)(); return ctx; }
  function beep(f=440, d=0.12, type='sine', v=0.12){
    const c = ensureCtx(); const o = c.createOscillator(); const g = c.createGain();
    o.type = type; o.frequency.value = f;
    o.connect(g); g.connect(c.destination);
    const t = c.currentTime; g.gain.setValueAtTime(v, t); g.gain.exponentialRampToValueAtTime(0.0001, t + d);
    o.start(t); o.stop(t + d);
  }
  function seq(steps){
    let delay = 0;
    steps.forEach(s=>{
      setTimeout(()=>beep(s.f, s.d ?? 0.1, s.type || 'sine', s.v ?? 0.12), delay*1000);
      delay += (s.wait ?? (s.d ?? 0.1));
    });
  }
  function stampThump(){ seq([{f:160,d:0.07,type:'square',v:0.2},{f:110,d:0.1,type:'sine',v:0.16,wait:0.02}]); }
  return {beep, seq, stampThump};
})();

/* ========= じゃんけん ========= */
(function(){
  const area = document.getElementById('view-game');
  if(!area) return;

  let autoReturnTid = null;
  window.__cancelAutoReturn = () => {
    if (autoReturnTid) { clearTimeout(autoReturnTid); autoReturnTid = null; }
  };

  const resultEl = $('#rpsResult', area);
  const countEl  = $('#rpsCount', area);
  const handU    = $('#handUser', area);
  const handC    = $('#handCpu', area);
  const badge    = $('#rpsBadge', area);
  const ctrl     = $('.rps-ctrl', area);
  if(!ctrl||!handU||!handC) return;

  const buttons = $$('[data-rps]', ctrl);
  const hands = ["グー","チョキ","パー"];
  const icons = { "グー":"✊", "チョキ":"✌️", "パー":"🖐️" };
  let playing=false, shuffleTimer=null, gameLocked=false;

  function setButtons(disabled){ buttons.forEach(b=> b.disabled = disabled); }
  function judge(u,c){
    if(u===c) return "draw";
    if((u==="グー"&&c==="チョキ")||(u==="チョキ"&&c==="パー")||(u==="パー"&&c==="グー")) return "win";
    return "lose";
  }
  function startShuffle(){
    const seq=["✊","🖐️","✌️"]; let i=0;
    handC.style.animation="shake .6s ease infinite";
    shuffleTimer=setInterval(()=>{ handC.textContent=seq[i++%seq.length]; },100);
  }
  function stopShuffle(){ handC.style.animation="none"; clearInterval(shuffleTimer); shuffleTimer=null; }
  function showBadge(type,text){
    badge.className=`rps-badge ${type} show`;
    badge.textContent=text;
    setTimeout(()=>badge.classList.remove('show'),1200);
  }
  function floatBonus(n){
    const host=$('.rps-row', area);
    const b=document.createElement('div');
    b.className='float-bonus'; b.textContent=`+${n}`;
    host.style.position='relative'; host.appendChild(b);
    requestAnimationFrame(()=> b.classList.add('animate'));
    setTimeout(()=> b.remove(),900);
  }

  area.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-rps]');
    if(!btn||playing||gameLocked) return;
    AudioFX.beep(520,0.08,'square',0.08);
    playing = true; setButtons(true); badge.classList.remove('show');

    const user = btn.getAttribute('data-rps');
    handU.textContent = icons[user];

    const cue = ["3","2","1","じゃんけん…","ぽん！"];
    handC.textContent="🖐️";
    startShuffle();
    for(let i=0;i<cue.length;i++){
      countEl.textContent = cue[i];
      if(i<3){ AudioFX.beep(680,0.1,'sine',0.12); }
      else if(i===3){ AudioFX.beep(440,0.12,'triangle',0.12); }
      else { AudioFX.beep(920,0.18,'square',0.14); }
      await new Promise(r=>setTimeout(r, i<3 ? 300 : 450));
    }
    stopShuffle();
    const cpu = hands[Math.floor(Math.random()*hands.length)];
    handC.textContent = icons[cpu];

    const res = judge(user,cpu);
    const jp  = res==="win"?"勝ち":res==="draw"?"あいこ":"負け";
    const add = res==="win"?3:res==="draw"?2:1;

    const granted = (window.__awardGameStamps ? window.__awardGameStamps(add) : 0);
    resultEl.textContent = `あなた: ${user} ／ アプリ: ${cpu} → ${jp}！ スタンプ ${granted} 個付与（印影：「ゲーム」）。`;

    if(res==="win"){ showBadge('win','WIN!'); AudioFX.seq([{f:880,d:0.1},{f:1047,d:0.1},{f:1319,d:0.16}]); }
    else if(res==="draw"){ showBadge('draw','DRAW'); AudioFX.beep(600,0.14,'sine',0.12); }
    else { showBadge('lose','LOSE'); AudioFX.seq([{f:300,d:0.14},{f:220,d:0.18,wait:0.12}]); }

    floatBonus(granted);

    // ロック＆スタンプページへ誘導（21秒後自動）
    gameLocked = true; setButtons(true);
    const goSheet = document.getElementById('goStampSheet');
    goSheet && goSheet.classList.add('is-open');
    window.__startGoCountdown && window.__startGoCountdown();

    window.__cancelAutoReturn?.();
    autoReturnTid = setTimeout(()=>{
      window.__goToStamp && window.__goToStamp(true);
      autoReturnTid = null;
    }, 21000);

    await new Promise(r=>setTimeout(r,900));
    countEl.textContent=""; playing=false;
  });

  window.__resetGame = function(){
    gameLocked = false; playing = false; setButtons(false);
    $('#rpsResult', area).textContent = "手をえらんでください。";
    $('#rpsCount', area).textContent = "";
    const b = $('#rpsBadge', area); b && b.classList.remove('show');
    handU.textContent = "🖐️"; handC.textContent = "🖐️";
  };
})();

/* ========= Stamp grid + 永続化 ========= */
(function(){
  const grid = document.getElementById('grid');
  if(!grid) return;

  const TOTAL = 1000, PER_PAGE = 20;

  const PAGES_OFFICIAL = Math.ceil(TOTAL/PER_PAGE);

  const STAMP_KEY='stampsV1';            // 各マス："" / "龍" / "ゲーム"
  const REDEEM_USED_KEY='redeemUsedV1';  // 何個分まで消費済みか（1-based）

  let stamps = new Array(TOTAL).fill("");
  try{
    const raw = localStorage.getItem(STAMP_KEY);
    if(raw){ const arr = JSON.parse(raw); if(Array.isArray(arr) && arr.length===TOTAL) stamps = arr; }
  }catch{}

  let redeemedUsed = parseInt(localStorage.getItem(REDEEM_USED_KEY)||'0',10) || 0;
  let filled = stamps.reduce((a,b)=> a + (b ? 1 : 0), 0);
  let page= Math.max(1, Math.ceil(Math.max(filled,1)/PER_PAGE));
  let lastHighlights = [];
  let lastGameSet = new Set();

  const bar=document.getElementById('bar'), cnt=document.getElementById('count');
  const barTop=document.getElementById('barTop'), cntTop=document.getElementById('countTop');
  const prev=document.getElementById('prevPage'), next=document.getElementById('nextPage');
  const pageNow=document.getElementById('pageNow'), pageTotal=document.getElementById('pageTotal');
  pageTotal && (pageTotal.textContent=String(PAGES_OFFICIAL));

  function saveStamps(){ try{ localStorage.setItem(STAMP_KEY, JSON.stringify(stamps)); }catch{} }
  function saveRedeemed(){ localStorage.setItem(REDEEM_USED_KEY, String(redeemedUsed)); }

  function updateProgress(){
    const ratio=(filled/TOTAL)*100;
    if(barTop) barTop.style.width = `${ratio}%`;
    if(cntTop) cntTop.textContent = `${filled}/${TOTAL}`;
    if(bar) bar.style.width = `${ratio}%`;
    if(cnt) cnt.textContent = `${filled}/${TOTAL}`;
  }

  function renderPage(highlight=[], gameSet=new Set()){
    const startIndex=(page-1)*PER_PAGE, endIndex=Math.min(startIndex+PER_PAGE, TOTAL);
    let html='';
    for(let i=startIndex;i<endIndex;i++){
      const idx1 = i+1;
      const ink = stamps[i];                           // "" / "龍" / "ゲーム"
      const isFilled = !!ink;
      const isGame = (ink === "ゲーム");
      const isConsumed = (idx1 <= redeemedUsed);
      const inkClass = isGame ? 'ink ink--game' : 'ink';
      html += `
        <div class="stamp ${isFilled?'is-filled':''} ${isConsumed?'is-consumed':''} ${isGame?'is-game':''}" data-idx="${idx1}">
          <span class="stamp__label">${idx1}</span>
          <span class="${inkClass}">${ink||''}</span>
          <i class="strike"></i>
        </div>`;
    }
    grid.innerHTML=html;
    pageNow && (pageNow.textContent=String(page));
    prev && (prev.disabled=page<=1);
    next && (next.disabled=page>=PAGES_OFFICIAL);

    if(highlight.length){
      const set = new Set(highlight);
      Array.from(grid.children).forEach(cell=>{
        const idx = parseInt(cell.getAttribute('data-idx'),10);
        if(set.has(idx)){
          if(gameSet && gameSet.has(idx)) cell.classList.add('is-game');
          void cell.offsetWidth;
          cell.classList.add('is-just-stamped');
          setTimeout(()=>{ cell.classList.remove('is-just-stamped'); cell.classList.remove('is-game'); }, 900);
        }
      });
      AudioFX.stampThump();
    }
  }

  function awardStamps(n, ink){
    let granted=0; const highlights=[]; const gameSet=new Set();
    while(granted<n && filled<TOTAL){
      stamps[filled]=ink; granted++;
      const idx1based=filled+1;
      highlights.push(idx1based);
      if(ink==="ゲーム") gameSet.add(idx1based);
      filled++;
    }
    lastHighlights = highlights.slice();
    lastGameSet = gameSet;
    const newPage=Math.ceil(Math.max(filled,1)/PER_PAGE);
    if(newPage!==page) page=newPage;
    updateProgress(); renderPage(highlights, gameSet); saveStamps();
    return granted;
  }

  function availableStamps(){ return Math.max(0, filled - redeemedUsed); }

  function buildRedeemOptions(){
    const avail = availableStamps();
    $('#availStamps').textContent = String(avail);
    const host = $('#redeemOptions');
    const chunks = [];
    const add = (title, cost, choices) => {
      const enabled = avail >= cost;
      if(!choices || choices.length===1){
        const label = choices ? choices[0] : title;
        chunks.push(`
          <label style="display:flex;gap:8px;align-items:center;opacity:${enabled?1:.5}">
            <input type="radio" name="redeem" value="${cost}::${label}" ${enabled?'':'disabled'}>
            <span><strong>${label}</strong>（${cost}スタンプ）</span>
          </label>`);
      }else{
        const inner = choices.map(c=>`<option value="${c}">${c}</option>`).join('');
        chunks.push(`
          <div style="display:grid;gap:6px;opacity:${enabled?1:.5}">
            <label style="display:flex;gap:8px;align-items:center;">
              <input type="radio" name="redeem" value="${cost}::" ${enabled?'':'disabled'}>
              <span><strong>${title}</strong>（${cost}スタンプ）</span>
            </label>
            <select name="choice_${cost}" ${enabled?'':'disabled'} style="padding:8px 10px;border:1px solid var(--border);border-radius:10px">
              ${inner}
            </select>
          </div>`);
      }
    };
    add("300円割引券",7,["300円割引券"]);
    add("選択",14,["300円割引券","餃子1人前","チャーシュウ"]);
    add("400円割引券",21,["400円割引券"]);
    add("選択",28,["400円割引券","餃子1人前","チャーシュウ"]);
    add("500円割引券",35,["500円割引券"]);
    add("選択",42,["500円割引券","餃子一人前","チャーシュウ"]);
    add("特典",50,["1000円割引券","シルバー会員証へアップグレード"]);

    host.innerHTML = chunks.join('');

    const doBtn = $('#doRedeem');
    const msg = $('#redeemMsg');
    doBtn.disabled = true; msg.textContent = "";
    host.addEventListener('change', ()=> {
      const checked = host.querySelector('input[type="radio"][name="redeem"]:checked');
      doBtn.disabled = !checked;
    }, {once:true});
  }

  function addDynamicCoupon(label, cost){
    const DYN_KEY='dynCouponsV1';
    const dyn = (()=>{ try{ const a=JSON.parse(localStorage.getItem(DYN_KEY)||'[]'); return Array.isArray(a)?a:[]; }catch{return [];} })();
    const id = `DYN-${Date.now().toString(36)}-${Math.random().toString(36).slice(2,6).toUpperCase()}`;
    const code = `${label.replace(/\s/g,'').slice(0,6).toUpperCase()}-${Math.random().toString(36).slice(2,6).toUpperCase()}`;
    dyn.push({ id, title: label, desc:`スタンプ${cost}個と引換`, until:'発行日から30日', code });
    localStorage.setItem('dynCouponsV1', JSON.stringify(dyn));
  }

  function confirmRedeem(){
    const host = $('#redeemOptions');
    const radios = host.querySelectorAll('input[type="radio"][name="redeem"]');
    let chosen=null, cost=0, label="";
    radios.forEach(r=>{
      if(r.checked){
        const [c, base] = r.value.split("::");
        cost = parseInt(c,10);
        label = base || "";
        if(!label){
          const wrap = r.closest('div');
          const sel = wrap ? wrap.querySelector('select') : null;
          label = sel ? sel.value : "";
        }
        chosen = r;
      }
    });
    if(!chosen || !label) return;

    if(Math.max(0,filled - redeemedUsed) < cost){
      $('#redeemMsg').textContent = "スタンプ数が不足しています。";
      return;
    }

    const prevConsumed = redeemedUsed;
    const newConsumed = redeemedUsed + cost;
    redeemedUsed = newConsumed; saveRedeemed();

    $('#redeemMsg').textContent = `${label} のクーポンを発行しました。クーポン一覧をご確認ください。`;
    addDynamicCoupon(label, cost);
    renderCoupons();
    renderPage();

    const startIndex=(page-1)*PER_PAGE, endIndex=Math.min(startIndex+PER_PAGE, TOTAL);
    for(let i=Math.max(prevConsumed+1, startIndex+1); i<=Math.min(newConsumed, endIndex); i++){
      const cell = grid.querySelector(`.stamp[data-idx="${i}"]`);
      if(cell){
        cell.classList.add('consume-anim');
        setTimeout(()=> cell.classList.remove('consume-anim'), 900);
      }
    }

    buildRedeemOptions();
  }

  document.getElementById('stampDemo')?.addEventListener('click', ()=>{
    if(filled>=TOTAL) return;
    awardStamps(1,"龍");
  });
  /* iOS Safari: double-tap で拡大されるのを個別に抑止（2回目だけキャンセル） */
  (function(){
    const btn = document.getElementById('stampDemo');
    if(!btn) return;
    let lastTouch = 0;
    btn.addEventListener('touchend', (e)=>{
      const now = e.timeStamp || Date.now();
      // 350ms以内の連続タップは “第二回目” とみなして既定動作（拡大/ダブルタップズーム）を止める
      if(now - lastTouch < 350){
        e.preventDefault(); // 合成clickも発火しない→二重実行や拡大が防げる
      }
      lastTouch = now;
    }, {passive:false});
  })();

  prev?.addEventListener('click', ()=>{ if(page>1){ page--; renderPage(); } });
  next?.addEventListener('click', ()=>{ if(page<PAGES_OFFICIAL){ page++; renderPage(); } });

  updateProgress(); renderPage();

  window.__awardGameStamps = (n)=> awardStamps(n, "ゲーム");
  window.__replayStampEffect = function(){
    if(!lastHighlights.length) return;
    const cells = Array.from(grid.children);
    const cellByIdx = new Map(cells.map(c => [parseInt(c.getAttribute('data-idx'), 10), c]));
    const startDelay = 900, stepDelay = 140, effectHold = 1000;
    lastHighlights.forEach((idx, k) => {
      const cell = cellByIdx.get(idx); if(!cell) return;
      setTimeout(()=>{
        if(lastGameSet && lastGameSet.has(idx)) cell.classList.add('is-game');
        void cell.offsetWidth;
        cell.classList.add('is-just-stamped');
        if(k===0){ AudioFX.stampThump(); }
        setTimeout(()=>{ cell.classList.remove('is-just-stamped'); cell.classList.remove('is-game'); }, effectHold);
      }, startDelay + k * stepDelay);
    });
  };

  const redeemSheet = document.getElementById('redeemSheet');
  document.getElementById('openRedeem')?.addEventListener('click', ()=>{ buildRedeemOptions(); redeemSheet.classList.add('is-open'); });
  document.getElementById('closeRedeem')?.addEventListener('click', ()=> redeemSheet.classList.remove('is-open'));
  document.getElementById('cancelRedeem')?.addEventListener('click', ()=> redeemSheet.classList.remove('is-open'));
  document.getElementById('doRedeem')?.addEventListener('click', confirmRedeem);
})();

/* ========= Game -> Stamp シート（21秒） ========= */
(function(){
  const sheet=document.getElementById('goStampSheet'), btnGo=document.getElementById('goStampNow'), remainEl=document.getElementById('remainSec');
  let countdownTimer=null, remain=21;
  function updateRemain(){ remainEl && (remainEl.textContent=String(remain)); }
  function startGoCountdown(){
    if(countdownTimer) clearInterval(countdownTimer);
    remain=21; updateRemain();
    countdownTimer=setInterval(()=>{
      remain=Math.max(0,remain-1); updateRemain();
      if(remain<=0){ clearInterval(countdownTimer); countdownTimer=null; }
    },1000);
  }
  function goToStamp(replay=false){
    window.__cancelAutoReturn && window.__cancelAutoReturn();
    if(countdownTimer){ clearInterval(countdownTimer); countdownTimer=null; }
    sheet.classList.remove('is-open');
    /* ★スタンプタブ廃止→ホームタブへ */
    const homeTab=document.querySelector('.tabs .tab[data-target="home"]');
    if(homeTab){
      homeTab.dispatchEvent(new Event('click',{bubbles:true}));
      setTimeout(()=>{
        document.getElementById('grid')?.scrollIntoView({behavior:'smooth',block:'start'});
        if(replay){ window.__replayStampEffect?.(); }
      }, 120);
    }
  }
  window.__startGoCountdown=startGoCountdown;
  window.__goToStamp=goToStamp;
  btnGo?.addEventListener('click', ()=> goToStamp(true));
})();

/* ========= Tabs ========= */
(function(){
  const views={
    home:document.getElementById('view-home'),
    coupon:document.getElementById('view-coupon'),
    member:document.getElementById('view-member'),
    menu:document.getElementById('view-menu'),
    game:document.getElementById('view-game')
  };
  $$('.tab').forEach(t=>{
    // ★ 実遷移が必要なリンク（ログアウトなど）は阻害しない
    if (t.hasAttribute('data-hardlink')) return;
    t.addEventListener('click',(e)=>{
      e.preventDefault();
      $$('.tab').forEach(x=>x.classList.remove('is-active'));
      t.classList.add('is-active');
      const target=t.getAttribute('data-target');
      Object.values(views).forEach(v=>v&&v.classList.remove('is-active'));
      if(target && views[target]) views[target].classList.add('is-active');
      if(target==='game'){ window.__resetGame?.(); }
      window.scrollTo({top:0,behavior:'smooth'});
    });
  });
})();

/* ========= Nickname & Member No & QR/Barcode ========= */
(function(){
  const defaultName='あなた';
  const input = document.getElementById('nicknameInput');
  const btn = document.getElementById('saveNickname');
  const stat = document.getElementById('nicknameStatus');
  const honor = document.getElementById('nickHonor');
  const memNoEl = document.getElementById('memNo');
  const qr = document.getElementById('qrCanvas');
  const bc = document.getElementById('barCanvas');

  function hash32(str){ let h=0x811c9dc5; for(let i=0;i<str.length;i++){ h ^= str.charCodeAt(i); h = (h + ((h<<1)+(h<<4)+(h<<7)+(h<<8)+(h<<24)))>>>0; } return h>>>0; }

  function drawQR(text){
    if(!qr) return;
    const ctx = qr.getContext('2d'); const size = qr.width;
    ctx.clearRect(0,0,size,size); ctx.fillStyle = '#fff'; ctx.fillRect(0,0,size,size);
    const h = hash32(text); const n = 25; const cell = Math.floor(size/(n+2));
    for(let y=0;y<n;y++){
      for(let x=0;x<n;x++){
        const bit = (((h + x*73856093 + y*19349663) >>> 1) & 1) ^ ((x*y + 31) & 1);
        if(bit){ ctx.fillStyle = '#111'; ctx.fillRect((x+1)*cell,(y+1)*cell,cell,cell); }
      }
    }
    ctx.strokeStyle='#111'; ctx.lineWidth=2;
    const fp = (ox,oy)=>{ ctx.strokeRect((ox+1)*cell,(oy+1)*cell,cell*7,cell*7); ctx.strokeRect((ox+2)*cell,(oy+2)*cell,cell*5,cell*5); ctx.fillStyle='#111'; ctx.fillRect((ox+3)*cell,(oy+3)*cell,cell*3,cell*3); };
    fp(0,0); fp(n-7,0); fp(0,n-7);
  }

  function drawBarcode(text){
    if(!bc) return;
    const ctx = bc.getContext('2d'); const w = bc.width, h = bc.height;
    ctx.clearRect(0,0,w,h); ctx.fillStyle='#fff'; ctx.fillRect(0,0,w,h);
    const bits = []; let seed = hash32(text);
    for(let i=0;i<200;i++){ seed = (seed * 1664525 + 1013904223)>>>0; bits.push(seed & 1); }
    const barW = Math.max(1, Math.floor(w/bits.length)); let x=0;
    bits.forEach(b=>{ ctx.fillStyle = b ? '#000' : '#fff'; ctx.fillRect(x,0,barW,h); x+=barW; });
    ctx.strokeStyle='rgba(0,0,0,.2)'; ctx.strokeRect(0,0,w,h);
  }

  async function loadProfile(){
    try{
      const r = await fetch('/pointcard/auth/whoami.php', {cache:'no-store'});
      const j = await r.json();
      if(!j || !j.ok || !j.logged_in) return;
      const memberNo = j.member_no || String(j.user_id || '').padStart(4,'0');
      const nick = (j.nickname || defaultName).slice(0, 64);
      const honorTxt = (nick || defaultName) + ' 様';  // ★JSでは切り詰めない→CSSで省略
      honor && (honor.textContent = honorTxt);
      memNoEl && (memNoEl.textContent = memberNo);
      drawQR(memberNo);
      drawBarcode(memberNo);
      input && (input.value = nick);
    }catch(_){}
  }

  async function saveNickname(){
    const v = (input?.value || '').trim() || defaultName;
    try{
      const res = await fetch('/pointcard/auth/api/set_nickname.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ nickname: v })
      });
      const j = await res.json();
      if(res.ok && j && j.ok){
        const nick = (j.nickname || v).slice(0,64);
        honor && (honor.textContent = (nick.slice(0,10) || defaultName) + ' 様');
        stat && (stat.textContent = '変更しました。');
      }else{
        stat && (stat.textContent = j?.error || '変更に失敗しました');
      }
    }catch(_){
      stat && (stat.textContent = '通信エラーが発生しました');
    }
    setTimeout(()=> stat && (stat.textContent = ''), 1500);
  }

  loadProfile();
  btn?.addEventListener('click', saveNickname);
})();

/* ========= Coupons ========= */
(function(){
  const staticCoupons = [
    { id:'CP10OFF',  title:'10%OFF クーポン',   desc:'税込1,000円以上で10%OFF', until:'2025/12/31', code:'CP10-ABCD' },
    { id:'FREEDRINK',title:'ドリンク1杯無料',    desc:'フード1品以上のご注文で',  until:'2025/10/31', code:'DRK1-FREE' },
    { id:'POINT2X',  title:'ポイント2倍',        desc:'本日限定のポイントブースト', until:'本日限り',   code:'2X-POINT' }
  ];
  const DYN_KEY='dynCouponsV1';
  function loadDynamicCoupons(){ try{ const a=JSON.parse(localStorage.getItem(DYN_KEY)||'[]'); return Array.isArray(a)?a:[]; }catch{ return []; } }
  function allCouponsForRender(){ return [...staticCoupons, ...loadDynamicCoupons()]; }

  const usedInSession = new Set();
  const couponList=document.getElementById('couponList');

  function renderCoupons(){
    if(!couponList) return;
    const list = allCouponsForRender().filter(c=> !usedInSession.has(c.id));
    couponList.innerHTML = list.length
      ? list.map(c => `
        <div class="coupon" data-id="${c.id}">
          <h3>${c.title}</h3>
          <div class="meta">${c.desc}／有効期限：${c.until}</div>
          <div class="cta"><button class="btn" data-open="${c.id}">使用する</button></div>
        </div>`).join('')
      : `<p class="muted">使用できるクーポンはありません。</p>`;
  }
  renderCoupons();

  let currentCouponId = null;
  const sheet = document.getElementById('couponSheet');
  $('#closeSheet')?.addEventListener('click', ()=>{ sheet.classList.remove('is-open'); currentCouponId=null; });
  $('#skipCp')?.addEventListener('click', ()=>{ sheet.classList.remove('is-open'); currentCouponId=null; });
  $('#useNow')?.addEventListener('click', ()=>{ $('#cpConfirm').style.display='block'; });
  $('#confirmUseNo')?.addEventListener('click', ()=>{ $('#cpConfirm').style.display='none'; });

  couponList?.addEventListener('click',(e)=>{
    const btn=e.target.closest('[data-open]'); if(!btn) return;
    const id=btn.getAttribute('data-open');
    const item=allCouponsForRender().find(x=>x.id===id); if(!item) return;
    currentCouponId = id;
    $('#cpTitle').textContent=item.title;
    $('#cpDesc').textContent=item.desc+'／有効期限：'+item.until;
    $('#cpCode').textContent=item.code;
    $('#cpConfirm').style.display='none';
    sheet.classList.add('is-open');
  });

  $('#confirmUseYes')?.addEventListener('click',()=>{
    if(!currentCouponId) return;
    usedInSession.add(currentCouponId);
    const card = couponList.querySelector(`.coupon[data-id="${currentCouponId}"]`);
    if(card){
      const h = card.getBoundingClientRect().height;
      card.style.height = h+'px';
      requestAnimationFrame(()=>{
        card.classList.add('is-removing'); card.style.height = '0px';
      });
      setTimeout(()=>{
        card.remove();
        if(!couponList.children.length){
          couponList.innerHTML = `<p class="muted">使用できるクーポンはありません。</p>`;
        }
      }, 420);
    }
    sheet.classList.remove('is-open'); currentCouponId=null;
  });

  window.renderCoupons = renderCoupons;
})();

/* ========= Menu ========= */
(function(){
  const menuGrid=document.getElementById('menuGrid');
  const menuItems=[
    {name:'ざる中華そば中',price:750,img:'./assets/zarusoba-m.png',desc:'コシのある麺を特製つけだれで。'},
    {name:'ざる中華そば大',price:850,img:'./assets/zarusoba-l.png',desc:'ボリューム満点のざる大盛り。'},
    {name:'にぼし中華そば中',price:750,img:'./assets/niboshi-m.png',desc:'煮干し香るしっかりスープ。'},
    {name:'にぼし中華そば大',price:800,img:'./assets/niboshi-l.png',desc:'煮干し好きにおすすめの大盛り。'},
    {name:'背脂中華そば大',price:850,img:'./assets/seabura-m.png',desc:'背脂のコクがクセになる。'},
    {name:'背脂中華そば中',price:950,img:'./assets/seabura-l.png',desc:'背脂×中太麺の黄金比。'}
  ];
  if(!menuGrid) return;
  menuGrid.innerHTML = menuItems.map((i,idx)=>`
    <div class="menu-item" data-menu="${idx}">
      <img class="menu-thumb" src="${i.img}" alt="${i.name}" onerror="this.src='https://via.placeholder.com/136?text=NO+IMG'"/>
      <div class="menu-meta"><h4>${i.name}</h4><div class="muted">おすすめ</div></div>
      <div class="price">¥${i.price.toLocaleString()}</div>
    </div>`).join('');

  const menuSheet = document.getElementById('menuSheet');
  const menuBody  = document.getElementById('menuBody');
  const menuTitle = document.getElementById('menuTitle');

  menuGrid.addEventListener('click', (e)=>{
    const card = e.target.closest('[data-menu]'); if(!card) return;
    const idx = parseInt(card.getAttribute('data-menu'),10);
    const item = menuItems[idx]; if(!item) return;
    menuTitle.textContent = item.name;
    menuBody.innerHTML = `
      <img src="${item.img}" alt="${item.name}" style="width:100%;height:auto;border-radius:12px;border:1px solid var(--border);margin-bottom:10px" onerror="this.src='https://via.placeholder.com/800x450?text=${encodeURIComponent(item.name)}'"/>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <strong style="font-size:18px">¥${item.price.toLocaleString()}</strong>
        <span class="muted">税込</span>
      </div>
      <p class="muted" style="margin-top:8px">${item.desc}</p>`;
    menuSheet.classList.add('is-open');
  });
  document.getElementById('closeMenuSheet')?.addEventListener('click', ()=> menuSheet.classList.remove('is-open'));
})();

/* ========= Slider Loader (px-based, stable) ==========
   HTML側の <section class="carousel" data-api="/pointcard/api/slider.php" data-store="1"> を前提 */
let currentSlide = 0;
let autoTimer = null;

const getSlides = () => Array.from(document.querySelectorAll('#track .slide'));
const getDots   = () => Array.from(document.querySelectorAll('#dots .dot'));

function trackTo(xPx){
  const track = document.getElementById('track');
  if(!track) return;
  track.style.transform = `translateX(${xPx}px)`;
}

function slideWidth(){
  const carousel = document.querySelector('.carousel');
  return carousel ? Math.round(carousel.clientWidth) : 0; // 1枚=表示幅(px)
}

function goToSlide(n, {animate=true} = {}){
  const track  = document.getElementById('track');
  const slides = getSlides();
  if(!track || !slides.length) return;

  track.style.transition = animate ? 'transform .5s ease' : 'none';

  currentSlide = (n + slides.length) % slides.length;
  const offset = - currentSlide * slideWidth(); // 1枚ぶん
  trackTo(offset);

  getDots().forEach((d,i)=> d.classList.toggle('is-active', i===currentSlide));
}

function startAutoplay(){
  stopAutoplay();
  autoTimer = setInterval(()=> goToSlide(currentSlide + 1), 5000);
}
function stopAutoplay(){
  if(autoTimer){ clearInterval(autoTimer); autoTimer = null; }
}

function setupSwipe(){
  const track = document.getElementById('track');
  if(!track) return;

  let startX = 0, dx = 0, dragging = false;

  track.addEventListener('pointerdown', (e)=>{
    dragging = true; startX = e.clientX; dx = 0;
    track.style.transition = 'none';
    stopAutoplay();
  });
  window.addEventListener('pointermove', (e)=>{
    if(!dragging) return;
    dx = e.clientX - startX;
    const base = - currentSlide * slideWidth();
    trackTo(base + dx);
  });
  window.addEventListener('pointerup', ()=>{
    if(!dragging) return;
    dragging = false;
    const SWIPE_THRESHOLD = 50; // px
    if(Math.abs(dx) > SWIPE_THRESHOLD){
      goToSlide(currentSlide + (dx < 0 ? 1 : -1));
    }else{
      goToSlide(currentSlide, {animate:true});
    }
    dx = 0;
    startAutoplay();
  });
}

function handleResize(){
  goToSlide(currentSlide, {animate:false});
}

function initCarousel(){
  goToSlide(0, {animate:false});
  setupSwipe();
  startAutoplay();
  window.addEventListener('resize', handleResize);
}

async function loadSliderFromAPI() {
  const carousel = document.querySelector('.carousel');
  if (!carousel) return;

  const apiUrl  = carousel.dataset.api || '/pointcard/api/slider.php';
  const storeId = parseInt(carousel.dataset.store || '0', 10);

  const track = document.getElementById('track');
  const dots  = document.getElementById('dots');

  const fallback = [
    {src:'./assets/slider-1.png', alt:'季節のおすすめ1'},
    {src:'./assets/slider-2.png', alt:'季節のおすすめ2'},
    {src:'./assets/slider-3.png', alt:'季節のおすすめ3'},
  ];

  let slides = [];
  try {
    if (!storeId) throw new Error('store_id missing');
    const url = new URL(apiUrl, location.origin);
    url.searchParams.set('store_id', String(storeId));
    const res = await fetch(url.toString(), { cache: 'no-store' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'api error');
    slides = (json.slides || []).filter(s => s && s.src);
  } catch (e) {
    console.warn('[slider] API error -> fallback', e);
  }
  if (slides.length === 0) slides = fallback;

  track.innerHTML = '';
  dots.innerHTML  = '';

  slides.forEach((s, idx) => {
    const slide = document.createElement('div');
    slide.className = 'slide';
    slide.setAttribute('aria-label', `${idx+1} / ${slides.length}`);

    const img = document.createElement('img');
    img.src = s.src;
    img.alt = s.alt || `Slide ${idx+1}`;
    img.onerror = function(){ this.src = `https://via.placeholder.com/800x450?text=Slide+${idx+1}`; };

    if (s.href) {
      const a = document.createElement('a');
      a.href = s.href; a.target = '_blank'; a.rel = 'noopener';
      a.appendChild(img);
      slide.appendChild(a);
    } else {
      slide.appendChild(img);
    }
    track.appendChild(slide);

    const dot = document.createElement('button');
    dot.type = 'button';
    dot.className = 'dot';
    dot.setAttribute('aria-label', `${idx+1}枚目へ`);
    dot.addEventListener('click', () => goToSlide(idx));
    dots.appendChild(dot);
  });

  initCarousel();
}

document.addEventListener('DOMContentLoaded', () => {
  loadSliderFromAPI();
});
