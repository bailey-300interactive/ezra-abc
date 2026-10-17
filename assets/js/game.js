/* Ezra ABC — trace and draw the alphabet. Touch-first, full screen, made for little fingers. */
(() => {
  'use strict';

  const B = window.EZRA_ABC;
  const STROKES = window.ABC_STROKES;
  const NAMES = window.ABC_NAMES;
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const rand = (a, b) => a + Math.random() * (b - a);
  const now = () => performance.now();

  /* ---------- Per-device storage (always optional) ---------- */
  const store = {
    get(k, d) { try { const v = localStorage.getItem('ezraabc_' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem('ezraabc_' + k, JSON.stringify(v)); } catch (e) {} }
  };
  let deviceId = store.get('device', '');
  if (!deviceId) { deviceId = Math.random().toString(36).slice(2) + Date.now().toString(36); store.set('device', deviceId); }

  const LETTERS = B.letters.length ? B.letters : Object.keys(NAMES).map(L => ({ letter: L, word: '', emoji: '' }));
  const NUMWORD = window.ABC_NUMBER_WORD;
  const THINGS = window.ABC_NUMBER_THINGS;
  const NUM_MAX = [10, 20, 50, 100].includes(B.numberMax) ? B.numberMax : 100;
  const NUMBERS = Array.from({ length: NUM_MAX + 1 }, (_, n) => ({ num: n }));
  // Number groups on the home screen: 0–10, 11–20, 21–30 …
  const GROUPS = [{ from: 0, to: Math.min(10, NUM_MAX) }];
  for (let a = 11; a <= NUM_MAX; a += 10) GROUPS.push({ from: a, to: Math.min(a + 9, NUM_MAX) });
  const groupOf = n => GROUPS.findIndex(g => n >= g.from && n <= g.to);
  const TILE_COLORS = ['#ff4d6d', '#ff9f1c', '#ffc43d', '#35d07f', '#1fb8e8', '#5b3df5', '#c04dff', '#ff5ca8'];
  const LETTER_COLORS = ['#ff3b5c', '#ff8a1c', '#f5b700', '#22c06b', '#169fe0', '#5b3df5', '#b03df5', '#ff4fa3'];
  const SWATCHES = [
    { id: 'red', c: '#ff3b3b' }, { id: 'orange', c: '#ff8a1c' }, { id: 'yellow', c: '#ffd23f' },
    { id: 'green', c: '#22c06b' }, { id: 'blue', c: '#1f8fff' }, { id: 'purple', c: '#7a3df5' },
    { id: 'pink', c: '#ff4fa3' }, { id: 'rainbow', c: null }, { id: 'glitter', c: null }
  ];
  const GLITTER = ['#ffd23f', '#ff5ca8', '#8a5cff', '#29b6ff', '#35d07f'];

  const S = {
    case: store.get('case', B.case),
    mode: store.get('mode', B.mode),
    idx: 0,
    brush: store.get('brush', 'rainbow'),
    stars: store.get('stars', {}),
    set: B.numbers ? store.get('set', 'abc') : 'abc',      // 'abc' letters or '123' numbers
    group: GROUPS.length > 1 ? -1 : 0                         // which number group is open (-1 = pick a group)
  };
  if (!B.allowCase) S.case = B.case;
  if (S.set !== '123') S.set = 'abc';

  /* ---------- Sound (synthesised, nothing to download) ---------- */
  let ac = null;
  function audio() {
    if (!ac) { const C = window.AudioContext || window.webkitAudioContext; if (C) ac = new C(); }
    if (ac && ac.state === 'suspended') ac.resume();
    return ac;
  }
  function tone(freq, dur, type = 'sine', vol = 0.2, when = 0, slideTo = 0) {
    if (!B.sounds) return;
    const a = audio(); if (!a) return;
    const t = a.currentTime + when;
    const o = a.createOscillator(), g = a.createGain();
    o.type = type; o.frequency.setValueAtTime(freq, t);
    if (slideTo) o.frequency.exponentialRampToValueAtTime(slideTo, t + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(vol, t + 0.012);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g).connect(a.destination);
    o.start(t); o.stop(t + dur + 0.05);
  }
  function noise(dur, vol = 0.15, f0 = 400, f1 = 3000) {
    if (!B.sounds) return;
    const a = audio(); if (!a) return;
    const len = Math.floor(a.sampleRate * dur);
    const buf = a.createBuffer(1, len, a.sampleRate), d = buf.getChannelData(0);
    for (let i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * (1 - i / len);
    const src = a.createBufferSource(); src.buffer = buf;
    const bp = a.createBiquadFilter(); bp.type = 'bandpass'; bp.Q.value = 1.2;
    bp.frequency.setValueAtTime(f0, a.currentTime); bp.frequency.exponentialRampToValueAtTime(f1, a.currentTime + dur);
    const g = a.createGain(); g.gain.value = vol;
    src.connect(bp).connect(g).connect(a.destination); src.start();
  }
  const PENTA = [523.25, 587.33, 659.25, 783.99, 880, 1046.5, 1174.66, 1318.51, 1567.98, 1760, 2093];
  const sfx = {
    pop(i) { const f = PENTA[i % PENTA.length]; tone(f, 0.16, 'sine', 0.22); tone(f * 2, 0.08, 'triangle', 0.05); },
    note(y01) { const f = PENTA[clamp(Math.round((1 - y01) * (PENTA.length - 1)), 0, PENTA.length - 1)]; tone(f, 0.22, 'triangle', 0.12); },
    chime() { [0, 2, 4, 7].forEach((n, k) => tone(PENTA[n], 0.35, 'triangle', 0.16, k * 0.07)); },
    fanfare() {
      [[523.25, 0], [659.25, 0.13], [783.99, 0.26], [1046.5, 0.42]].forEach(([f, w]) => { tone(f, 0.3, 'square', 0.06, w); tone(f, 0.32, 'triangle', 0.16, w); });
      [1046.5, 1318.51, 1567.98].forEach(f => { tone(f, 0.9, 'triangle', 0.12, 0.6); });
      for (let i = 0; i < 10; i++) tone(rand(1800, 3200), 0.1, 'sine', 0.05, 0.6 + i * 0.07);
    },
    swoosh() { noise(0.35, 0.18, 300, 2400); },
    tap() { tone(880, 0.07, 'sine', 0.15); tone(1320, 0.05, 'sine', 0.06, 0.03); },
    boing() { tone(260, 0.3, 'sine', 0.2, 0, 720); },
    magnet() { tone(392, 0.12, 'square', 0.05); tone(784, 0.2, 'triangle', 0.15, 0.08); tone(1568, 0.25, 'sine', 0.08, 0.16); }
  };

  /* ---------- Speech (uses the iPad's built-in voice, or your recorded clip) ---------- */
  let voice = null, clipEl = null;
  /* Pick the warmest-sounding voice the device has. The high-quality "Premium" / "Enhanced" / "Natural"
     voices sound like a real person; the basic compact ones sound robotic, so they come last. Novelty
     voices (Bells, Zarvox, Whisper…) are never used. */
  const WARM = ['Ava', 'Zoe', 'Samantha', 'Nicky', 'Allison', 'Susan', 'Joelle', 'Evan', 'Nathan', 'Tom',
    'Serena', 'Kate', 'Karen', 'Moira', 'Tessa', 'Jenny', 'Aria', 'Emma', 'Ana', 'Libby', 'Sonia', 'Google US English'];
  const NOVELTY = /albert|bad news|bahh|bells|boing|bubbles|cellos|deranged|good news|hysterical|jester|junior|organ|superstar|trinoids|whisper|wobble|zarvox|ralph|fred|kathy|princess|eddy|flo|grandma|grandpa|reed|rocko|sandy|shelley/i;
  function voiceScore(v) {
    let s = 0;
    if (/premium/i.test(v.name)) s += 60;
    else if (/enhanced|neural|natural|online/i.test(v.name)) s += 45;
    const w = WARM.findIndex(n => v.name.includes(n));
    if (w >= 0) s += 30 - w;
    if (/en[-_]US/i.test(v.lang)) s += 6; else if (/en[-_](GB|AU|IE|CA)/i.test(v.lang)) s += 3;
    if (v.localService) s += 2;                     // works without the internet
    return s;
  }
  function pickVoice() {
    if (!('speechSynthesis' in window)) return;
    const vs = speechSynthesis.getVoices().filter(v => /^en(-|_|$)/i.test(v.lang) && !NOVELTY.test(v.name));
    voice = vs.sort((a, b) => voiceScore(b) - voiceScore(a))[0] || null;
  }
  if ('speechSynthesis' in window) { pickVoice(); speechSynthesis.addEventListener?.('voiceschanged', pickVoice); }
  function stopTalking() {
    if ('speechSynthesis' in window) speechSynthesis.cancel();
    if (clipEl) { clipEl.pause(); clipEl = null; }
  }
  function say(text, then) {
    if (!B.voice || !('speechSynthesis' in window)) { then && then(); return; }
    const u = new SpeechSynthesisUtterance(text);
    // A natural pitch: pushing it up is what made the old voice sound strange.
    u.rate = B.rate; u.pitch = 1.05; u.volume = 1;
    if (voice) u.voice = voice;
    if (then) u.onend = then;
    speechSynthesis.speak(u);
  }
  function playClip(url) {
    if (!B.voice || !url) return false;
    clipEl = new Audio(url); clipEl.play().catch(() => {});
    return true;
  }
  const cap = s => s.charAt(0).toUpperCase() + s.slice(1);
  const isNum = it => it && it.num !== undefined;
  const thing = n => THINGS[n % THINGS.length];
  const noun = n => n === 1 ? thing(n)[1] : thing(n)[2];
  /* "Three cars!", "One puppy!", "No cookies!" */
  function numPhrase(n) { return n === 0 ? `No ${noun(0)}!` : `${cap(NUMWORD(n))} ${noun(n)}!`; }
  function speakLetter(intro = '') {
    stopTalking();
    const L = cur();
    if (isNum(L)) { say(`${intro}${cap(NUMWORD(L.num))}! ${numPhrase(L.num)}`); return; }
    const name = NAMES[L.letter];
    const tail = L.word ? `${name} is for ${L.word}!` : '';
    if (L.voice && !intro) { playClip(L.voice); return; }
    say(`${intro}${name}! ${tail}`, intro && L.voice ? () => playClip(L.voice) : null);
  }
  function unlockSpeech() {
    if (!('speechSynthesis' in window)) return;
    const u = new SpeechSynthesisUtterance(' '); u.volume = 0; speechSynthesis.speak(u);
  }

  /* ---------- Full screen ---------- */
  const docEl = document.documentElement;
  const canFS = !!(docEl.requestFullscreen || docEl.webkitRequestFullscreen);
  const isFS = () => !!(document.fullscreenElement || document.webkitFullscreenElement);
  const isStandalone = () => navigator.standalone === true || matchMedia('(display-mode: standalone)').matches
    || (matchMedia('(display-mode: fullscreen)').matches && !isFS());
  function enterFS() {
    if (!canFS || isFS() || isStandalone()) return;
    try {
      const p = docEl.requestFullscreen ? docEl.requestFullscreen({ navigationUI: 'hide' }) : docEl.webkitRequestFullscreen();
      if (p && p.catch) p.catch(() => {});
    } catch (e) {}
  }
  function exitFS() {
    try { (document.exitFullscreen || document.webkitExitFullscreen).call(document); } catch (e) {}
  }
  function updateFSButtons() {
    $$('.fs-btn').forEach(b => {
      b.classList.toggle('hidden', !canFS || isStandalone());
      b.textContent = isFS() ? '🗗' : '⛶';
    });
  }
  ['fullscreenchange', 'webkitfullscreenchange'].forEach(ev => document.addEventListener(ev, () => { updateFSButtons(); setTimeout(relayout, 120); }));
  $$('.fs-btn').forEach(b => b.addEventListener('click', () => { sfx.tap(); isFS() ? exitFS() : enterFS(); }));

  /* ---------- Letter geometry ---------- */
  const SVGNS = 'http://www.w3.org/2000/svg';
  const measureSvg = document.createElementNS(SVGNS, 'svg');
  measureSvg.setAttribute('width', '0'); measureSvg.setAttribute('height', '0');
  measureSvg.style.cssText = 'position:absolute;left:-9999px;top:-9999px';
  const measurePath = document.createElementNS(SVGNS, 'path');
  measureSvg.appendChild(measurePath); document.body.appendChild(measureSvg);
  const geomCache = {};

  function charFor(L, c = S.case) { return c === 'lower' ? L.toLowerCase() : L.toUpperCase(); }
  /* Glyph key: a letter ('A', 'a') or '#' + a number ('#7', '#42'). */
  const glyphKey = it => isNum(it) ? '#' + it.num : charFor(it.letter);
  const DIGIT_GAP = 30;                                       // space between the digits of 10, 42, 100 …
  const measured = {};
  function measure(d) {
    if (measured[d]) return measured[d];
    measurePath.setAttribute('d', d);
    const len = measurePath.getTotalLength();
    const n = Math.max(2, Math.ceil(len));
    const pts = [];
    for (let i = 0; i < n; i++) { const p = measurePath.getPointAtLength(len * i / (n - 1)); pts.push({ x: p.x, y: p.y }); }
    return (measured[d] = { len, pts });
  }
  function geom(ch) {
    if (geomCache[ch]) return geomCache[ch];
    let parts;
    if (ch[0] === '#') {
      // A number: its digits side by side, each one moved along by dx.
      parts = []; let x = 0;
      [...ch.slice(1)].forEach(dg => {
        const list = STROKES.digits[dg] || [];
        let a = 1e9, b = -1e9;
        list.forEach(d => measure(d).pts.forEach(p => { a = Math.min(a, p.x); b = Math.max(b, p.x); }));
        list.forEach(d => parts.push({ d, dx: x - a }));
        x += b - a + DIGIT_GAP;
      });
    } else {
      const set = ch === ch.toUpperCase() ? STROKES.upper : STROKES.lower;
      parts = (set[ch] || []).map(d => ({ d, dx: 0 }));
    }
    let x0 = 1e9, y0 = 1e9, x1 = -1e9, y1 = -1e9;
    const strokes = parts.map(({ d, dx }) => {
      const m = measure(d);
      const pts = m.pts.map(p => ({ x: p.x + dx, y: p.y }));
      pts.forEach(p => { x0 = Math.min(x0, p.x); y0 = Math.min(y0, p.y); x1 = Math.max(x1, p.x); y1 = Math.max(y1, p.y); });
      let path = new Path2D(d);
      if (dx) { const moved = new Path2D(); moved.addPath(path, new DOMMatrix().translate(dx, 0)); path = moved; }
      return { d, dx, len: m.len, pts, tap: m.len < 6, path };
    });
    return (geomCache[ch] = { ch, strokes, x0, y0, x1, y1, w: x1 - x0, h: y1 - y0, digits: ch[0] === '#' ? ch.length - 1 : 0 });
  }
  function pointAt(stroke, f) {
    const p = stroke.pts; const i = clamp(f, 0, 1) * (p.length - 1);
    const a = p[Math.floor(i)], b = p[Math.ceil(i)], t = i - Math.floor(i);
    return { x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t };
  }

  /* ---------- Screens ---------- */
  function show(id) {
    $$('.screen').forEach(s => s.classList.toggle('active', s.id === id));
    if (id === 'board') { relayout(); startLoop(); } else stopLoop();
  }
  const items = () => S.set === '123' ? NUMBERS : LETTERS;
  const cur = () => items()[S.idx];

  function titleSpans(el, text) {
    el.innerHTML = '';
    [...text].forEach((c, i) => {
      const s = document.createElement('span');
      s.textContent = c === ' ' ? ' ' : c;
      s.style.animationDelay = (i * 0.12) + 's';
      el.appendChild(s);
    });
  }

  /* ---------- Home grid ---------- */
  function letterSvg(ch, color) {
    const g = geom(ch);
    const pad = 16;
    const vb = [g.x0 - pad, g.y0 - pad, Math.max(g.w, 30) + pad * 2, Math.max(g.h, 30) + pad * 2];
    if (g.w < 30) vb[0] -= (30 - g.w) / 2;
    if (g.h < 30) vb[1] -= (30 - g.h) / 2;
    const ps = g.strokes.map(s => `<path d="${s.d}"${s.dx ? ` transform="translate(${s.dx.toFixed(2)} 0)"` : ''}/>`).join('');
    return `<svg viewBox="${vb.join(' ')}" aria-hidden="true">
      <g fill="none" stroke-linecap="round" stroke-linejoin="round">
        <g stroke="rgba(30,10,80,.35)" stroke-width="27" transform="translate(0 4)">${ps}</g>
        <g stroke="#2b2160" stroke-width="26">${ps}</g>
        <g stroke="#fff" stroke-width="15">${ps}</g>
        <g stroke="${color}" stroke-width="5" opacity=".35">${ps}</g>
      </g></svg>`;
  }
  function buildGrid() {
    const grid = $('#grid'); grid.innerHTML = '';
    const numbers = S.set === '123';
    $$('.set-btn').forEach(b => b.classList.toggle('on', b.dataset.set === S.set));
    $('#setSwitch').classList.toggle('hidden', !B.numbers);
    $('#groupBack').classList.toggle('hidden', !(numbers && S.group >= 0 && GROUPS.length > 1));
    $$('.case-btn').forEach(b => b.classList.toggle('hidden', !B.allowCase || numbers));
    if (numbers) { buildNumberGrid(grid); return; }
    LETTERS.forEach((L, i) => {
      const ch = charFor(L.letter);
      const b = document.createElement('button');
      b.className = 'tile';
      b.style.setProperty('--c', TILE_COLORS[i % TILE_COLORS.length]);
      b.style.setProperty('--i', i);
      b.setAttribute('aria-label', ch);
      const star = S.stars[S.case + ':' + L.letter] ? '<span class="t-star">⭐</span>' : '';
      const pic = L.image ? `<img src="${L.image}" alt="" style="width:1.3em;height:1.3em;border-radius:50%;object-fit:cover">` : (L.emoji || '');
      b.innerHTML = letterSvg(ch, LETTER_COLORS[i % LETTER_COLORS.length]) + `<span class="t-emoji">${pic}</span>` + star;
      b.addEventListener('click', () => { audio(); sfx.tap(); openLetter(i); });
      grid.appendChild(b);
    });
    $$('.case-btn').forEach(b => {
      b.innerHTML = b.id === 'caseBtnHome' ? (S.case === 'upper' ? 'ABC' : 'abc') : (S.case === 'upper' ? 'A<small style="opacity:.4">a</small>' : '<small style="opacity:.4">A</small>a');
    });
  }

  /* Numbers: first pick a group (0–10, 11–20 …), then a number. */
  function buildNumberGrid(grid) {
    if (S.group < 0) {
      GROUPS.forEach((g, i) => {
        const b = document.createElement('button');
        b.className = 'tile group-tile';
        b.style.setProperty('--c', TILE_COLORS[i % TILE_COLORS.length]);
        b.style.setProperty('--i', i);
        b.setAttribute('aria-label', `${g.from} to ${g.to}`);
        let all = true;
        for (let n = g.from; n <= g.to; n++) if (!S.stars['123:' + n]) all = false;
        b.innerHTML = `<span class="t-range">${g.from}<i>–</i>${g.to}</span><span class="t-emoji">${thing(i + 1)[0]}</span>` + (all ? '<span class="t-star">⭐</span>' : '');
        b.addEventListener('click', () => { audio(); sfx.tap(); S.group = i; buildGrid(); $('#home').scrollTop = 0; });
        grid.appendChild(b);
      });
      return;
    }
    const g = GROUPS[S.group];
    for (let n = g.from; n <= g.to; n++) {
      const b = document.createElement('button');
      b.className = 'tile';
      b.style.setProperty('--c', TILE_COLORS[n % TILE_COLORS.length]);
      b.style.setProperty('--i', n - g.from);
      b.setAttribute('aria-label', String(n));
      const star = S.stars['123:' + n] ? '<span class="t-star">⭐</span>' : '';
      b.innerHTML = letterSvg('#' + n, LETTER_COLORS[n % LETTER_COLORS.length]) + `<span class="t-emoji">${thing(n)[0]}</span>` + star;
      b.addEventListener('click', () => { audio(); sfx.tap(); openLetter(n); });
      grid.appendChild(b);
    }
  }
  $$('.set-btn').forEach(b => b.addEventListener('click', () => {
    if (S.set === b.dataset.set) return;
    S.set = b.dataset.set; store.set('set', S.set);
    S.group = GROUPS.length > 1 ? -1 : 0;
    sfx.boing(); buildGrid(); $('#home').scrollTop = 0;
    say(S.set === '123' ? 'Numbers!' : 'Letters!');
  }));
  $('#groupBack').addEventListener('click', () => { sfx.tap(); S.group = -1; buildGrid(); $('#home').scrollTop = 0; });

  /* ---------- Board state ---------- */
  const guide = $('#guide'), ink = $('#ink'), fx = $('#fx');
  const gctx = guide.getContext('2d'), ictx = ink.getContext('2d'), fctx = fx.getContext('2d');
  let W = 0, H = 0, DPR = 1;
  const V = { s: 1, ox: 0, oy: 0, L: 60, area: null };        // view transform for the current letter
  let G = null;                                               // geometry of the current letter
  let T = null;                                               // tracing progress
  let inkStrokes = [];                                        // everything drawn, in letter units
  let particles = [], fills = [];
  let lastTouch = now(), hintT0 = 0, popCount = 0, completed = false;

  function letterColor() { return LETTER_COLORS[S.idx % LETTER_COLORS.length]; }

  function openLetter(i, dir = 0) {
    stopCount();
    S.idx = (i + items().length) % items().length;
    G = geom(glyphKey(cur()));
    inkStrokes = []; particles = []; fills = []; completed = false; popCount = 0;
    T = null;
    $('#nextBtn').classList.remove('glow');
    if (!$('#board').classList.contains('active')) show('board'); else relayout();
    resetTrace();
    drawGuide(); redrawInk();
    updateWordCard();
    lastTouch = now(); hintT0 = now() + 2500;
    if (dir) { sfx.swoosh(); burst(W / 2, H / 2, 18, 'star'); }
    setTimeout(() => speakLetter(), dir ? 250 : 80);
  }

  function updateWordCard() {
    const L = cur();
    if (isNum(L)) {
      $('#wordCard .wc-text').innerHTML = `<b>${L.num}</b> ${L.num === 0 ? 'no' : NUMWORD(L.num)} ${noun(L.num)}`;
      $('#wcPic').innerHTML = thing(L.num)[0];
    } else {
    $('#wordCard .wc-text').innerHTML = L.word ? `<b>${charFor(L.letter)}</b> is for ${escapeHtml(L.word)}` : `<b>${charFor(L.letter)}</b>`;
    $('#wcPic').innerHTML = L.image ? `<img src="${L.image}" alt="">` : (L.emoji || '🔤');
    }
    const wc = $('#wordCard'); wc.classList.remove('bounce'); void wc.offsetWidth; wc.classList.add('bounce');
    $$('.mode-btn').forEach(b => b.classList.toggle('on', b.dataset.mode === S.mode));
    $$('.case-btn').forEach(b => b.classList.toggle('hidden', !B.allowCase || isNum(L)));
  }
  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

  function resetTrace() {
    // Dot count is fixed per letter visit so a rotation mid-trace keeps progress.
    const lens = G.strokes.map(s => s.len * V.s);
    const gap = V.L * 0.82;
    T = G.strokes.map((s, k) => {
      const n = s.tap ? 1 : Math.max(3, Math.round(lens[k] / gap) + 1);
      const dots = [];
      for (let j = 0; j < n; j++) dots.push({ f: n === 1 ? 0.5 : j / (n - 1), hit: false, x: 0, y: 0 });
      return { dots, done: false, hits: 0, need: s.tap ? 1 : Math.max(2, Math.ceil(n * (B.difficulty === 'medium' ? 0.92 : 0.72))) };
    });
    placeDots();
  }
  function placeDots() {
    if (!T) return;
    const seen = [], near = V.L * 0.45;
    T.forEach((t, k) => t.dots.forEach(d => {
      const p = pointAt(G.strokes[k], d.f); d.x = V.ox + p.x * V.s; d.y = V.oy + p.y * V.s;
      d.dup = seen.some(o => (o.x - d.x) ** 2 + (o.y - d.y) ** 2 < near * near);   // where strokes meet, show one dot
      seen.push(d);
    }));
  }

  /* ---------- Layout ---------- */
  function sizeCanvas(c) {
    c.width = Math.round(W * DPR); c.height = Math.round(H * DPR);
  }
  function relayout() {
    if (!$('#board').classList.contains('active') || !G) return;
    const r = $('#board').getBoundingClientRect();
    W = r.width; H = r.height; DPR = Math.min(window.devicePixelRatio || 1, 2);
    [guide, ink, fx].forEach(sizeCanvas);
    const top = $('.board-bar').getBoundingClientRect().bottom + 10;
    const bottom = $('#swatches').getBoundingClientRect().top - 14;
    const left = $('#prevBtn').getBoundingClientRect().right + 14;
    const right = $('#nextBtn').getBoundingClientRect().left - 14;
    const area = { x: left, y: top, w: Math.max(100, right - left), h: Math.max(100, bottom - top) };
    V.area = area;
    V.L = clamp(Math.min(area.w, area.h) * 0.13, 34, 104);
    const pad = V.L * 0.7;
    const gw = Math.max(G.w, 40), gh = Math.max(G.h, 40);
    V.s = Math.min((area.w - pad * 2) / gw, (area.h - pad * 2) / gh);
    if (G.digits > 1) V.L = Math.max(24, Math.min(V.L, V.s * 21));   // fat strokes would join neighbouring digits
    V.ox = area.x + area.w / 2 - (G.x0 + G.w / 2) * V.s;
    V.oy = area.y + area.h / 2 - (G.y0 + G.h / 2) * V.s;
    placeDots(); drawGuide(); redrawInk();
  }
  let rT = 0;
  window.addEventListener('resize', () => { clearTimeout(rT); rT = setTimeout(relayout, 80); });
  window.addEventListener('orientationchange', () => setTimeout(relayout, 300));

  /* ---------- Drawing the letter ---------- */
  function strokePath(ctx, stroke, width, color, dash, dx = 0, dy = 0) {
    ctx.save();
    ctx.setTransform(DPR * V.s, 0, 0, DPR * V.s, DPR * (V.ox + dx), DPR * (V.oy + dy));
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.lineWidth = width / V.s; ctx.strokeStyle = color;
    if (dash) { ctx.setLineDash(dash.map(v => v / V.s)); }
    ctx.stroke(stroke.path);
    ctx.restore();
  }
  function drawGuide() {
    if (!G) return;
    gctx.setTransform(1, 0, 0, 1, 0, 0);
    gctx.clearRect(0, 0, guide.width, guide.height);
    const L = V.L, trace = S.mode === 'trace', col = letterColor();
    // solid colours (not see-through) so overlapping strokes don't show darker joins
    G.strokes.forEach(s => strokePath(gctx, s, L + 16, trace ? '#3d3370' : '#d9d3e6'));
    G.strokes.forEach(s => strokePath(gctx, s, L, trace ? '#ffffff' : mixWhite(col, 0.86)));
    G.strokes.forEach((s, k) => {
      if (trace && T && T[k].done) {
        strokePath(gctx, s, L, col);
        strokePath(gctx, s, L * 0.26, 'rgba(255,255,255,.45)', null, -L * 0.13, -L * 0.15);
      } else if (trace && !s.tap) {
        strokePath(gctx, s, Math.max(4, L * 0.07), hexA(col, 0.35), [2, L * 0.28]);
      }
    });
  }
  function mixWhite(hex, k) {
    const n = parseInt(hex.slice(1), 16);
    const m = c => Math.round(c + (255 - c) * k);
    return `rgb(${m(n >> 16 & 255)},${m(n >> 8 & 255)},${m(n & 255)})`;
  }
  function hexA(hex, a) {
    const n = parseInt(hex.slice(1), 16);
    return `rgba(${n >> 16 & 255},${n >> 8 & 255},${n & 255},${a})`;
  }

  /* ---------- Ink ---------- */
  const brushW = () => V.L * 0.44;
  function toU(x, y) { return [(x - V.ox) / V.s, (y - V.oy) / V.s]; }
  function toPx(u, v) { return [V.ox + u * V.s, V.oy + v * V.s]; }
  function segColor(st, p) {
    if (st.brush === 'rainbow') return `hsl(${p[2] % 360},95%,58%)`;
    if (st.brush === 'glitter') return GLITTER[Math.floor(p[2] / 40) % GLITTER.length];
    return st.color;
  }
  function drawSeg(st, a, b) {
    const [x0, y0] = toPx(a[0], a[1]), [x1, y1] = toPx(b[0], b[1]);
    ictx.setTransform(DPR, 0, 0, DPR, 0, 0);
    ictx.lineCap = 'round'; ictx.lineJoin = 'round';
    ictx.lineWidth = st.w * V.L; ictx.strokeStyle = segColor(st, b);
    ictx.beginPath(); ictx.moveTo(x0, y0); ictx.lineTo(x1, y1); ictx.stroke();
    if (st.brush === 'glitter' && b[3]) {
      ictx.fillStyle = 'rgba(255,255,255,.9)';
      b[3].forEach(([dx, dy, r]) => { ictx.beginPath(); ictx.arc(x1 + dx * V.L, y1 + dy * V.L, r, 0, 7); ictx.fill(); });
    }
  }
  function drawDot(st, p) {
    const [x, y] = toPx(p[0], p[1]);
    ictx.setTransform(DPR, 0, 0, DPR, 0, 0);
    ictx.fillStyle = segColor(st, p);
    ictx.beginPath(); ictx.arc(x, y, st.w * V.L / 2, 0, 7); ictx.fill();
  }
  function redrawInk() {
    ictx.setTransform(1, 0, 0, 1, 0, 0);
    ictx.clearRect(0, 0, ink.width, ink.height);
    inkStrokes.forEach(st => {
      if (st.pts.length === 1) drawDot(st, st.pts[0]);
      for (let i = 1; i < st.pts.length; i++) drawSeg(st, st.pts[i - 1], st.pts[i]);
    });
  }

  /* ---------- Touch ---------- */
  const active = new Map();
  let hue = rand(0, 360), noteDist = 0, lastNote = 0;

  function evPoint(e) { const r = ink.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; }

  ink.addEventListener('pointerdown', e => {
    e.preventDefault();
    audio();
    try { ink.setPointerCapture(e.pointerId); } catch (_) {}
    const p = evPoint(e);
    const sw = SWATCHES.find(s => s.id === S.brush) || SWATCHES[7];
    const st = { brush: sw.c ? 'color' : sw.id, color: sw.c, w: brushW() / V.L, pts: [] };
    const u = toU(p.x, p.y); const pt = [u[0], u[1], hue];
    st.pts.push(pt); inkStrokes.push(st); drawDot(st, pt);
    active.set(e.pointerId, { st, last: p });
    lastTouch = now(); hintT0 = now() + 3500;
    traceAt(p.x, p.y);
    if (S.mode === 'draw') { sfx.note(p.y / H); noteDist = 0; }
  });

  function onMove(e) {
    const a = active.get(e.pointerId); if (!a) return;
    e.preventDefault();
    const evs = (e.getCoalescedEvents && e.getCoalescedEvents().length) ? e.getCoalescedEvents() : [e];
    evs.forEach(ev => {
      const p = evPoint(ev);
      const dx = p.x - a.last.x, dy = p.y - a.last.y, dist = Math.hypot(dx, dy);
      if (dist < 1.5) return;
      hue = (hue + dist * 0.45) % 360;
      const u = toU(p.x, p.y), pt = [u[0], u[1], hue];
      if (a.st.brush === 'glitter') {
        pt[3] = Array.from({ length: 2 }, () => [rand(-.25, .25), rand(-.25, .25), rand(1, 2.6)]);
        if (Math.random() < 0.6) particles.push(mkParticle('spark', p.x + rand(-20, 20), p.y + rand(-20, 20)));
      } else if (a.st.brush === 'rainbow' && Math.random() < 0.12) {
        particles.push(mkParticle('spark', p.x, p.y));
      }
      drawSeg(a.st, a.st.pts[a.st.pts.length - 1], pt);
      a.st.pts.push(pt);
      // check every few pixels between samples so fast swipes still count
      const steps = Math.ceil(dist / 8);
      for (let k = 1; k <= steps; k++) traceAt(a.last.x + dx * k / steps, a.last.y + dy * k / steps);
      if (S.mode === 'draw') {
        noteDist += dist;
        if (noteDist > 70 && now() - lastNote > 90) { sfx.note(p.y / H); noteDist = 0; lastNote = now(); }
      }
      a.last = p;
    });
    lastTouch = now(); hintT0 = now() + 3500;
  }
  ink.addEventListener('pointermove', onMove);
  const end = e => { active.delete(e.pointerId); };
  ink.addEventListener('pointerup', end);
  ink.addEventListener('pointercancel', end);
  ink.addEventListener('lostpointercapture', end);

  /* ---------- Tracing ---------- */
  function traceAt(x, y) {
    if (S.mode !== 'trace' || !T || completed) return;
    const R = V.L * (B.difficulty === 'medium' ? 0.62 : 0.9);
    T.forEach((t, k) => {
      if (t.done) return;
      t.dots.forEach(d => {
        if (d.hit) return;
        if ((d.x - x) ** 2 + (d.y - y) ** 2 < R * R) {
          d.hit = true; t.hits++;
          sfx.pop(popCount++);
          particles.push(mkParticle('ring', d.x, d.y));
          for (let i = 0; i < 3; i++) particles.push(mkParticle('star', d.x, d.y));
          if (t.hits >= t.need) completeStroke(k);
        }
      });
    });
  }
  function completeStroke(k) {
    const t = T[k]; t.done = true; t.dots.forEach(d => d.hit = true);
    fills.push({ k, t0: now() });
    sfx.chime();
    const s = G.strokes[k];
    for (let i = 0; i < 10; i++) { const p = pointAt(s, Math.random()); burst(V.ox + p.x * V.s, V.oy + p.y * V.s, 2, 'star'); }
    setTimeout(drawGuide, 520);
    if (T.every(x => x.done)) setTimeout(celebrate, 650);
  }

  function celebrate() {
    if (completed) return;
    completed = true;
    const L = cur(), num = isNum(L);
    S.stars[num ? '123:' + L.num : S.case + ':' + L.letter] = 1; store.set('stars', S.stars);
    sfx.fanfare();
    confetti();
    stopCount();
    const party = $('#party'), pic = $('#partyPic');
    pic.classList.toggle('counted', num && L.num > 0);
    if (num) {
      if (L.num > 0) renderCount(pic, L.num, false); else pic.textContent = '🎉';
      $('#partyText').textContent = L.num === 0 ? `0! No ${noun(0)}!` : `${L.num} ${noun(L.num)}!`;
    } else {
      pic.innerHTML = L.image ? `<img src="${L.image}" alt="">` : (L.emoji || '🎉');
      $('#partyText').textContent = L.word ? `${charFor(L.letter)} is for ${L.word}!` : `Yay! ${charFor(L.letter)}!`;
    }
    party.classList.add('active');
    party.style.pointerEvents = 'none';                 // a finger still drawing can't close it right away
    setTimeout(() => { party.style.pointerEvents = ''; }, 1400);
    stopTalking();
    if (num) { say(`Hooray! You did it! ${cap(NUMWORD(L.num))}! ${numPhrase(L.num)}`); }
    else {
    const name = NAMES[L.letter];
    say(`Hooray! You did it! ${name}!`, () => { if (L.voice) playClip(L.voice); else if (L.word) say(`${name} is for ${L.word}!`); });
    }
    if (B.autoSave) saveDrawing(true);
    clearTimeout(celebrate.t);
    celebrate.t = setTimeout(hideParty, 3600);
  }
  function hideParty() {
    $('#party').classList.remove('active');
    $('#nextBtn').classList.add('glow');
  }
  $('#party').addEventListener('pointerdown', () => { clearTimeout(celebrate.t); hideParty(); });

  /* ---------- Particles + animation loop ---------- */
  const CONF_COLORS = ['#ff3b5c', '#ffd23f', '#22c06b', '#1f8fff', '#b03df5', '#ff8a1c', '#ff4fa3'];
  function mkParticle(type, x, y, o = {}) {
    const p = { type, x, y, vx: rand(-3, 3), vy: rand(-4, 1), life: 0, max: rand(500, 900), size: rand(8, 16), rot: rand(0, 6), vr: rand(-.2, .2), color: CONF_COLORS[Math.floor(rand(0, CONF_COLORS.length))] };
    if (type === 'ring') { p.vx = p.vy = 0; p.max = 420; p.size = V.L * 0.35; p.color = letterColor(); }
    if (type === 'spark') { p.vx = rand(-.6, .6); p.vy = rand(-.8, .2); p.max = rand(400, 800); p.size = rand(4, 9); p.color = '#fff7b0'; }
    if (type === 'star') { p.vx = rand(-5, 5); p.vy = rand(-7, -1); p.size = rand(10, 20); p.color = ['#ffd23f', '#ffb000', '#fff06b'][Math.floor(rand(0, 3))]; }
    if (type === 'confetti') { p.max = rand(2200, 3400); p.size = rand(10, 18); }
    return Object.assign(p, o);
  }
  function burst(x, y, n, type) { for (let i = 0; i < n; i++) particles.push(mkParticle(type, x, y)); }
  function confetti() {
    for (let i = 0; i < 90; i++) particles.push(mkParticle('confetti', rand(0, W * 0.15), H, { vx: rand(4, 13), vy: rand(-22, -12) }));
    for (let i = 0; i < 90; i++) particles.push(mkParticle('confetti', W - rand(0, W * 0.15), H, { vx: rand(-13, -4), vy: rand(-22, -12) }));
    for (let i = 0; i < 40; i++) particles.push(mkParticle('star', rand(W * .2, W * .8), rand(H * .2, H * .6), { vy: rand(-8, -2), max: rand(900, 1500) }));
  }
  function starPath(ctx, x, y, r, rot) {
    ctx.beginPath();
    for (let i = 0; i < 10; i++) {
      const a = rot + i * Math.PI / 5, rr = i % 2 ? r * 0.45 : r;
      ctx.lineTo(x + Math.cos(a) * rr, y + Math.sin(a) * rr);
    }
    ctx.closePath();
  }

  let raf = 0, last = 0;
  function startLoop() { if (!raf) { last = now(); raf = requestAnimationFrame(loop); } }
  function stopLoop() { cancelAnimationFrame(raf); raf = 0; }
  function loop(t) {
    raf = requestAnimationFrame(loop);
    const dt = Math.min(50, t - last); last = t;
    fctx.setTransform(1, 0, 0, 1, 0, 0);
    fctx.clearRect(0, 0, fx.width, fx.height);
    fctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    if (S.mode === 'trace' && T && G) drawDots(t);
    drawFills(t);
    drawParticles(dt);
    if (S.mode === 'trace' && !completed && t > hintT0 && t - lastTouch > 3500) drawHint(t);
  }
  function drawDots(t) {
    const col = letterColor();
    const next = T.findIndex(x => !x.done);
    T.forEach((tr, k) => {
      if (tr.done) return;
      const r = V.L * 0.17;
      tr.dots.forEach((d, j) => {
        if (d.hit) {
          fctx.fillStyle = '#ffc800'; fctx.strokeStyle = '#fff'; fctx.lineWidth = 3;
          starPath(fctx, d.x, d.y, r * 1.25, -Math.PI / 2); fctx.fill(); fctx.stroke();
          return;
        }
        const isStart = k === next && j === tr.dots.findIndex(x => !x.hit);
        if (d.dup && !isStart) return;
        const pulse = isStart ? 1 + Math.sin(t / 180) * 0.25 : 1;
        fctx.beginPath(); fctx.arc(d.x, d.y, r * pulse * (isStart ? 1.35 : 1), 0, 7);
        fctx.fillStyle = isStart ? '#22c06b' : '#fff'; fctx.fill();
        fctx.lineWidth = Math.max(3, r * 0.35); fctx.strokeStyle = isStart ? '#fff' : col; fctx.stroke();
        if (isStart) {
          fctx.beginPath(); fctx.arc(d.x, d.y, r * (1.6 + ((t / 900) % 1) * 1.6), 0, 7);
          fctx.strokeStyle = `rgba(34,192,107,${1 - ((t / 900) % 1)})`; fctx.lineWidth = 4; fctx.stroke();
        }
      });
    });
  }
  function drawFills(t) {
    fills = fills.filter(f => {
      const k = (t - f.t0) / 480; if (k >= 1.15) return false;
      const s = G.strokes[f.k];
      const len = s.len;
      fctx.save();
      fctx.setTransform(DPR * V.s, 0, 0, DPR * V.s, DPR * V.ox, DPR * V.oy);
      fctx.lineCap = 'round'; fctx.lineJoin = 'round'; fctx.lineWidth = V.L / V.s;
      fctx.strokeStyle = letterColor();
      fctx.setLineDash([len + 1, len + 1]); fctx.lineDashOffset = (len + 1) * (1 - Math.min(1, k));
      fctx.stroke(s.path);
      fctx.restore();
      return true;
    });
  }
  function drawParticles(dt) {
    const f = dt / 16.7;
    particles = particles.filter(p => {
      p.life += dt; if (p.life > p.max) return false;
      const k = p.life / p.max;
      p.x += p.vx * f; p.y += p.vy * f; p.rot += p.vr * f;
      if (p.type === 'confetti') { p.vy += 0.42 * f; p.vx *= 0.99; }
      else if (p.type === 'star') { p.vy += 0.25 * f; }
      fctx.globalAlpha = p.type === 'confetti' ? Math.min(1, (1 - k) * 3) : 1 - k;
      if (p.type === 'ring') {
        fctx.beginPath(); fctx.arc(p.x, p.y, p.size * (1 + k * 2), 0, 7);
        fctx.strokeStyle = p.color; fctx.lineWidth = 6 * (1 - k); fctx.stroke();
      } else if (p.type === 'star') {
        fctx.fillStyle = p.color; starPath(fctx, p.x, p.y, p.size * (1 - k * 0.4), p.rot); fctx.fill();
      } else if (p.type === 'spark') {
        fctx.fillStyle = p.color; starPath(fctx, p.x, p.y, p.size * (1 - k), p.rot); fctx.fill();
      } else {
        fctx.save(); fctx.translate(p.x, p.y); fctx.rotate(p.rot);
        fctx.fillStyle = p.color; fctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2 * (0.4 + Math.abs(Math.sin(p.life / 120))));
        fctx.restore();
      }
      fctx.globalAlpha = 1;
      return p.y < H + 60;
    });
  }
  function drawHint(t) {
    const k = T.findIndex(x => !x.done); if (k < 0) return;
    const s = G.strokes[k];
    const cycle = s.tap ? 1400 : 2600, run = s.tap ? 600 : 1700;
    const ph = (t - hintT0) % cycle;
    if (ph > run) return;
    const p = pointAt(s, s.tap ? 0.5 : ph / run);
    const x = V.ox + p.x * V.s, y = V.oy + p.y * V.s;
    fctx.beginPath(); fctx.arc(x, y, V.L * 0.5, 0, 7);
    fctx.fillStyle = 'rgba(255,210,63,.45)'; fctx.fill();
    fctx.font = `${Math.round(V.L * 1.1)}px system-ui, "Apple Color Emoji", "Segoe UI Emoji", sans-serif`;
    fctx.textAlign = 'center'; fctx.textBaseline = 'top';
    fctx.fillText('👆', x + V.L * 0.12, y - V.L * 0.08);
  }

  /* ---------- Palette ---------- */
  function buildPalette() {
    const box = $('#swatches'); box.innerHTML = '';
    SWATCHES.forEach(sw => {
      const b = document.createElement('button');
      b.className = 'swatch' + (sw.c ? '' : ' ' + sw.id) + (S.brush === sw.id ? ' on' : '');
      if (sw.c) b.style.setProperty('--c', sw.c);
      b.setAttribute('aria-label', sw.id);
      b.addEventListener('click', () => {
        S.brush = sw.id; store.set('brush', sw.id);
        $$('.swatch', box).forEach(x => x.classList.toggle('on', x === b));
        tone(PENTA[SWATCHES.indexOf(sw)], 0.18, 'triangle', 0.15);
        const r = b.getBoundingClientRect(); burst(r.left + r.width / 2, r.top, 6, sw.id === 'glitter' ? 'spark' : 'star');
      });
      box.appendChild(b);
    });
  }

  /* ---------- Buttons ---------- */
  $('#playBtn').addEventListener('click', () => {
    audio(); unlockSpeech(); enterFS(); sfx.fanfare();
    show('home');
    setTimeout(() => say(S.set === '123' ? "Let's draw numbers!" : "Let's draw letters!"), 300);
  });
  $('#homeBtn').addEventListener('click', () => {
    sfx.tap(); stopTalking(); stopCount();
    if (S.set === '123') S.group = GROUPS.length > 1 ? groupOf(cur().num) : 0;   // back to the numbers he was on
    buildGrid(); show('home');
  });
  $('#prevBtn').addEventListener('click', () => openLetter(S.idx - 1, -1));
  $('#nextBtn').addEventListener('click', () => openLetter(S.idx + 1, 1));
  $('#wordCard').addEventListener('click', () => {
    audio(); updateWordCard();
    if (isNum(cur())) countUp(); else speakLetter();
    const r = $('#wcPic').getBoundingClientRect(); burst(r.left + r.width / 2, r.top + r.height / 2, 8, 'star');
  });
  $$('.mode-btn').forEach(b => b.addEventListener('click', () => {
    if (S.mode === b.dataset.mode) return;
    S.mode = b.dataset.mode; store.set('mode', S.mode); sfx.boing();
    $$('.mode-btn').forEach(x => x.classList.toggle('on', x === b));
    inkStrokes = []; completed = false; resetTrace(); drawGuide(); redrawInk();
    $('#nextBtn').classList.remove('glow');
    hintT0 = now() + 2500;
  }));
  $$('.case-btn').forEach(b => b.addEventListener('click', () => {
    S.case = S.case === 'upper' ? 'lower' : 'upper'; store.set('case', S.case);
    sfx.boing(); buildGrid();
    if ($('#board').classList.contains('active')) openLetter(S.idx);
  }));
  $('#clearBtn').addEventListener('click', () => {
    sfx.swoosh();
    for (let i = 0; i < 30; i++) particles.push(mkParticle('spark', rand(0, W), rand(H * 0.2, H * 0.8), { color: '#cfe8ff', size: rand(8, 16) }));
    inkStrokes = []; completed = false; resetTrace(); drawGuide(); redrawInk();
    $('#nextBtn').classList.remove('glow');
  });
  $('#saveBtn').addEventListener('click', () => saveDrawing(false));

  /* ---------- Counting (tap the number card) ---------- */
  /* Lays out n things: one by one up to 20 (rows of 5, or a row of 10 then the rest),
     above 20 as bars of ten plus the ones. Returns the steps to count: [{ el, k }]. */
  function renderCount(box, n, animate) {
    const e = thing(n)[0];
    box.innerHTML = '';
    const steps = [];
    const mk = (cls, parent) => { const s = document.createElement('span'); s.className = cls + (animate ? '' : ' in'); s.textContent = e; parent.appendChild(s); return s; };
    if (n <= 20) {
      const wrap = document.createElement('div');
      wrap.className = 'count-grid';
      wrap.style.setProperty('--cols', n <= 10 ? Math.min(n, 5) : 10);
      wrap.style.setProperty('--fs', n <= 5 ? 1 : n <= 10 ? .8 : .55);
      for (let i = 1; i <= n; i++) {
        const s = mk('c-thing', wrap);
        if (!animate) s.style.animationDelay = (i * 40) + 'ms';
        steps.push({ el: s, k: i });
      }
      box.appendChild(wrap);
    } else {
      const wrap = document.createElement('div');
      wrap.className = 'count-bars';
      const tens = Math.floor(n / 10), ones = n % 10;
      for (let t = 1; t <= tens; t++) {
        const bar = document.createElement('div');
        bar.className = 'c-bar' + (animate ? '' : ' in');
        if (!animate) bar.style.animationDelay = (t * 60) + 'ms';
        for (let i = 0; i < 10; i++) { const s = document.createElement('span'); s.className = 'c-mini'; s.textContent = e; bar.appendChild(s); }
        wrap.appendChild(bar);
        steps.push({ el: bar, k: t * 10 });
      }
      if (ones) {
        const row = document.createElement('div');
        row.className = 'c-ones';
        for (let i = 1; i <= ones; i++) {
          const s = mk('c-thing', row);
          if (!animate) s.style.animationDelay = ((tens + i) * 60) + 'ms';
          steps.push({ el: s, k: tens * 10 + i });
        }
        wrap.appendChild(row);
      }
      box.appendChild(wrap);
    }
    return steps;
  }
  let countRun = 0;
  function stopCount() {
    countRun++;
    $('#countBox').classList.remove('active');
  }
  /* Say one counting word, then carry on. Never waits on a voice that doesn't finish. */
  function sayStep(text, then) {
    let done = false;
    const go = () => { if (!done) { done = true; then(); } };
    if (B.voice && 'speechSynthesis' in window) { say(text, () => setTimeout(go, 80)); setTimeout(go, 1500); }
    else setTimeout(go, 650);
  }
  function countUp() {
    const n = cur().num, run = ++countRun;
    stopTalking();
    const box = $('#countBox'), big = $('#countSay');
    const steps = renderCount($('#countStage'), n, true);
    big.textContent = n === 0 ? '0' : '';
    box.classList.remove('active'); void box.offsetWidth; box.classList.add('active');
    const finish = () => {
      if (run !== countRun) return;
      sfx.chime();
      const r = big.getBoundingClientRect(); burst(r.left + r.width / 2, r.top + r.height / 2, 14, 'star');
      if (n <= 20) say(n === 0 ? `Zero! ${numPhrase(0)}` : numPhrase(n));
      setTimeout(() => { if (run === countRun) stopCount(); }, n <= 20 ? 3200 : 2400);
    };
    let i = 0;
    const next = () => {
      if (run !== countRun) return;
      if (i >= steps.length) { finish(); return; }
      const st = steps[i++];
      st.el.classList.add('in');
      sfx.pop(i - 1);
      big.textContent = st.k;
      big.classList.remove('bump'); void big.offsetWidth; big.classList.add('bump');
      // above 20 it counts the bars by tens, then the ones, and ends on the whole number: "Forty-two!"
      sayStep(i === steps.length && n > 20 ? cap(NUMWORD(st.k)) + '!' : NUMWORD(st.k), next);
    };
    setTimeout(next, 350);
  }
  $('#countBox').addEventListener('pointerdown', e => { e.preventDefault(); stopTalking(); stopCount(); });

  /* ---------- Fridge ---------- */
  function snapshot() {
    const a = V.area; const pad = 10;
    const sx = Math.max(0, a.x - pad), sy = Math.max(0, a.y - pad);
    const sw = Math.min(W - sx, a.w + pad * 2), sh = Math.min(H - sy, a.h + pad * 2);
    const scale = Math.min(1400 / (sw * DPR), 1) * DPR;
    const c = document.createElement('canvas');
    c.width = Math.round(sw * scale); c.height = Math.round(sh * scale);
    const x = c.getContext('2d');
    x.fillStyle = '#fffaf0'; x.fillRect(0, 0, c.width, c.height);
    [guide, ink].forEach(src => x.drawImage(src, sx * DPR, sy * DPR, sw * DPR, sh * DPR, 0, 0, c.width, c.height));
    return c.toDataURL('image/jpeg', 0.88);
  }
  let saving = false;
  function saveDrawing(quiet) {
    if (saving) return;
    if (!inkStrokes.length) { if (!quiet) { sfx.boing(); wiggle($('#saveBtn')); } return; }
    saving = true;
    const url = snapshot();
    if (!quiet) flyToFridge(url);
    sfx.magnet();
    fetch('api/drawings.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign(isNum(cur()) ? { number: cur().num } : { letter: cur().letter, case: S.case }, { image: url, mode: S.mode, device: deviceId }))
    }).catch(() => {}).finally(() => { saving = false; });
  }
  function wiggle(el) { el.classList.remove('saved'); void el.offsetWidth; el.classList.add('saved'); }
  function flyToFridge(url) {
    const a = V.area, img = document.createElement('img');
    img.src = url; img.className = 'flyer';
    Object.assign(img.style, { left: a.x + 'px', top: a.y + 'px', width: a.w + 'px', height: a.h + 'px' });
    document.body.appendChild(img);
    const r = $('#saveBtn').getBoundingClientRect();
    requestAnimationFrame(() => requestAnimationFrame(() => {
      Object.assign(img.style, { left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', height: r.height + 'px', opacity: '.2', transform: 'rotate(-20deg)' });
    }));
    setTimeout(() => { img.remove(); wiggle($('#saveBtn')); burst(r.left + r.width / 2, r.top + r.height / 2, 12, 'star'); }, 950);
  }
  async function openFridge() {
    sfx.tap();
    const ov = $('#fridge'); ov.classList.add('active');
    const grid = $('#fridgeGrid'); grid.innerHTML = '';
    let list = [];
    try { list = (await (await fetch('api/drawings.php', { cache: 'no-store' })).json()).drawings || []; } catch (e) {}
    $('#fridgeEmpty').style.display = list.length ? 'none' : 'block';
    list.forEach((d, i) => {
      const f = document.createElement('figure');
      f.style.setProperty('--r', rand(-5, 5).toFixed(1) + 'deg');
      f.style.setProperty('--m', CONF_COLORS[i % CONF_COLORS.length]);
      f.innerHTML = `<img src="${d.url}" alt="" loading="lazy"><figcaption>${escapeHtml(String(d.letter))}</figcaption>`;
      f.addEventListener('click', () => {
        sfx.tap(); const z = $('#fridgeZoom'); $('img', z).src = d.url; z.classList.add('active');
      });
      grid.appendChild(f);
    });
  }
  $('#fridgeBtnHome').addEventListener('click', openFridge);
  $('#fridgeBtnHome').classList.toggle('hidden', !B.fridge);
  $('#fridgeClose').addEventListener('click', () => { sfx.tap(); $('#fridge').classList.remove('active'); });
  $('#fridgeZoom').addEventListener('click', () => $('#fridgeZoom').classList.remove('active'));

  /* ---------- Keep little fingers from zooming, scrolling or opening menus ---------- */
  ['gesturestart', 'gesturechange', 'dblclick', 'contextmenu'].forEach(ev => document.addEventListener(ev, e => e.preventDefault(), { passive: false }));
  document.addEventListener('touchmove', e => {
    if (!e.target.closest('#home, .fridge-door, .swatches')) e.preventDefault();
  }, { passive: false });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { stopTalking(); stopCount(); stopLoop(); }
    else if ($('#board').classList.contains('active')) startLoop();
  });

  /* ---------- Go ---------- */
  document.title = B.title;
  titleSpans($('#splashTitle'), B.title);
  titleSpans($('#homeTitle'), B.title);
  buildPalette(); buildGrid(); updateFSButtons();
  // Read-only peek for testing from the browser console.
  window.EzraABC = { get view() { return V; }, get geom() { return G; }, get trace() { return T; } };
})();
