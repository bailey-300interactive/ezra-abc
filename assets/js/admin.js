/* Ezra ABC admin: record a voice clip right in the browser (letters, numbers and the "Hooray!" clips).
   Each 🎙️ button says where to send it: data-post (page), data-fields (JSON form fields), data-title,
   data-say (what to say) and data-back (the #anchor to come back to). */
(() => {
  const dlg = document.getElementById('recDialog');
  if (!dlg) return;
  const $ = id => document.getElementById(id);
  let rec = null, stream = null, chunks = [], blob = null, target = null, t0 = 0, timer = 0, ac = null, raf = 0;

  function pickType() {
    const types = ['audio/mp4', 'audio/webm;codecs=opus', 'audio/webm', 'audio/ogg'];
    return types.find(t => window.MediaRecorder && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t)) || '';
  }
  function stopStream() {
    clearInterval(timer); cancelAnimationFrame(raf);
    if (stream) stream.getTracks().forEach(t => t.stop());
    stream = null;
    if (ac) { ac.close(); ac = null; }
  }
  function ui(state) {
    $('recStop').hidden = state !== 'recording';
    $('recAgain').hidden = $('recSave').hidden = $('recPreview').hidden = state !== 'done';
  }
  async function start() {
    blob = null; chunks = []; ui('recording');
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (e) {
      alert('The microphone is blocked. Allow it for this site (it needs HTTPS), or use "Upload clip" instead.');
      dlg.close(); return;
    }
    const type = pickType();
    rec = new MediaRecorder(stream, type ? { mimeType: type } : undefined);
    rec.ondataavailable = e => e.data.size && chunks.push(e.data);
    rec.onstop = () => {
      blob = new Blob(chunks, { type: rec.mimeType || type || 'audio/webm' });
      $('recPreview').src = URL.createObjectURL(blob);
      stopStream(); ui('done');
    };
    rec.start();
    t0 = performance.now();
    timer = setInterval(() => {
      const s = (performance.now() - t0) / 1000;
      $('recTime').textContent = s.toFixed(1) + ' s';
      if (s > 15) rec.state === 'recording' && rec.stop();
    }, 100);
    // level meter
    ac = new (window.AudioContext || window.webkitAudioContext)();
    const an = ac.createAnalyser(); an.fftSize = 512;
    ac.createMediaStreamSource(stream).connect(an);
    const d = new Uint8Array(an.fftSize);
    const tick = () => {
      an.getByteTimeDomainData(d);
      let m = 0; for (const v of d) m = Math.max(m, Math.abs(v - 128));
      $('recLevel').style.width = Math.min(100, m / 1.1) + '%';
      raf = requestAnimationFrame(tick);
    };
    tick();
  }

  document.querySelectorAll('.rec').forEach(b => b.addEventListener('click', () => {
    if (!window.MediaRecorder) { alert('This browser can\'t record. Use "Upload clip" instead.'); return; }
    target = { post: b.dataset.post, fields: JSON.parse(b.dataset.fields || '{}'), back: b.dataset.back || '', name: b.dataset.name || 'clip' };
    $('recLetter').textContent = b.dataset.title || '';
    $('recSay').textContent = b.dataset.say || '';
    $('recTime').textContent = '0.0 s';
    dlg.showModal(); start();
  }));
  $('recStop').addEventListener('click', () => rec && rec.state === 'recording' && rec.stop());
  $('recAgain').addEventListener('click', start);
  $('recCancel').addEventListener('click', () => { if (rec && rec.state === 'recording') { rec.onstop = null; rec.stop(); } stopStream(); dlg.close(); });
  $('recSave').addEventListener('click', async () => {
    if (!blob) return;
    const ext = blob.type.includes('mp4') ? 'm4a' : blob.type.includes('ogg') ? 'ogg' : 'webm';
    const fd = new FormData();
    fd.append('csrf', window.CSRF); fd.append('ajax', '1');
    Object.entries(target.fields).forEach(([k, v]) => fd.append(k, v));
    fd.append('clip', blob, `${target.name}.${ext}`);
    $('recSave').disabled = true;
    try {
      const r = await fetch(target.post, { method: 'POST', body: fd });
      const j = await r.json();
      if (!r.ok) throw new Error(j.error || 'Upload failed');
      if (target.back) location.hash = target.back;
      location.reload();
    } catch (e) { alert(e.message); }
    $('recSave').disabled = false;
  });
})();
