/*
 * Invisible challenge for Laravel: solves the server's puzzle quietly in the background.
 * - Forms with a data-challenge attribute get the answer as hidden fields just before they submit.
 * - For fetch(), spread the headers in: fetch(url, { headers: { ...(await window.invisibleChallenge()) } }).
 * The puzzle is fetched on the first sign of a person (a tap, key press or focus), so it's ready before Send.
 */
(() => {
  'use strict';
  const url = window.invisibleChallengeUrl || '/invisible-challenge';
  let pending = null;

  async function solve() {
    const started = performance.now();
    const res = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' });
    if (!res.ok) throw new Error('challenge');
    const { challenge, difficulty, min_seconds: minSeconds } = await res.json();
    const enc = new TextEncoder(), full = difficulty >> 3, rest = difficulty & 7;
    const ok = (h) => { for (let i = 0; i < full; i++) if (h[i]) return false; return !rest || h[full] >> (8 - rest) === 0; };
    for (let n = 0; ; n += 256) {
      const batch = await Promise.all(Array.from({ length: 256 }, (_, i) => crypto.subtle.digest('SHA-256', enc.encode(`${challenge}:${n + i}`))));
      const hit = batch.findIndex((b) => ok(new Uint8Array(b)));
      if (hit >= 0) return { challenge, solution: String(n + hit), started, readyAt: started + minSeconds * 1000 + 300 };
    }
  }

  const prepare = () => { if (!pending) pending = solve().catch(() => { pending = null; return null; }); return pending; };

  /** Waits for an answer (and the minimum time), uses it up, and starts the next one. */
  async function take() {
    let solved = await prepare();
    pending = null;
    if (solved && performance.now() - solved.started > 25 * 60e3) { solved = await prepare(); pending = null; } // answers expire
    if (!solved) return null;
    const wait = solved.readyAt - performance.now();
    if (wait > 0) await new Promise((r) => setTimeout(r, wait));
    setTimeout(prepare, 50);
    return solved;
  }

  window.invisibleChallenge = async () => {
    const s = await take();
    return s ? { 'X-Challenge': s.challenge, 'X-Challenge-Solution': s.solution } : {};
  };

  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-challenge') || form.dataset.challengeReady === '1') return;
    e.preventDefault();
    const button = e.submitter;
    if (button) button.disabled = true;
    const s = await take();
    for (const [name, value] of [['_challenge', s?.challenge ?? ''], ['_challenge_solution', s?.solution ?? '']]) {
      let input = form.querySelector(`input[name="${name}"]`);
      if (!input) { input = Object.assign(document.createElement('input'), { type: 'hidden', name }); form.append(input); }
      input.value = value;
    }
    if (button) {
      button.disabled = false;
      if (button.name) form.append(Object.assign(document.createElement('input'), { type: 'hidden', name: button.name, value: button.value }));
    }
    form.dataset.challengeReady = '1';
    form.submit();
  });

  const start = () => { if (document.querySelector('[data-challenge]')) prepare(); };
  ['pointerdown', 'keydown', 'focusin'].forEach((t) => addEventListener(t, start, { once: true, passive: true }));
})();
