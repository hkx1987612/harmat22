import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

// Synthetic browser fixtures only: no network, WordPress, customer or mail calls.
const source = readFileSync(new URL('../../wp-plugins/360/viewer.js', import.meta.url), 'utf8');
const flush = async () => { for (let i = 0; i < 16; i++) await Promise.resolve(); };
const frameData = () => Object.fromEntries(Array.from({ length: 72 }, (_, i) => [i + 1, {
  building: `M ${i + 1} 0 L 100 100 Z`, static: 'M 0 0 Z', apt: 'M 1 1 Z', sold: 'M 2 2 Z',
}]));

function harness({ canvas = true, decode = true, json = 'ok', data = frameData(), buildingLinks = true,
  saveData = false, synchronousDecodeError = false } = {}) {
  let now = 0, serial = 0, fetchCalls = 0, aborted = false, fetchOptions;
  const timers = new Map(), images = [], paint = [];
  class Element {
    constructor() {
      this.style = {}; this.dataset = {}; this.listeners = {}; this.children = []; this.attributes = {};
      this.parentNode = null; this.textContent = ''; this._html = '';
      const classes = new Set();
      this.classList = {
        add: (...names) => names.forEach(name => classes.add(name)),
        remove: (...names) => names.forEach(name => classes.delete(name)),
        contains: name => classes.has(name),
        toggle: name => classes.has(name) ? classes.delete(name) : classes.add(name),
      };
    }
    set innerHTML(value) { this._html = value; this.children = []; }
    get innerHTML() { return this._html; }
    addEventListener(name, fn) { (this.listeners[name] ||= []).push(fn); }
    emit(name, event = {}) { for (const fn of this.listeners[name] || []) fn({ target: this, ...event }); }
    setAttribute(name, value) { this.attributes[name] = value; }
    getAttribute(name) { return this.attributes[name]; }
    removeAttribute(name) { delete this.attributes[name]; if (name === 'src') this.src = ''; }
    appendChild(child) { this.children.push(child); child.parentNode = this; }
    insertBefore(child) { this.appendChild(child); }
    removeChild(child) { this.children.splice(this.children.indexOf(child), 1); child.parentNode = null; }
    scrollTo() {}
  }
  class FakeImage extends Element {
    constructor() {
      super(); this.src = ''; this.complete = false; this.naturalWidth = 0; this.naturalHeight = 0;
      this.decoded = false;
      this.decodePromise = new Promise((resolve, reject) => {
        this.resolveDecode = () => { this.decoded = true; resolve(); }; this.rejectDecode = reject;
      });
      if (!decode) this.decode = undefined;
      images.push(this);
    }
    decode() { if (synchronousDecodeError) throw new Error('decode failed'); return this.decodePromise; }
    load() { this.complete = true; this.naturalWidth = 1920; this.naturalHeight = 1080; this.onload?.(); this.emit('load'); }
    fail() { this.complete = true; this.naturalWidth = 0; this.onerror?.(); this.emit('error'); }
  }
  const ids = {};
  for (const id of ['mainLayout', 'buildingViewer', 'hitboxLayer', 'viewerTooltip', 'compassSlider',
    'rotateLeftBtn', 'rotateRightBtn', 'lakasparkLoader', 'loaderBarFill', 'loaderPercent', 'loaderText',
    'apartmentList', 'resultCount', 'filterRooms']) ids[id] = new Element();
  const floor = new Element(); floor.setAttribute('data-val', '1');
  const poster = ids.lakasparkPoster = new FakeImage();
  poster.src = '/frames/bld-test-frame-01.webp'; poster.style.opacity = '1';
  ids.buildingViewer.appendChild(poster);
  const directLink = new Element();
  directLink.href = '/building/';
  let surface;
  const document = {
    hidden: false,
    addEventListener: (event, handler) => { if (event === 'DOMContentLoaded') document.start = handler; },
    getElementById: id => ids[id] || null,
    querySelectorAll: selector => selector === '.floor-btn' ? [floor] : [],
    querySelector: selector => selector === '.lakaspark-building-links' && buildingLinks ? directLink : null,
    createElement: tag => {
      const el = new Element();
      if (tag === 'canvas') {
        surface = el;
        let width = 300, height = 150, clears = 0;
        Object.defineProperties(el, {
          width: { get: () => width, set: value => { width = value; clears++; } },
          height: { get: () => height, set: value => { height = value; clears++; } },
          clears: { get: () => clears },
        });
        el.getContext = () => canvas ? { drawImage: img => {
          assert.ok(img.complete && img.naturalWidth);
          assert.ok(!decode || img.decoded);
          if (img.drawFails) throw new Error('paint failed');
          paint.push(img.src);
        } } : null;
      }
      return el;
    },
    createElementNS: () => new Element(),
  };
  const schedule = (fn, delay, repeat = false) => {
    const id = ++serial; timers.set(id, { fn, due: now + delay, delay, repeat }); return id;
  };
  const window = {
    LakasparkData: {
      scene: 'test', baseUrl: '/frames/', jsonUrl: '/hitboxes.json', toggle: 'on',
      customLinks: { building: '/building/' }, staticHitboxes: { static: 'Park' },
      apartments: { apt: { name: 'APT', status: 'reserved', floor: '1', rooms: 2, link: '/apt/' },
        sold: { name: 'SOLD', status: 'sold', floor: '1', rooms: 2 } },
    },
    location: { href: '' }, history: { back() {} }, addEventListener() {},
    setTimeout: (fn, delay = 0) => schedule(fn, delay),
  };
  class AbortController {
    constructor() { this.signal = {}; }
    abort() { aborted = true; }
  }
  let resolveFetch, rejectFetch;
  const fetchPromise = new Promise((resolve, reject) => { resolveFetch = resolve; rejectFetch = reject; });
  const response = value => ({ ok: true, json: async () => value });
  const context = vm.createContext({
    window, document, navigator: { connection: { saveData } }, Image: FakeImage, AbortController,
    console, Promise,
    setInterval: (fn, delay) => schedule(fn, delay, true), clearInterval: id => timers.delete(id),
    setTimeout: window.setTimeout, clearTimeout: id => timers.delete(id),
    fetch: (url, options) => {
      fetchCalls++; fetchOptions = options;
      if (json === 'ok') return Promise.resolve(response(data));
      if (json === 'http') return Promise.resolve({ ok: false, json: async () => data });
      if (json === 'reject') return Promise.reject(new Error('network'));
      if (json === 'parse') return Promise.resolve({ ok: true, json: async () => { throw new Error('parse'); } });
      return fetchPromise;
    },
  });
  vm.runInContext(source, context); document.start();
  const image = frame => images.findLast(img => img.src.endsWith(`-${String(frame).padStart(2, '0')}.webp`));
  const go = frame => { ids.compassSlider.value = frame; ids.compassSlider.emit('input'); };
  const ready = async frame => { const img = image(frame); assert.ok(img, `Frame ${frame} requested`); img.load(); img.resolveDecode(); await flush(); };
  const advance = milliseconds => {
    const end = now + milliseconds;
    let ticks = 0;
    while (true) {
      const next = [...timers.entries()].filter(([, t]) => t.due <= end).sort((a, b) => a[1].due - b[1].due)[0];
      if (!next) break;
      assert.ok(++ticks < 10000, 'No timer retry spin');
      const [id, timer] = next; now = timer.due;
      if (timer.repeat) timer.due += timer.delay; else timers.delete(id);
      timer.fn();
    }
    now = end;
  };
  return { ids, poster, surface, images, paint, image, go, ready, advance, window, floor, directLink,
    status: () => ids.buildingViewer.children.find(el => el.className === 'viewer-load-status'),
    resident: () => images.filter(img => img.src).length,
    fetchCalls: () => fetchCalls, fetchOptions: () => fetchOptions, aborted: () => aborted,
    resolveJson: value => resolveFetch(response(value)), rejectJson: rejectFetch };
}
async function started(options) { const h = harness(options); await flush(); await h.ready(1); h.advance(500); return h; }

test('initial paint remains full resolution and warms only adjacent frames, never 72 eagerly', async () => {
  const h = await started();
  assert.equal(h.surface.width, 1920); assert.equal(h.surface.height, 1080);
  assert.equal(h.surface.dataset.frame, '1'); assert.equal(h.images.length, 3);
  h.advance(120000); await flush();
  assert.equal(h.images.length, 3); assert.equal(h.surface.clears, 2);
});
test('save-data warms no adjacent images', async () => {
  const h = await started({ saveData: true }); assert.equal(h.images.length, 1);
});
test('stalled poster releases overlay within deadline without fake success; direct links stay reachable', async () => {
  const h = harness(); await flush();
  assert.equal(h.ids.lakasparkLoader.style.pointerEvents, 'none');
  h.advance(10500); await flush();
  assert.equal(h.ids.lakasparkLoader.style.display, 'none');
  assert.notEqual(h.ids.loaderPercent.innerText, '100%');
  assert.equal(h.status().attributes.role, 'status'); assert.equal(h.status().style.pointerEvents, 'none');
  assert.match(h.status().textContent, /épületgombokkal/);
  assert.equal(h.directLink.href, '/building/'); assert.equal(h.paint.length, 0);
});
test('stalled decode retains poster, exits loader and recovers on usable destination', async () => {
  const h = harness(); await flush(); h.poster.load(); await flush();
  h.advance(10500); assert.equal(h.ids.lakasparkLoader.style.display, 'none');
  assert.equal(h.poster.style.opacity, '1'); assert.equal(h.paint.length, 0);
  await h.ready(2);
  assert.equal(h.surface.dataset.frame, '2'); assert.equal(h.status().style.display, 'none');
});
test('failed poster dismisses blocking feedback immediately and permits one neighbor recovery', async () => {
  const h = harness(); await flush(); h.poster.fail(); h.advance(500);
  assert.equal(h.ids.lakasparkLoader.style.display, 'none');
  assert.notEqual(h.ids.loaderPercent.innerText, '100%');
  await h.ready(2); assert.equal(h.surface.dataset.frame, '2');
});
test('late original poster recovery is paintable after a timeout', async () => {
  const h = harness(); await flush(); h.advance(10500);
  h.go(1); await h.ready(1);
  assert.equal(h.surface.dataset.frame, '1'); assert.equal(h.status().style.display, 'none');
});
for (const failure of ['http', 'reject', 'parse']) test(`JSON ${failure} fails nonblockingly without retries`, async () => {
  const h = harness({ json: failure }); await flush(); h.advance(500);
  assert.equal(h.fetchCalls(), 1); assert.match(h.status().textContent, /nem érhető el/);
  assert.equal(h.ids.lakasparkLoader.style.display, 'none');
  await h.ready(1); assert.equal(h.surface.dataset.frame, '1');
  assert.match(h.status().textContent, /nem érhető el/);
});
test('stalled JSON aborts at deadline and late success cannot overwrite terminal status', async () => {
  const h = await started({ json: 'stall' }); h.advance(10000); await flush();
  assert.equal(h.aborted(), true); assert.ok(h.fetchOptions().signal);
  assert.equal(h.status().style.display, 'block');
  h.resolveJson(frameData()); await flush();
  assert.match(h.status().textContent, /nem érhető el/); assert.equal(h.ids.hitboxLayer.children.length, 0);
  assert.equal(h.fetchCalls(), 1);
});
test('late JSON rejection after timeout does not create another status or retry', async () => {
  const h = harness({ json: 'stall' }); h.advance(10500); h.rejectJson(new Error('aborted')); await flush();
  assert.equal(h.fetchCalls(), 1);
  assert.equal(h.ids.buildingViewer.children.filter(el => el.className === 'viewer-load-status').length, 1);
});
test('A4 array-root JSON keeps one-based frames including object index zero', async () => {
  const data = Array.from({ length: 73 }, (_, i) => ({ building: `M ${i} 0 Z` }));
  const h = await started({ data });
  assert.equal(h.status().style.display, 'none');
  assert.equal(h.ids.hitboxLayer.children[0].attributes.d, 'M 1 0 Z');
  h.go(72); await h.ready(72);
  assert.equal(h.ids.hitboxLayer.children[0].attributes.d, 'M 72 0 Z');
});
test('invalid JSON shape is not treated as a functional selector', async () => {
  const h = await started({ data: { 1: null } });
  assert.match(h.status().textContent, /nem érhető el/); assert.equal(h.ids.hitboxLayer.children.length, 0);
});
test('slow rotation keeps pixels and matching hitboxes until latest pending frame is ready', async () => {
  const h = await started(); h.go(20); h.image(20).load(); await flush();
  assert.equal(h.surface.dataset.frame, '1');
  assert.equal(h.ids.hitboxLayer.children[0].attributes.d, 'M 1 0 L 100 100 Z');
  h.go(30); await h.ready(30); h.image(20).resolveDecode(); await flush();
  assert.equal(h.surface.dataset.frame, '30');
  assert.equal(h.ids.hitboxLayer.children[0].attributes.d, 'M 30 0 L 100 100 Z');
});
test('bounded cache handles forward and reverse full cycles without clearing the one canvas', async () => {
  const h = await started();
  for (const frame of [...Array.from({ length: 71 }, (_, i) => i + 2), ...Array.from({ length: 72 }, (_, i) => 72 - i)]) {
    h.go(frame); if (h.image(frame).dataset.loaded !== '1') await h.ready(frame);
    assert.equal(h.surface.dataset.frame, String(frame)); assert.ok(h.resident() <= 9);
    assert.equal(h.surface.width, 1920); assert.equal(h.surface.height, 1080); assert.equal(h.surface.clears, 2);
  }
  assert.ok(h.ids.buildingViewer.children.filter(el => el instanceof h.poster.constructor).length <= 1);
});
test('eviction cancels old sources and stale decode callbacks cannot paint or corrupt residency', async () => {
  const h = await started(); h.go(20); const old = h.image(20); old.load();
  for (const frame of [30, 40, 50, 60]) { h.go(frame); await h.ready(frame); }
  assert.equal(old.src, ''); assert.equal(old.dataset.retired, '1');
  const paints = h.paint.length; old.resolveDecode(); await flush();
  assert.equal(h.paint.length, paints); assert.equal(h.surface.dataset.frame, '60'); assert.ok(h.resident() <= 9);
  h.go(20); assert.notEqual(h.image(20), old); await h.ready(20);
  assert.equal(h.surface.dataset.frame, '20');
});
test('rapid requests make progress to latest pending selection without resurrecting evicted loads', async () => {
  const h = await started();
  for (let frame = 2; frame <= 72; frame++) { h.go(frame); assert.ok(h.resident() <= 9); }
  await h.ready(72); assert.equal(h.surface.dataset.frame, '72'); assert.equal(h.ids.compassSlider.value, 72);
  h.advance(10500); await flush(); assert.equal(h.surface.dataset.frame, '72');
});
test('stalled requested frame is bounded, not refetched repeatedly, and next selection recovers', async () => {
  const h = await started(); h.go(20); const old = h.image(20); h.advance(10500); await flush();
  assert.equal(h.surface.dataset.frame, '1'); assert.equal(h.status().style.display, 'block');
  h.go(20); assert.equal(h.image(20), old);
  h.go(21); await h.ready(21); assert.equal(h.surface.dataset.frame, '21'); assert.equal(h.status().style.display, 'none');
});
test('native image fallback bounds attached images and leaves current one visible', async () => {
  const h = await started({ canvas: false, decode: false });
  for (let frame = 2; frame <= 30; frame++) {
    h.go(frame); await h.ready(frame); assert.equal(h.image(frame).style.opacity, '1');
    assert.ok(h.resident() <= 9);
    assert.ok(h.ids.buildingViewer.children.filter(el => el instanceof h.poster.constructor).length <= 9);
  }
});
test('synchronous decoder throw and draw error do not clear old pixels', async () => {
  const h = harness({ synchronousDecodeError: true }); await flush(); h.poster.load(); await flush();
  h.advance(500); assert.equal(h.ids.lakasparkLoader.style.display, 'none'); assert.equal(h.paint.length, 0);
  const p = await started(); p.go(20); p.image(20).drawFails = true; await p.ready(20);
  assert.equal(p.surface.dataset.frame, '1'); assert.equal(p.surface.clears, 2);
});
test('SVG custom links preserve click and keyboard activation, static/sold regions remain nonactivating', async () => {
  const h = await started(); const paths = h.ids.hitboxLayer.children;
  const byId = id => paths.find(el => el.attributes['data-id'] === id);
  assert.equal(byId('building').attributes.role, 'link');
  byId('building').emit('click'); assert.equal(h.window.location.href, '/building/');
  for (const key of ['Enter', ' ']) {
    h.window.location.href = ''; let prevented = false;
    byId('building').emit('keydown', { key, preventDefault() { prevented = true; } });
    assert.equal(prevented, true); assert.equal(h.window.location.href, '/building/');
  }
  assert.equal(byId('static').attributes.role, undefined); assert.equal(byId('sold').attributes.role, undefined);
  assert.equal(byId('apt').attributes.role, 'button');
  byId('apt').emit('click'); assert.ok(byId('apt').classList.contains('active'));
});
test('native room/floor controls and reserved cards remain present', async () => {
  const h = await started(); assert.equal(h.ids.apartmentList.children.length, 2);
  assert.match(h.ids.apartmentList.children[0].innerHTML, /Egyeztetés alatt/);
  h.ids.filterRooms.value = '3'; h.ids.filterRooms.emit('change');
  assert.equal(h.ids.apartmentList.children.length, 0);
  h.ids.filterRooms.value = '2'; h.ids.filterRooms.emit('change'); h.floor.emit('click');
  assert.equal(h.ids.apartmentList.children.length, 2);
});
test('slow building scene describes only navigation actually available', async () => {
  const h = harness({ buildingLinks: false }); await flush(); h.advance(10500);
  assert.match(h.status().textContent, /lakáslistából/); assert.doesNotMatch(h.status().textContent, /épületgomb/);
  const p = harness({ buildingLinks: false, json: 'reject' }); await flush();
  assert.doesNotMatch(p.status().textContent, /épületgomb|lakáslistából/);
});
