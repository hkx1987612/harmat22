"""Exact controller-only hero fill update; default offline, native forms untouched."""
import importlib.util
from pathlib import Path
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]


def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, HERE / filename)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


U = load('hero_fill_transport', '2026-10-18-update-home-header-flow.py')
A = load('hero_fill_markup', '2026-10-18-audit-home-loading.py')
C = A.owner_helpers()
BASE = 'd17dd30'
OLD_SHA = 'ac813d2b6057fa23e4cf03ace499d7eb81c76b2faf372b950cf77b3faec6d2b3'
NEW_SHA = '03e7fa36f1b3f23649f99240123b5a6eeefe6ee3af68edfec6f97a8f588b89c7'
PREFIX = U.H.BACKUPS + '/home-hero-fill-'
START = b"            . 'body.home.page-id-164 #harmat-home-family .hhf-hero{"
END = b"            . '</style>';"
PROTECTED = ('zz-harmat-construction-updates.php', 'zz-harmat-construction-progress-video.php')
STYLE = ''
original_state = U.state
original_public = U.public


def hero_block(source):
    U.require(source.count(START) == 1 and source.count(END) == 1, 'Exact final hero CSS boundaries required')
    start, end = source.index(START), source.index(END)
    U.require(start < end, 'Invalid hero CSS order')
    return source[start:end]


def inline_style(source):
    start = source.index(b"        $resources='<style ")
    end = source.index(END, start) + len(END)
    lines = source[start:end].decode('ascii').splitlines()
    values = []
    for index, line in enumerate(lines):
        prefix = "        $resources='" if index == 0 else "            . '"
        suffix = "';" if index == len(lines) - 1 else "'"
        U.require(line.startswith(prefix) and line.endswith(suffix), 'Static CSS literal required')
        value = line[len(prefix):-len(suffix)]
        U.require("'" not in value and '\\' not in value, 'Escaped/dynamic CSS literal refused')
        values.append(value)
    return ''.join(values)


def validate_candidate(original, candidate):
    U.require(U.sha(original) == OLD_SHA and b'\r' not in original, 'Pinned v1.0.8 LF controller required')
    U.require(U.sha(candidate) == NEW_SHA and b'\r' not in candidate, 'Reviewed v1.0.9 LF controller required')
    old, new = hero_block(original), hero_block(candidate)
    restored = candidate.replace(new, old, 1).replace(b' * Version: 1.0.9', b' * Version: 1.0.8', 1)
    U.require(restored == original, 'Only final hero CSS and version may change')
    U.require(len(new) < 10000 and b'max-width:none;margin:0;scroll-margin-top:76px' in new
              and b'object-fit:contain' not in new, 'Bounded full-width baseline-cover rules required')
    for forbidden in (b'#my-sticky-header', b'#masthead', b'h22-offer', b'<script', b'url(', b'@import'):
        U.require(forbidden not in new, 'Non-hero behavior in fill rules refused')
    return original, candidate


def local_release():
    original = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + U.D.MAIN], cwd=ROOT)
    candidate = (ROOT / 'wp-mu-plugins' / U.D.MAIN).read_bytes().replace(b'\r\n', b'\n')
    validate_candidate(original, candidate)
    for name in PROTECTED:
        expected = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + name], cwd=ROOT)
        U.require((ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n') == expected,
                  'Protected construction source changed: ' + name)
    files, blobs, hashes, _ = U.D.bundle()
    return original, candidate, files, hashes, blobs[U.D.CHECKER]


def state(remote, files, hashes):
    value = original_state(remote, files, hashes)
    value['construction_sources'] = {name: U.sha(remote.read(U.D.DIR + '/' + name)) for name in PROTECTED}
    return value


def policy(text, journal=False):
    fragment = C.owned(text, 'id', 'harmat-construction-updates') if journal else C.owned(text, 'class', 'hhf-journal')
    nodes = A.Markup(fragment).nodes
    U.require(sum('data-hcu-player' in a for _, a in nodes) == (5 if journal else 1), 'Construction count changed')
    U.require(not any(t in ('video', 'source', 'iframe', 'form') for t, _ in nodes), 'Construction eager media/form regression')
    U.require('2026-10-13-compilation-hu-subtitled.mp4' in fragment, 'Latest compilation missing')
    if not journal:
        U.require('/epitesi-naplo/#construction-2026-10-13' in fragment, 'Latest journal link changed')


def public(remote, files, hashes, baseline, updated):
    proof = original_public(remote, files, hashes, baseline, updated)
    home, mime = U.D.get(U.D.URL)
    U.require('text/html' in mime, 'Home MIME mismatch')
    policy(home.decode('utf-8'))
    journal, mime = U.D.get(C.JOURNAL)
    U.require('text/html' in mime, 'Journal MIME mismatch')
    policy(journal.decode('utf-8'), journal=True)
    proof['construction_policy'] = {'home': 1, 'journal': 5, 'click_only': True}
    return proof


def configure():
    global STYLE
    original, candidate, _, _, _ = local_release()
    STYLE = inline_style(candidate)
    U.require(STYLE != inline_style(original), 'Fill style unchanged')
    U.PREFIX, U.STYLE, U.local_release = PREFIX, STYLE, local_release
    U.state, U.public, U.__doc__ = state, public, __doc__
    return U


if __name__ == '__main__':
    configure().main()
