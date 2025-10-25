<?php
require_once __DIR__ . '/auth/require_login.php';
?>

<script>
// 起動時にユーザー情報を反映（任意）
(async () => {
  try {
    const r = await fetch('/pointcard/auth/whoami.php');
    const j = await r.json();
    if (j && j.ok && j.logged_in) {
      const el = document.getElementById('nickHonor');
      if (el && j.nickname) el.textContent = `${j.nickname} 様`;
    }
  } catch (_) {}
})();
</script>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>来店ポイントアプリ｜UIモック</title>

  <!-- 👇 追加：ローカルのファビコンを明示（HTTP外部参照を回避） -->
  <link rel="icon" href="/pointcard/assets/logo80x80.ico" sizes="any" />
  <link rel="shortcut icon" href="/pointcard/assets/logo80x80.ico" />

  <!-- 👇 追加：CSPで混在コンテンツを封じる（インラインJS/CSSは許可） -->
  <meta http-equiv="Content-Security-Policy"
        content="default-src 'self' https:; 
                 img-src 'self' data: https:; 
                 script-src 'self' 'unsafe-inline' https:; 
                 style-src  'self' 'unsafe-inline' https:; 
                 connect-src 'self' https:; 
                 font-src 'self' data: https:; 
                 object-src 'none'; 
                 base-uri 'self'; 
                 upgrade-insecure-requests">

  <link rel="stylesheet" href="./styles.css">
</head>
<body>
<div class="app" aria-label="来店ポイントアプリ">
  <!-- Header -->
  <header>
    <div class="header-inner">
      <div class="logo-wrap" aria-label="店舗ロゴ">
        <img class="logo" src="./assets/logo80x80.ico" alt="店舗ロゴ" />
        <div class="brand-txt">中華そば専門 龍</div>
      </div>
    </div>
  </header>

  <!-- ===== HOME VIEW ===== -->
<main id="view-home" class="view is-active" aria-label="ホーム">
  <section
    class="carousel"
    aria-roledescription="carousel"
    aria-label="おすすめ情報"
    data-api="/pointcard/api/slider.php"
    data-store="1"
  >
    <div class="track" id="track"></div>
    <div class="dots" aria-hidden="true" id="dots"></div>
  </section>

    <!-- Stamp card -->
    <section class="section">
      <article class="card" aria-label="スタンプカード">
        <header class="card-hd">
          <h2>スタンプカード</h2>
          <button class="btn" type="button" id="stampDemo">スタンプを押す</button>
        </header>
        <div class="card-body">
          <!-- 上側プログレス -->
          <div class="progress" aria-live="polite">
            <span class="muted">達成まで</span>
            <div class="progress-bar" aria-hidden="true"><i id="barTop"></i></div>
            <strong id="countTop">0/1000</strong>
          </div>

          <div class="stamp-grid" id="grid" aria-label="スタンプ枠"></div>

          <!-- ページャー -->
          <div class="pager" id="pager" aria-label="スタンプページャー">
            <button class="btn-ghost" id="prevPage" type="button" aria-label="前のページ">‹‹ 前の20</button>
            <div class="pager-info"><strong id="pageNow">1</strong>/<span id="pageTotal">50</span></div>
            <button class="btn-ghost" id="nextPage" type="button" aria-label="次のページ">次の20 ››</button>
          </div>

          <!-- 下側プログレス -->
          <div class="progress" aria-live="polite">
            <span class="muted">達成まで</span>
            <div class="progress-bar" aria-hidden="true"><i id="bar"></i></div>
            <strong id="count">0/1000</strong>
          </div>

          <div class="redeem-wrap">
            <button class="btn" id="openRedeem">クーポンに引き換え</button>
          </div>
        </div>
      </article>
    </section>
  </main>

  <!-- ===== COUPON VIEW ===== -->
  <main id="view-coupon" class="view" aria-label="クーポン一覧">
    <section class="section">
      <article class="card">
        <header class="card-hd"><h2>クーポン</h2></header>
        <div class="card-body"><div class="coupon-list" id="couponList"></div></div>
      </article>
    </section>
  </main>

  <!-- Coupon Detail Sheet -->
  <aside class="sheet" id="couponSheet" role="dialog" aria-modal="true" aria-labelledby="cpTitle">
    <div class="sheet-hd">
      <h3 id="cpTitle">クーポン詳細</h3>
      <button class="btn" id="closeSheet" aria-label="閉じる">閉じる</button>
    </div>
    <div class="sheet-body">
      <p id="cpDesc" class="muted">説明</p>
      <div class="code" id="cpCode">ABCD-1234</div>

      <!-- 確認メッセージ -->
      <div id="cpConfirm" style="display:none; margin-top:12px; background:#f9fafb;border:1px solid var(--border);border-radius:12px;padding:12px">
        <p style="margin:0 0 10px">
          クーポンを利用しますか？<br>
          <strong>スタッフの確認がない場合はクーポンの利用が無効</strong>になりますのでご注意ください。
        </p>
        <div style="display:flex; gap:8px; flex-wrap:wrap">
          <button class="btn" id="confirmUseYes">はい、使用する</button>
          <button class="btn-ghost" id="confirmUseNo">いいえ</button>
        </div>
      </div>

      <div style="margin-top:12px;display:flex;gap:8px">
        <button class="btn" id="useNow">使用する</button>
        <button class="btn" style="background:#111827;color:#fff" id="skipCp">使わない</button>
      </div>
    </div>
  </aside>

  <!-- ===== MEMBER VIEW ===== -->
  <main id="view-member" class="view" aria-label="会員証">
    <section class="section">
      <article class="member">
        <div class="member-card">
          <div class="member-row">
            <div class="member-left">
              <div class="member-badge">MEMBER’S CARD</div>
              <div class="member-id">会員番号：<strong id="memNo">未発行</strong></div>
              <div id="nickHonor" class="nick-honor line-trim">あなた 様</div>
              <div class="member-shop">中華そば専門 龍</div>
            </div>
            <canvas id="qrCanvas" class="qr" width="120" height="120" aria-label="会員QRコード" style="flex:0 0 120px"></canvas>
          </div>

        <!-- ニックネーム設定 -->
        <article class="card" aria-label="ニックネーム設定">
          <header class="card-hd"><h2>ニックネーム</h2></header>
          <div class="card-body" style="display:grid;gap:10px">
            <p class="muted">会員証に表示するあなたのニックネームを設定できます。</p>
            <label for="nicknameInput" class="muted">ニックネーム</label>
            <input type="text" id="nicknameInput" maxlength="24" placeholder="あなた">
            <button class="btn" id="saveNickname">変更する</button>
            <p class="muted" id="nicknameStatus" aria-live="polite"></p>
          </div>
        </article>

        <!-- 保有ポイント -->
        <article class="card">
          <header class="card-hd"><h2>保有ポイント</h2><strong id="pt">1,250 pt</strong></header>
          <div class="card-body">
            <p class="muted">来店・購入・クーポン利用でポイントが貯まります。</p>
            <button class="btn" id="showRules">ポイントルール</button>
          </div>
        </article>

        <!-- 店舗情報 -->
        <article class="card" aria-label="店舗情報">
          <header class="card-hd"><h2>店舗情報</h2></header>
          <div class="card-body">
            <div style="display:grid;gap:6px">
              <div><strong>店舗名：</strong>中華そば専門　龍</div>
              <div><strong>住所：</strong>南相馬市原町区栄町２丁目６番地</div>
              <div><strong>営業時間：</strong>１１：００～１５：００／２０：００～２：００</div>
              <div><strong>定休日：</strong>月・木</div>
            </div>
          </div>
        </article>
      </article>
    </section>
  </main>

  <!-- ===== MENU VIEW ===== -->
  <main id="view-menu" class="view" aria-label="メニュー一覧">
    <section class="section">
      <article class="card">
        <header class="card-hd"><h2>メニュー</h2></header>
        <div class="card-body"><div class="menu-grid" id="menuGrid"></div></div>
      </article>
    </section>
  </main>

  <!-- ===== GAME VIEW ===== -->
  <main id="view-game" class="view" aria-label="ゲーム">
    <section class="section">
      <article class="card">
        <header class="card-hd"><h2>じゃんけんゲーム</h2></header>
        <div class="card-body" style="display:grid;gap:12px">
          <p class="muted">勝敗に応じてスタンプを付与します（勝=3／あいこ=2／負け=1）。付与分の印影は「ゲーム」です。</p>

          <!-- Arena -->
          <div class="rps-arena" id="rpsArena">
            <div class="rps-count" id="rpsCount"></div>
            <div class="rps-row">
              <div class="rps-col">
                <div class="rps-label">あなた</div>
                <div class="rps-hand" id="handUser" aria-label="あなたの手">🖐️</div>
              </div>
              <div class="rps-vs">VS</div>
              <div class="rps-col">
                <div class="rps-label">アプリ</div>
                <div class="rps-hand" id="handCpu" aria-label="CPUの手">🖐️</div>
              </div>
            </div>
            <div><span class="rps-badge" id="rpsBadge"></span></div>
          </div>

          <!-- ガイド -->
          <div id="rpsResult" class="rps-guide">手をえらんでください。</div>

          <!-- Controls -->
          <div class="rps-ctrl">
            <button class="btn" type="button" data-rps="グー">グー</button>
            <button class="btn" type="button" data-rps="チョキ">チョキ</button>
            <button class="btn" type="button" data-rps="パー">パー</button>
          </div>
        </div>
      </article>
    </section>
  </main>

  <!-- Game -> Stamp sheet -->
  <aside class="sheet" id="goStampSheet" role="dialog" aria-modal="true" aria-labelledby="goStampTitle">
    <div class="sheet-hd"><h3 id="goStampTitle">スタンプを確認してください</h3></div>
    <div class="sheet-body">
      <p class="muted">ゲームで加算されたスタンプをスタンプカードで確認できます。</p>
      <p class="muted"><strong id="remainSec">21</strong> 秒後に自動でスタンプページへ移動します。</p>
      <button class="btn" id="goStampNow">スタンプページへ</button>
    </div>
  </aside>

  <!-- Menu Detail Sheet -->
  <aside class="sheet" id="menuSheet" role="dialog" aria-modal="true" aria-labelledby="menuTitle">
    <div class="sheet-hd">
      <h3 id="menuTitle">商品詳細</h3>
      <button class="btn" id="closeMenuSheet" aria-label="閉じる">閉じる</button>
    </div>
    <div class="sheet-body" id="menuBody"></div>
  </aside>

  <!-- Redeem Sheet -->
  <aside class="sheet" id="redeemSheet" role="dialog" aria-modal="true" aria-labelledby="redeemTitle">
    <div class="sheet-hd">
      <h3 id="redeemTitle">クーポンに引き換え</h3>
      <button class="btn" id="closeRedeem" aria-label="閉じる">閉じる</button>
    </div>
    <div class="sheet-body">
      <p class="muted">利用可能スタンプ：<strong id="availStamps">0</strong> 個</p>
      <div id="redeemOptions" style="display:grid;gap:10px;margin:12px 0"></div>
      <div style="display:flex;gap:8px">
        <button class="btn" id="doRedeem" disabled>引き換える</button>
        <button class="btn-ghost" id="cancelRedeem">キャンセル</button>
      </div>
      <p class="muted" id="redeemMsg" style="margin-top:10px;"></p>
    </div>
  </aside>
</div>

<!-- Bottom navigation -->
<nav class="bottom-nav" aria-label="メニュー">
  <div class="tabs">
    <a class="tab is-active" href="#" data-target="home" aria-current="page" aria-label="ホーム">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3l9-8z" fill="#9ca3af"/></svg><span>ホーム</span>
    </a>
    <a class="tab" href="#" data-target="coupon" aria-label="クーポン">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7a3 3 0 013-3h10a3 3 0 013 3v2a2 2 0 110 4v2a3 3 0 01-3 3H7a3 3 0 01-3-3v-2a2 2 0 110-4V7zM9 7v10M15 7v10" fill="#9ca3af"/></svg><span>クーポン</span>
    </a>
    <a class="tab" href="#" data-target="member" aria-label="会員証">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6a3 3 0 013-3h12a3 3 0 013 3v12a3 3 0 01-3 3H6a3 3 0 01-3-3V6zm6 3a3 3 0 106 0 3 3 0 00-6 0zm-3 9a6 6 0 1112 0H6z" fill="#9ca3af"/></svg><span>会員証</span>
    </a>
    <a class="tab" href="#" data-target="menu" aria-label="メニュー">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z" fill="#9ca3af"/></svg><span>メニュー</span>
    </a>
    <a class="tab" href="#" data-target="game" aria-label="ゲーム">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 8h16v8H4zM7 6h10v2H7zM8 17h8v1H8z" fill="#9ca3af"/></svg><span>ゲーム</span>
    </a>
<a class="tab" href="/pointcard/auth/logout.php" data-hardlink="true" aria-label="ログアウト">
  <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M10 3h7a2 2 0 012 2v4h-2V5h-7v14h7v-4h2v4a2 2 0 01-2 2h-7a2 2 0 01-2-2V5a2 2 0 012-2zm5.293 5.293L18 11h-9v2h9l-2.707 2.707 1.414 1.414L22.828 12l-6.121-6.121-1.414 1.414z" fill="#9ca3af"/>
  </svg>
  <span>ログアウト</span>
</a>


  </div>
</nav>

<script src="./app.js" defer></script>
</body>
</html>
