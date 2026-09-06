import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../wp-plugins/360/viewer.js', import.meta.url), 'utf8');
const flush = async () => { for (let i = 0; i < 8; i++) await Promise.resolve(); };

function harness({ canvas = true, brokenPoster = false, decode = true } = {}) {
  const timers = [];
  const intervals = [];
  const images = [];
  const paint = [];
  class Element {
    constructor() {
      this.style = {};
      this.dataset = {};
      this.listeners = {};
      this.children = [];
      this.attributes = {};
      const classes = new Set();
      this.classList = {
        add: (...names) => names.forEach(name => classes.add(name)),
        remove: (...names) => names.forEach(name => classes.delete(name)),
        contains: name => classes.has(name),
        toggle: name => classes.has(name) ? classes.delete(name) : classes.add(name),
      };
    }
    addEventListener(name, listener) { (this.listeners[name] ||= []).push(listener); }
    emit(name, data = {}) { for (const listener of this.listeners[name] || []) listener({ target: this, ...data }); }
    setAttribute(name, value) { this.attributes[name] = value; }
    getAttribute(name) { return this.attributes[name]; }
    insertBefore(child) { this.children.push(child); }
    appendChild(child) { this.children.push(child); }
  }
  class FakeImage extends Element {
    constructor() {
      super();
      this.complete = false;
      this.naturalWidth = 0;
      this.naturalHeight = 0;
      this.decoded = false;
      this.decodePromise = new Promise((resolve, reject) => {
        this.resolveDecode = () => { this.decoded = true; resolve(); };
        this.rejectDecode = reject;
      });
      if (!decode) this.decode = undefined;
      images.push(this);
    }
    decode() { return this.decodePromise; }
    load() {
      this.complete = true;
      this.naturalWidth = 1920;
      this.naturalHeight = 1080;
      this.onload?.();
      this.emit('load');
    }
    fail() {
      this.complete = true;
      this.naturalWidth = 0;
      this.onerror?.();
      this.emit('error');
    }
  }
  const ids = {};
  for (const id of ['mainLayout', 'buildingViewer', 'hitboxLayer', 'viewerTooltip',
    'compassSlider', 'rotateLeftBtn', 'rotateRightBtn', 'lakasparkLoader', 'loaderBarFill',
    'loaderPercent', 'loaderText']) ids[id] = new Element();
  const poster = ids.lakasparkPoster = new FakeImage();
  poster.src = '/frames/bld-test-frame-01.webp';
  poster.style.opacity = '1';
  if (brokenPoster) poster.complete = true;
  let surface;
  const document = {
    hidden: false,
    addEventListener: (event, handler) => { if (event === 'DOMContentLoaded') document.start = handler; },
    getElementById: id => ids[id] || null,
    querySelectorAll: () => [],
    querySelector: () => null,
    createElement: tag => {
      const el = new Element();
      if (tag === 'canvas') {
        surface = el;
        let width = 300, height = 150;
        let clears = 0;
        Object.defineProperties(el, {
          width: { get: () => width, set: v => { width = v; clears++; } },
          height: { get: () => height, set: v => { height = v; clears++; } },
          clears: { get: () => clears },
        });
        el.getContext = () => canvas ? {
          drawImage: img => {
            assert.ok(img.complete && img.naturalWidth);
            assert.ok(!decode || img.decoded, 'Do not paint a not-yet-decoded image');
            if (img.drawFails) throw new Error('decode evicted / draw failed');
            paint.push(img.src);
          },
        } : null;
      }
      return el;
    },
    createElementNS: () => new Element(),
  };
  const window = {
    LakasparkData: { scene: 'test', baseUrl: '/frames/', jsonUrl: '/hitboxes.json', toggle: 'off' },
    addEventListener: () => {},
    setTimeout: (fn, delay) => { timers.push({ fn, delay }); return timers.length; },
  };
  const context = vm.createContext({
    window, document, navigator: { connection: { saveData: true } }, Image: FakeImage,
    console, Promise,
    setInterval: fn => { intervals.push(fn); return intervals.length - 1; },
    clearInterval: id => { intervals[id] = null; },
    setTimeout: window.setTimeout, clearTimeout: () => {},
    fetch: async () => ({ json: async () => Object.fromEntries(Array.from({ length: 72 }, (_, i) => [i + 1, {}])) }),
  });
  vm.runInContext(source, context);
  document.start();
  const image = frame => images.find(img => img.src.endsWith(`-${String(frame).padStart(2, '0')}.webp`));
  const go = frame => { ids.compassSlider.value = frame; ids.compassSlider.emit('input'); };
  const ready = async frame => { image(frame).load(); image(frame).resolveDecode(); await flush(); };
  const tick = delay => {
    const pending = timers.filter(t => t.delay === delay);
    for (const t of pending) { timers.splice(timers.indexOf(t), 1); t.fn(); }
  };
  return { ids, poster, surface, paint, go, image, ready, tick, intervals };
}

async function started(options) {
  const h = harness(options);
  await flush();
  await h.ready(1);
  h.tick(0);
  await flush();
  return h;
}

test('poster is retained until decoded; first paint uses its full resolution', async () => {
  const h = harness();
  await flush();
  h.poster.load();
  await flush();
  assert.equal(h.poster.style.opacity, '1');
  assert.equal(h.paint.length, 0);
  h.poster.resolveDecode();
  await flush();
  assert.equal(h.surface.dataset.frame, '1');
  assert.equal(h.surface.width, 1920);
  assert.equal(h.surface.height, 1080);
});

test('slow decode holds the old painted frame until the destination is ready', async () => {
  const h = await started();
  h.go(20);
  h.image(20).load();
  await flush();
  assert.equal(h.surface.dataset.frame, '1');
  assert.equal(h.surface.style.opacity, '1');
  const paints = h.paint.length;
  h.image(20).resolveDecode();
  await flush();
  assert.equal(h.surface.dataset.frame, '20');
  assert.equal(h.paint.length, paints + 1);
  assert.equal(h.surface.clears, 2, 'No canvas clear/resize during rotation');
});

test('a failed image cannot replace the last successful frame', async () => {
  const h = await started();
  h.go(20);
  const paints = h.paint.length;
  h.image(20).fail();
  await flush();
  assert.equal(h.image(20).dataset.loaded, '');
  assert.equal(h.surface.dataset.frame, '1');
  assert.equal(h.paint.length, paints);
  h.go(21);
  await h.ready(21);
  assert.equal(h.surface.dataset.frame, '21');
});

test('decode rejection and drawing failure preserve the old surface', async () => {
  const h = await started();
  h.go(20);
  h.image(20).load();
  h.image(20).rejectDecode(new Error('bad image'));
  await flush();
  assert.equal(h.surface.dataset.frame, '1');
  h.go(30);
  h.image(30).drawFails = true;
  await h.ready(30);
  assert.equal(h.surface.dataset.frame, '1');
  assert.equal(h.surface.clears, 2);
});

test('late frame completion cannot undo a newer slider/rotation selection', async () => {
  const h = await started();
  h.go(20);
  h.image(20).load();
  h.go(30);
  await h.ready(30);
  h.image(20).resolveDecode();
  await flush();
  assert.equal(h.surface.dataset.frame, '30');
  assert.equal(h.ids.compassSlider.value, 30);
});

test('selecting an already-ready frame cancels an older pending request', async () => {
  const h = await started();
  h.go(20);
  h.go(1);
  await h.ready(20);
  assert.equal(h.surface.dataset.frame, '1');
});

test('rotation continues past unavailable frames and wraps at 72', async () => {
  const h = await started();
  h.ids.rotateRightBtn.emit('click');
  const spin = h.intervals.at(-1);
  for (let i = 0; i < 4; i++) spin();
  await h.ready(5);
  assert.equal(h.surface.dataset.frame, '5');
  h.go(72);
  await h.ready(72);
  h.ids.rotateRightBtn.emit('click');
  h.intervals.at(-1)();
  assert.equal(h.surface.dataset.frame, '1');
});

test('a broken completed poster is not marked successful and uses a neighbor', async () => {
  const h = harness({ brokenPoster: true });
  await flush();
  h.tick(0);
  assert.notEqual(h.poster.dataset.loaded, '1');
  await h.ready(2);
  assert.equal(h.surface.dataset.frame, '2');
});

test('native image fallback and browsers without decode keep working', async () => {
  const h = await started({ canvas: false, decode: false });
  assert.equal(h.poster.style.opacity, '1');
  h.go(2);
  await h.ready(2);
  assert.equal(h.image(2).style.opacity, '1');
  assert.equal(h.poster.style.opacity, '0');
});

test('full 72-frame cycles reuse one surface without clear, resize or resolution loss', async () => {
  const h = await started();
  for (let frame = 2; frame <= 72; frame++) {
    h.go(frame);
    await h.ready(frame);
    assert.equal(h.surface.dataset.frame, String(frame));
  }
  for (let frame = 71; frame >= 1; frame--) h.go(frame);
  assert.equal(h.surface.dataset.frame, '1');
  assert.equal(h.surface.clears, 2);
  assert.equal(h.surface.width, 1920);
  assert.equal(h.surface.height, 1080);
});
