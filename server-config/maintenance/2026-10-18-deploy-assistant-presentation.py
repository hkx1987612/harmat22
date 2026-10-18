"""Absent-only assistant presentation release. Default is offline; no lead sends."""
import argparse
from collections import Counter
import hashlib
from html.parser import HTMLParser
import importlib.util
import json
from pathlib import Path
import re
import shlex
import stat
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('assistant_presentation_home', HERE / '2026-10-18-deploy-home-family.py')
D = importlib.util.module_from_spec(spec)
spec.loader.exec_module(D)
H = D.H
DIR = D.DIR
ASSET = 'harmat-assistant-presentation'
MAIN = 'zz-harmat-assistant-presentation.php'
REQUIRED = (ASSET + '/assistant-presentation.css', ASSET + '/assistant-presentation.js')
LICENSE = ASSET + '/LICENSE-lucide.txt'
CHECKER = D.CHECKER
TEST = '2026-10-18-test-assistant-presentation-deploy.py'
SELF = Path(__file__).name
NATIVE = '2026-10-18-native-assistant-presentation.php'
PREFIX = H.BACKUPS + '/assistant-presentation-'
OLD = H.LIVE + '/wp-content/plugins/harmat-local-assistant/harmat-local-assistant.php'
HOME = ('zz-harmat-home-family.php', 'harmat-home-family/manifest.json')
CONSTRUCTION = ('zz-harmat-construction-updates.php', 'zz-harmat-construction-progress-video.php')
ROUTES = ('/', '/lakaskereso/', '/property/a1-1-l2/', '/virtualis-lakasvalaszto/',
          '/virtualis-lakasvalaszto-elso-utem/', '/galeria/', '/elerhetosegeink/', '/epitesi-naplo/', '/koszonjuk/')
ORIGIN = 'https://harmat22.hu'
require = D.require
absent = D.absent


def sha(data):
    return hashlib.sha256(data).hexdigest()


def encoded(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':'), ensure_ascii=True).encode('ascii')


def local_release():
    directory = ROOT / 'wp-mu-plugins' / ASSET
    files = REQUIRED + ((LICENSE,) if (directory / 'LICENSE-lucide.txt').exists() else ()) + (MAIN,)
    require(directory.is_dir() and not directory.is_symlink(), 'Regular local asset directory required')
    allowed = {Path(name).name for name in files if name != MAIN}
    require(all(p.name in allowed for p in directory.iterdir() if not p.is_dir()), 'Unexpected release asset')
    blobs = {}
    for name in files:
        path = ROOT / 'wp-mu-plugins' / name
        require(path.is_file() and not path.is_symlink(), 'Regular release file required: ' + name)
        data = path.read_bytes().replace(b'\r\n', b'\n')
        data.decode('utf-8', 'strict')
        require(data and b'\r' not in data and len(data) < 512 * 1024, 'Invalid release text: ' + name)
        blobs[name] = data
    controller = blobs[MAIN]
    pinned = re.findall(rb"['\"]([A-Za-z0-9._-]+)['\"]\s*=>\s*['\"]([a-f0-9]{64})['\"]", controller)
    require({n.decode() for n, _ in pinned} == allowed and len(pinned) == len(allowed), 'Exact controller asset pin set required')
    for name in files[:-1]:
        pair = rb"['\"]" + re.escape(Path(name).name.encode()) + rb"['\"]\s*=>\s*['\"]" + sha(blobs[name]).encode() + rb"['\"]"
        require(len(re.findall(pair, controller)) == 1, 'Controller asset pin mismatch: ' + name)
    for name in REQUIRED:
        data = blobs[name]
        require(not re.search(rb'\b(?:fetch\s*\(|XMLHttpRequest|sendBeacon|WebSocket|EventSource|import\s*\(|importScripts\s*\()', data),
                'Presentation network primitive refused')
        require(not re.search(rb'https?://(?!www\.w3\.org/2000/svg)|["\']\s*//', data), 'External presentation URL refused')
    require(not re.search(rb'@import|url\s*\(', blobs[REQUIRED[0]], re.I), 'CSS resource request refused')
    require(not re.search(rb'\b(?:register_rest_route|wp_remote_\w+|wp_mail|update_option|update_post_meta|wp_insert_post|rest_do_request)\s*\(', controller),
            'Non-presentation controller primitive refused')
    for name in (CHECKER, SELF, TEST, NATIVE):
        blobs[name] = (HERE / name).read_bytes().replace(b'\r\n', b'\n')
    pins = {name: sha(data) for name, data in blobs.items()}
    descriptor = {'schema': 1, 'files': list(files), 'pins': pins}
    return {'files': files, 'blobs': blobs, 'descriptor': descriptor, 'aggregate': sha(encoded(descriptor))}


def protected_paths(r):
    # Read current live pins, not an obsolete whole-controller/old-assistant hash.
    manifest = json.loads(r.read(DIR + '/' + HOME[1]))
    require(isinstance(manifest, dict) and manifest, 'Current home asset manifest required')
    require(all(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._-]*', n) and isinstance(v, str)
                and re.fullmatch(r'[a-f0-9]{64}', v) for n, v in manifest.items()), 'Invalid current home manifest')
    paths = set(D.I.CRITICAL) | set(D.T.OTHER_SOURCES.values()) | {OLD, H.LIVE + '/.htaccess'}
    names = set(HOME + CONSTRUCTION + D.P.EXTRA + D.P.FILES)
    names.update('harmat-home-family/' + n for n in manifest)
    names.update(('zz-harmat-internal-language.php', 'harmat-unified-offer-modal.php',
                  'zz-harmat-property-disclaimer.php', 'zz-harmat-public-offer-antispam.php',
                  'zz-harmat-public-offer-integrity.php'))
    names.update('harmat-internal-language/' + n for n in ('bootstrap.js', 'runtime.js', 'runtime.css', 'zh-Hans.json', 'patterns.js'))
    paths.update(DIR + '/' + n for n in names)
    return sorted(paths)


def snapshot(r):
    return {'native': D.snapshot(r), 'sources': {p: sha(r.read(p)) for p in protected_paths(r)}}


def metadata(r):
    r.directory(DIR)
    info = r.info(DIR + '/' + HOME[0])
    mode = stat.S_IMODE(info.st_mode)
    require(stat.S_ISREG(info.st_mode) and info.st_uid == 1608 and info.st_gid == 1611
            and mode & 0o400 and H.safe_public_mode(mode, info.st_uid, info.st_gid, r.private_group_verified),
            'Unsafe existing owned metadata template')
    return (0o644, info.st_uid, info.st_gid)


def owned(r, path, blob, meta, private=False, partial=False):
    parent = path.rsplit('/', 1)[0]
    r.directory(parent)
    if parent == DIR + '/' + ASSET:
        directory = r.info(parent)
        require((directory.st_uid, directory.st_gid, stat.S_IMODE(directory.st_mode)) == (meta[1], meta[2], 0o755),
                'Asset directory metadata changed')
    info = r.info(path)
    expected = (0o600 if private else meta[0], meta[1], meta[2])
    require(stat.S_ISREG(info.st_mode) and (stat.S_IMODE(info.st_mode), info.st_uid, info.st_gid) == expected,
            'Owned file metadata changed: ' + path)
    value = r.read(path, private=private)
    require((blob.startswith(value) if partial else value == blob), 'Foreign file bytes retained: ' + path)
    return value


class Resources(HTMLParser):
    def __init__(self, text):
        super().__init__(convert_charrefs=True)
        self.text = text
        self.offsets = [0] + [m.end() for m in re.finditer('\n', text)]
        self.items = []
        self.current = None
        self.feed(text)
        require(self.current is None, 'Incomplete presentation resource')

    def position(self):
        row, col = self.getpos()
        return self.offsets[row - 1] + col

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        marker = a.get('id', '')
        if marker.startswith('harmat-assistant-presentation-') or a.get('data-harmat-assistant-presentation') == '1':
            require(self.current is None and tag in ('style', 'script', 'link'), 'Only presentation resources permitted')
            start = self.position()
            if tag == 'link':
                self.items.append((tag, a, start, start + len(self.get_starttag_text()), ''))
            else:
                self.current = (tag, a, start, start + len(self.get_starttag_text()))

    def handle_endtag(self, tag):
        if self.current and self.current[0] == tag:
            kind, attrs, start, content = self.current
            end = self.text.find('>', self.position()) + 1
            self.items.append((kind, attrs, start, end, self.text[content:self.position()]))
            self.current = None


class NativeResources(HTMLParser):
    """Compare all other executable/style references; normalize only WP nonce literals."""
    def __init__(self, text):
        super().__init__(convert_charrefs=True)
        self.items = []
        self.current = None
        self.feed(text)
        require(self.current is None, 'Incomplete native resource')

    def handle_starttag(self, tag, attrs):
        if tag in ('script', 'style', 'link'):
            require(self.current is None, 'Nested native resource')
            self.items.append([tag, sorted(attrs), ''])
            if tag != 'link':
                self.current = self.items[-1]

    def handle_endtag(self, tag):
        if self.current and self.current[0] == tag:
            self.current[2] = re.sub(r'(["\'](?:nonce|_wpnonce|restNonce|offerNonce)["\']\s*:\s*["\'])[a-f0-9]{10}(["\'])',
                                     r'\1WP_NONCE\2', self.current[2])
            self.current = None

    def handle_data(self, data):
        if self.current is not None:
            self.current[2] += data


def original_widget(text):
    style = list(re.finditer(r'<style\b[^>]*\bid=["\']harmat-local-assistant-style["\'][^>]*>.*?</style>', text, re.S))
    script = list(re.finditer(r'<script\b[^>]*\bid=["\']harmat-local-assistant-script["\'][^>]*>.*?</script>', text, re.S))
    require(len(style) == len(script) == 1 and style[0].end() < script[0].start(), 'Original assistant STYLE/SCRIPT contract missing')
    chunk = text[style[0].start():script[0].end()]
    for marker in ('harmat-local-ai-launch', 'harmat-local-ai-panel', 'data-harmat-local-ai-body',
                   'data-harmat-local-ai-suggestions', 'data-harmat-local-ai-form', 'harmat-local-ai-input', 'harmat-local-ai-close'):
        require(marker in chunk, 'Original widget DOM contract missing: ' + marker)
    # HTML parsers map this SVG HTML spelling to the same case-sensitive DOM attribute.
    # The existing HTTP minifier can lowercase it; normalize this one exact tag only.
    chunk = chunk.replace('<svg viewbox="0 0 24 24" focusable="false">',
                          '<svg viewBox="0 0 24 24" focusable="false">')
    return chunk, script[0].end()


def assert_public(text, baseline, release, active):
    widget, marker = original_widget(text)
    require(widget == original_widget(baseline)[0], 'Original inline assistant/widget changed')
    page, prior = D.Page(text), D.Page(baseline)
    require(page.form == prior.form and page.seo == prior.seo and page.canonical == prior.canonical,
            'Native forms/SEO changed')
    resources = Resources(text).items
    require(len(resources) == (2 if active else 0), 'Presentation activation marker mismatch')
    expected_ids = Counter(prior.ids)
    if active:
        seen = set()
        for tag, attrs, start, end, body in resources:
            require(start >= marker, 'Presentation resource precedes original assistant activation marker')
            kind = 'css' if tag in ('style', 'link') else 'js'
            name = ASSET + '/assistant-presentation.' + kind
            require(kind not in seen, 'Duplicate presentation resource')
            allowed = {'id', 'data-version', 'data-chat-locale', 'data-harmat-assistant-presentation',
                       'rel', 'href', 'src', 'type', 'defer'}
            require(set(attrs) <= allowed, 'Unexpected presentation resource attributes')
            require(tag != 'link' or attrs.get('rel') == 'stylesheet', 'Presentation link must be a stylesheet')
            seen.add(kind)
            if 'id' in attrs:
                expected_ids[attrs['id']] += 1
            ref = attrs.get('href' if tag == 'link' else 'src')
            if ref:
                require(ref.split('?', 1)[0] == ORIGIN + '/wp-content/mu-plugins/' + name, 'Foreign presentation resource reference')
                require(not body.strip(), 'Unexpected external-script inline body')
            else:
                source = release['blobs'][name]
                emitted = source[:-1] if source.endswith(b'\n') else source
                require(tag in ('style', 'script') and body.encode() == emitted,
                        'Inline presentation hash mismatch')
        require(seen == {'css', 'js'}, 'Both presentation resources required')
    require(Counter(page.ids) == expected_ids, 'Native DOM ID contract changed')
    stripped = text
    for _, _, start, end, _ in sorted(resources, key=lambda item: item[2], reverse=True):
        stripped = stripped[:start] + stripped[end:]
    require(NativeResources(stripped).items == NativeResources(baseline).items, 'Native resources changed or unowned resource added')
    return {'original_inline_style_script_widget': True, 'native_form_seo_ids': True, 'active': active}


def baselines():
    result = {}
    for route in ROUTES:
        data, mime = D.get(ORIGIN + route)
        require('text/html' in mime, 'Native public baseline MIME mismatch')
        text = data.decode('utf-8')
        original_widget(text)
        require(not Resources(text).items, 'Presentation already active on baseline')
        result[route] = data
    return result


def public_proof(r, release, baseline, active=True):
    # Existing GET-only nine-route health/inventory and native homepage contracts.
    result = D.public_proof(r, (), {}, baseline['/'].decode('utf-8'))
    result['assistant_routes'] = {}
    for route in ROUTES:
        data, mime = D.get(ORIGIN + route)
        require('text/html' in mime, 'Public HTML MIME mismatch')
        try:
            result['assistant_routes'][route] = assert_public(data.decode('utf-8'), baseline[route].decode('utf-8'), release, active)
        except Exception as error:
            raise RuntimeError('Presentation proof failed on ' + route + ': ' + str(error)) from error
    if active:
        result['artifacts'] = {}
        for name in release['files'][:-1]:
            data, mime = D.get(ORIGIN + '/wp-content/mu-plugins/' + name)
            expected = 'text/css' if name.endswith('.css') else 'javascript' if name.endswith('.js') else 'text/plain'
            require(sha(data) == release['descriptor']['pins'][name] and expected in mime, 'Public artifact hash/MIME mismatch: ' + name)
            result['artifacts'][name] = {'sha256': sha(data), 'mime': mime}
    return result


def cleanup_stages(r, stages, meta):
    retained = []
    for path, blob in stages.items():
        try:
            if absent(r, path):
                continue
            mode = stat.S_IMODE(r.info(path).st_mode)
            require(mode in (0o600, 0o644), 'Stage mode drift')
            data = owned(r, path, blob, meta, private=mode == 0o600, partial=True)
            r.remove_owned(path, data)
        except Exception:
            retained.append(path)
    return retained


def native_presentation(r, backup, release, baseline):
    results = []
    for route in ('/', '/lakaskereso/', '/property/a1-1-l2/'):
        result = json.loads(r.run(H.PHP + ' ' + shlex.quote(backup + '/' + NATIVE)
                                 + ' ' + shlex.quote(backup + '/' + MAIN) + ' ' + shlex.quote(route)))
        require(result.get('route') == route and result.get('bytes', 0) > 10000, 'Native render contract failed')
        require(set(result.get('resources', {})) == {'style', 'script'}, 'Native resources missing')
        for kind, name in zip(('style', 'script'), REQUIRED):
            blob = release['blobs'][name]
            emitted = blob[:-1] if blob.endswith(b'\n') else blob
            item = result['resources'][kind]
            require(item.get('expected') == item.get('actual') == sha(emitted)
                    and item.get('expected_bytes') == item.get('actual_bytes') == len(emitted), 'Native rendered bytes changed')
        widget = r.read(backup + '/native-widget-' + sha(route.encode())[:12] + '-with.html', private=True).decode('utf-8')
        require(sha(widget.encode()) == result.get('native_widget_sha256'), 'Private widget proof drift')
        require(original_widget(widget)[0] == original_widget(baseline[route].decode('utf-8'))[0],
                'Native widget differs before activation: ' + route)
        results.append(result)
    return results


def rollback(r, release, meta, installed=None, remove_directory=True):
    files = release['files'] if installed is None else tuple(n for n in release['files'] if n in installed)
    controller = DIR + '/' + MAIN
    # A foreign controller may still depend on every asset: do not remove any.
    if not absent(r, controller):
        require(MAIN in files, 'Foreign controller retained with dependencies')
        owned(r, controller, release['blobs'][MAIN], meta)
        r.remove_owned(controller, release['blobs'][MAIN])
        require(absent(r, controller), 'Controller removal uncertain; retain assets')
    retained = []
    for name in reversed(files):
        if name == MAIN:
            continue
        path = DIR + '/' + name
        try:
            if not absent(r, path):
                owned(r, path, release['blobs'][name], meta)
                r.remove_owned(path, release['blobs'][name])
                require(absent(r, path), 'Asset removal uncertain')
        except Exception:
            retained.append(path)
    if remove_directory and not absent(r, DIR + '/' + ASSET):
        try:
            r.directory(DIR + '/' + ASSET)
            info = r.info(DIR + '/' + ASSET)
            require((info.st_uid, info.st_gid, stat.S_IMODE(info.st_mode)) == (meta[1], meta[2], 0o755), 'Asset directory ownership drift')
            r.sftp.rmdir(DIR + '/' + ASSET)
        except Exception:
            retained.append(DIR + '/' + ASSET)
    r.purge()
    return retained


def publish(r, name, release, meta, stages, installed):
    target = DIR + '/' + name
    r.directory(target.rsplit('/', 1)[0])
    require(absent(r, target), 'New target collision: ' + name)
    stage = target.rsplit('/', 1)[0] + '/.' + Path(name).name + '.' + uuid.uuid4().hex + '.tmp'
    require(absent(r, stage), 'Stage collision')
    blob = release['blobs'][name]
    stages[stage] = blob
    r.write_new(stage, blob)
    owned(r, stage, blob, meta, private=True)
    if name.endswith('.php'):
        r.lint(stage)
    r.stage_metadata(stage, meta)
    owned(r, stage, blob, meta)
    require(absent(r, target), 'Target appeared during staging')
    try:
        r.sftp.rename(stage, target)  # Standard SFTP: never overwrite a destination.
    except BaseException:
        if absent(r, stage) and not absent(r, target):
            installed.add(name)  # Successful rename with lost acknowledgement.
        raise
    installed.add(name)
    stages.pop(stage)
    owned(r, target, blob, meta)
    if name.endswith('.php'):
        r.lint(target)


def save_backup(r, backup, release, before, baseline, meta):
    r.mkdir(backup)
    r.mkdir(backup + '/' + ASSET)
    for name, blob in release['blobs'].items():
        r.write_new(backup + '/' + name, blob)
        if name.endswith('.php'):
            r.lint(backup + '/' + name)
    baseline_pins = {}
    for index, route in enumerate(ROUTES):
        name = 'public-baseline-' + str(index) + '.html'
        r.write_new(backup + '/' + name, baseline[route])
        baseline_pins[route] = sha(baseline[route])
    for path, digest in before['sources'].items():
        data = r.read(path)
        require(sha(data) == digest, 'Protected source changed during backup')
        r.write_new(backup + '/protected-' + sha(path.encode()) + '.bin', data)
    saved = {'schema': 1, 'release': release['descriptor'], 'aggregate': release['aggregate'],
             'before': before, 'metadata': list(meta), 'public_baselines': baseline_pins,
             'original_absent': list(release['files']) + [ASSET]}
    D.write_json(r, backup + '/manifest.json', saved)
    return saved


def load_backup(r, backup, release):
    r.directory(backup, private=True)
    r.directory(backup + '/' + ASSET, private=True)
    saved = json.loads(r.read(backup + '/manifest.json', private=True))
    require(saved.get('schema') == 1 and saved.get('release') == release['descriptor']
            and saved.get('aggregate') == release['aggregate'], 'Saved release pins differ')
    require(saved.get('original_absent') == list(release['files']) + [ASSET], 'Saved original absence missing')
    meta = saved.get('metadata')
    require(isinstance(meta, list) and meta == [0o644, 1608, 1611], 'Saved metadata invalid')
    for name, blob in release['blobs'].items():
        require(r.read(backup + '/' + name, private=True) == blob, 'Saved release bytes changed')
    baseline = {route: r.read(backup + '/public-baseline-' + str(index) + '.html', private=True)
                for index, route in enumerate(ROUTES)}
    require(saved.get('public_baselines') == {p: sha(b) for p, b in baseline.items()}, 'Saved public baseline drift')
    for data in baseline.values():
        original_widget(data.decode('utf-8'))
    for path, digest in saved['before']['sources'].items():
        require(sha(r.read(backup + '/protected-' + sha(path.encode()) + '.bin', private=True)) == digest,
                'Saved protected source drift')
    return saved, baseline, tuple(meta)


def operate(release, backup=None, reverse=False):
    if backup is not None:
        require(isinstance(backup, str) and re.fullmatch(re.escape(PREFIX) + r'[a-f0-9]{32}', backup), 'Exact private backup required')
    fresh = backup is None
    backup = backup or PREFIX + uuid.uuid4().hex
    r = H.Remote()
    locked = started = False
    stages, installed = {}, set()
    owner = uuid.uuid4().hex.encode('ascii')
    lock_owner = D.LOCK + '/owner'
    try:
        r.directory(DIR)
        r.directory(H.BACKUPS)
        r.mkdir(D.LOCK)
        r.write_new(lock_owner, owner)
        locked = True
        if fresh:
            require(absent(r, DIR + '/' + MAIN) and absent(r, DIR + '/' + ASSET), 'New release paths already exist')
            meta = metadata(r)
            before = snapshot(r)
            baseline = baselines()
            save_backup(r, backup, release, before, baseline, meta)
            saved, baseline, meta = load_backup(r, backup, release)
            filters = D.validate_native(r, backup)
            rendered = native_presentation(r, backup, release, baseline)
            require(snapshot(r) == before, 'Protected state drift before activation')
            D.write_json(r, backup + '/preflight-proof.json', {'filters': filters, 'rendered': rendered, 'aggregate': release['aggregate']})
            print(json.dumps({'status': 'PRIVATE_BACKUP_READY', 'backup': backup, 'aggregate': release['aggregate']}), flush=True)
            require(absent(r, DIR + '/' + MAIN) and absent(r, DIR + '/' + ASSET), 'New paths appeared before activation')
            r.sftp.mkdir(DIR + '/' + ASSET, 0o755)
            started = True
            r.sftp.chmod(DIR + '/' + ASSET, 0o755)
            for name in release['files']:
                if name == MAIN:
                    require(snapshot(r) == before, 'Protected state drift before controller')
                    for asset in release['files'][:-1]:
                        owned(r, DIR + '/' + asset, release['blobs'][asset], meta)
                publish(r, name, release, meta, stages, installed)
            r.purge()
        else:
            saved, baseline, meta = load_backup(r, backup, release)
            before = saved['before']
            if reverse:
                retained = rollback(r, release, meta)
                require(not retained, 'Foreign assets retained after controller deactivation: ' + ', '.join(retained))
            else:
                for name in release['files']:
                    owned(r, DIR + '/' + name, release['blobs'][name], meta)
                r.lint(DIR + '/' + MAIN)
        proof = public_proof(r, release, baseline, not reverse)
        filters = D.validate_native(r, backup)
        require(snapshot(r) == before, 'Protected state drift; never restore business data')
        if not reverse:
            for name in release['files']:
                owned(r, DIR + '/' + name, release['blobs'][name], meta)
        else:
            require(absent(r, DIR + '/' + MAIN), 'Controller reappeared after rollback')
        if fresh:
            D.write_json(r, backup + '/release-proof.json', {'public': proof, 'filters': filters})
        status = 'RELEASED' if fresh else 'ROLLED_BACK_SOURCE_ONLY' if reverse else 'VERIFIED_ASSISTANT_PRESENTATION'
        print(json.dumps({'status': status, 'backup': backup, 'aggregate': release['aggregate'], 'proof': proof, 'filters': filters}), flush=True)
    except BaseException:
        if started:
            retained = cleanup_stages(r, stages, meta)
            try:
                retained += rollback(r, release, meta, installed)
            except Exception:
                print(json.dumps({'status': 'RECOVERY_BLOCKED_FOREIGN_CONTROLLER', 'backup': backup, 'retained_stages': retained}), flush=True)
                raise
            print(json.dumps({'status': 'OWNED_RELEASE_DEACTIVATED', 'backup': backup, 'retained': retained}), flush=True)
        raise
    finally:
        try:
            if locked:
                r.directory(D.LOCK, private=True)
                require(r.read(lock_owner, private=True) == owner, 'Lock owner changed; retain lock')
                r.remove_owned(lock_owner, owner)
                r.sftp.rmdir(D.LOCK)
        finally:
            r.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    modes = parser.add_mutually_exclusive_group()
    modes.add_argument('--deploy', action='store_true')
    modes.add_argument('--verify')
    modes.add_argument('--rollback')
    parser.add_argument('--expect', help='Exact offline aggregate, required for every remote mode')
    args = parser.parse_args()
    release = local_release()
    if not (args.deploy or args.verify or args.rollback):
        print(json.dumps({'mode': 'OFFLINE_NO_WRITES', 'aggregate': release['aggregate'], 'release': release['descriptor']}, indent=2))
        return
    require(args.expect == release['aggregate'], 'Exact reviewed aggregate --expect required')
    operate(release, args.verify or args.rollback, bool(args.rollback))


if __name__ == '__main__':
    main()
