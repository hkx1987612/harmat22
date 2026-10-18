"""Prepare approved construction media locally; never upload or alter originals."""
import hashlib
import json
from pathlib import Path
import re
import struct
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'outputs/2026-10-18-construction-web/media-v1'
FF = Path('C:/Users/Administrator/AppData/Roaming/Python/Python312/site-packages/imageio_ffmpeg/binaries/ffmpeg-win-x86_64-v7.1.exe')
PINS = [
    '951c5d059460e856fe08f2c87438f027f059a03987c7f3746383f0aab0062233',
    'edd260132f920058595c5937e32abfb4669f8b9d90f3d97aafd89114627edcc8',
    '044bf06813d46fccc2d9bf48a1fcfaecdf99c1189f134dbdb0a3ec31ae52ae32',
    '142b038bd0571d70c54b30ac6266a7673f46903b8832a166273a6ce9ba8672cc',
    '9d6c849e8f7d5dc6776921c991bf588e42172c4f3b93454e3a902a4e7333ab30',
]


def sha(path):
    h = hashlib.sha256()
    with path.open('rb') as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()


def run(args):
    result = subprocess.run([str(FF), '-hide_banner', '-nostdin', *args], capture_output=True)
    if result.returncode:
        raise RuntimeError(result.stderr.decode('utf-8', 'replace'))
    return result


def main():
    sources = [(ROOT / 'outputs/2026-10-12-latest-videos/prepared-v1' / f'latest-site-{i:02d}.mp4',
                f'latest-site-{i:02d}') for i in range(1, 5)]
    sources.append((ROOT / 'outputs/2026-10-14-site-videos/subtitled-v1/render/harmat-2026-10-13-compilation-hu-subtitled.mp4',
                    '2026-10-13-compilation-hu-subtitled'))
    assert [sha(p) for p, _ in sources] == PINS, 'Approved source pin mismatch'
    OUT.mkdir(parents=True, exist_ok=False)
    manifest = {'scope': 'LOCAL_ONLY_APPROVED_WEB_DERIVATIVES', 'files': {}, 'sources': []}
    for index, (source, name) in enumerate(sources):
        target = OUT / (name + '.mp4')
        codec = ['-c', 'copy'] if index < 4 else [
            '-c:v', 'libx264', '-preset', 'medium', '-crf', '22', '-pix_fmt', 'yuv420p', '-threads', '4', '-c:a', 'copy']
        run(['-v', 'error', '-n', '-i', str(source), '-map', '0:v:0', '-map', '0:a?', *codec,
             '-map_metadata', '-1', '-map_metadata:s', '-1', '-map_chapters', '-1', '-movflags', '+faststart', str(target)])
        run(['-v', 'error', '-xerror', '-i', str(target), '-map', '0:v:0', '-map', '0:a?', '-f', 'null', '-'])
        info = run(['-i', str(target), '-t', '0', '-f', 'null', '-']).stderr.decode('utf-8', 'replace')
        assert '1280x720' in info and not re.search(r'location|creation_time|com\.apple', info, re.I)
        poster = OUT / (name + '.jpg')
        run(['-v', 'error', '-n', '-ss', '8', '-i', str(target), '-frames:v', '1', '-q:v', '3', str(poster)])
        for p in (target, poster):
            manifest['files'][p.name] = {'sha256': sha(p), 'bytes': p.stat().st_size}
        manifest['sources'].append({'name': name, 'sha256': PINS[index], 'source_bytes': source.stat().st_size,
                                    'web_bytes': target.stat().st_size, 'decode': 'PASS', 'dimensions': '1280x720',
                                    'transform': 'Remux only' if index < 4 else 'Native 720p CRF22 H264; AAC copied'})
        print(json.dumps(manifest['sources'][-1]), flush=True)
    assert [sha(p) for p, _ in sources] == PINS
    (OUT / 'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
    print('LOCAL_MEDIA_READY', flush=True)


def verify():
    manifest = json.loads((OUT / 'manifest.json').read_text())
    for name, row in manifest['files'].items():
        assert re.fullmatch(r'(?:latest-site-0[1-4]|2026-10-13-compilation-hu-subtitled)\.(?:mp4|jpg)', name)
        path = OUT / name
        assert sha(path) == row['sha256'] and path.stat().st_size == row['bytes']
        if name.endswith('.mp4'):
            atoms = []
            with path.open('rb') as f:
                offset = 0
                while offset < row['bytes']:
                    f.seek(offset)
                    size, kind = struct.unpack('>I4s', f.read(8))
                    if size == 1:
                        size = struct.unpack('>Q', f.read(8))[0]
                    assert size >= 8 and offset + size <= row['bytes']
                    atoms.append(kind)
                    offset += size
            assert atoms.index(b'moov') < atoms.index(b'mdat')
    paths = [ROOT / 'outputs/2026-10-14-site-videos/subtitled-v1/render/harmat-2026-10-13-compilation-hu-subtitled.mp4',
             OUT / '2026-10-13-compilation-hu-subtitled.mp4']
    audio = [run(['-v', 'error', '-i', str(p), '-map', '0:a:0', '-c', 'copy',
                  '-f', 'streamhash', '-hash', 'sha256', '-']).stdout for p in paths]
    assert audio[0] == audio[1]
    print(json.dumps({'status': 'VERIFIED_LOCAL_MEDIA', 'files': len(manifest['files']),
                      'all_hashes_sizes': True, 'all_faststart': True, 'approved_narration_copied': True}))


if __name__ == '__main__':
    if sys.argv[1:] == ['--verify']:
        verify()
    else:
        assert not sys.argv[1:]
        main()
