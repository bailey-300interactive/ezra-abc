<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

$S = settings();
$letters = array_map(fn($r) => [
    'letter' => $r['letter'],
    'word'   => $r['word'],
    'emoji'  => $r['emoji'],
    'image'  => upload_url($r['image'], $r['ver']),
    'voice'  => upload_url($r['voice'], $r['ver']),
], letters(true));

$boot = [
    'title'       => $S['title'],
    'case'        => $S['default_case'] === 'lower' ? 'lower' : 'upper',
    'mode'        => $S['default_mode'] === 'draw' ? 'draw' : 'trace',
    'allowCase'   => $S['allow_case'] === '1',
    'voice'       => $S['voice_on'] === '1',
    'sounds'      => $S['sounds_on'] === '1',
    'rate'        => max(0.5, min(1.3, (float)$S['speech_rate'])),
    'difficulty'  => $S['difficulty'] === 'medium' ? 'medium' : 'easy',
    'fridge'      => $S['show_fridge'] === '1',
    'autoSave'    => $S['auto_save'] === '1',
    'letters'     => $letters,
];
$v = (string)@filemtime(__DIR__ . '/assets/js/game.js') . (string)@filemtime(__DIR__ . '/assets/css/game.css');
header('Cache-Control: no-cache');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<title><?= h($S['title']) ?></title>
<meta name="theme-color" content="#5b3df5">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= h($S['title']) ?>">
<meta name="format-detection" content="telephone=no">
<link rel="manifest" href="manifest.php">
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
<link rel="icon" type="image/png" sizes="96x96" href="assets/icons/favicon-96.png">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="180x180" href="assets/icons/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="167x167" href="assets/icons/apple-touch-icon-167.png">
<link rel="apple-touch-icon" sizes="152x152" href="assets/icons/apple-touch-icon-152.png">
<link rel="preload" href="assets/fonts/fredoka-latin-700-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="assets/css/game.css?v=<?= h($v) ?>">
</head>
<body>
<div id="app">

  <!-- Splash: the first tap unlocks sound + speech and goes full screen -->
  <section id="splash" class="screen active">
    <div class="sky"><span class="cloud c1"></span><span class="cloud c2"></span><span class="cloud c3"></span></div>
    <img class="splash-logo" src="assets/icons/logo-ezra.png" alt="">
    <h1 class="bubble-title" id="splashTitle"></h1>
    <div class="blocks" aria-hidden="true"><span>A</span><span>B</span><span>C</span></div>
    <button id="playBtn" class="play-btn" aria-label="Play"><span>▶</span></button>
    <p class="hint">Tap to play</p>
  </section>

  <!-- Home: pick a letter -->
  <section id="home" class="screen">
    <header class="home-bar">
      <div class="home-brand"><img class="home-logo" src="assets/icons/logo-ezra.png" alt=""><h1 class="bubble-title small" id="homeTitle"></h1></div>
      <div class="bar-buttons">
        <button id="caseBtnHome" class="pill-btn case-btn" aria-label="Big or little letters">ABC</button>
        <button id="fridgeBtnHome" class="round-btn fridge-btn" aria-label="Fridge">🧲</button>
        <button class="round-btn fs-btn" aria-label="Full screen">⛶</button>
      </div>
    </header>
    <div id="grid" class="grid"></div>
  </section>

  <!-- Board: trace or draw one letter -->
  <section id="board" class="screen">
    <canvas id="guide"></canvas>
    <canvas id="ink"></canvas>
    <canvas id="fx"></canvas>

    <header class="board-bar">
      <button id="homeBtn" class="round-btn" aria-label="All letters">🏠</button>
      <button id="wordCard" class="word-card" aria-label="Hear it again">
        <span class="wc-pic" id="wcPic"></span>
        <span class="wc-text"><b id="wcLetter">A</b> is for <span id="wcWord">Apple</span></span>
        <span class="wc-speaker">🔊</span>
      </button>
      <div class="bar-buttons">
        <div class="mode-switch" role="group" aria-label="Trace or draw">
          <button data-mode="trace" class="mode-btn" aria-label="Trace">✏️</button>
          <button data-mode="draw" class="mode-btn" aria-label="Draw">🎨</button>
        </div>
        <button id="caseBtnBoard" class="pill-btn case-btn" aria-label="Big or little letter">Aa</button>
        <button class="round-btn fs-btn" aria-label="Full screen">⛶</button>
      </div>
    </header>

    <button id="prevBtn" class="arrow-btn prev" aria-label="Previous letter">◀</button>
    <button id="nextBtn" class="arrow-btn next" aria-label="Next letter">▶</button>

    <footer class="palette" id="palette">
      <div class="swatches" id="swatches"></div>
      <div class="tools">
        <button id="clearBtn" class="tool-btn" aria-label="Start over">🧽</button>
        <button id="saveBtn" class="tool-btn save" aria-label="Put it on the fridge">⭐</button>
      </div>
    </footer>
  </section>

  <!-- Fridge: saved drawings -->
  <section id="fridge" class="overlay">
    <div class="fridge-door">
      <button class="round-btn close-btn" id="fridgeClose" aria-label="Close">✖</button>
      <h2>🧲 My Fridge</h2>
      <div id="fridgeGrid" class="fridge-grid"></div>
      <p id="fridgeEmpty" class="fridge-empty">Tap ⭐ to put a drawing here!</p>
    </div>
    <div id="fridgeZoom" class="fridge-zoom"><img alt=""></div>
  </section>

  <!-- Celebration card -->
  <div id="party" class="party">
    <div class="party-card">
      <div class="party-pic" id="partyPic"></div>
      <div class="party-text" id="partyText"></div>
      <div class="party-stars">⭐⭐⭐</div>
    </div>
  </div>

</div>

<script>window.EZRA_ABC = <?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="assets/js/letters.js?v=<?= h($v) ?>"></script>
<script src="assets/js/game.js?v=<?= h($v) ?>"></script>
</body>
</html>
