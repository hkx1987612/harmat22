"""Local-only lossless preparation; no publication or audio privacy clearance."""
import hashlib
import json
import re
import subprocess
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / 'outputs/2026-10-12-latest-videos'
OUT = BASE / 'prepared-v1'
FF = Path('C:/Users/Administrator/AppData/Roaming/Python/Python312/site-packages/imageio_ffmpeg/binaries/ffmpeg-win-x86_64-v7.1.exe')

def digest(p):
    h = hashlib.sha256()
    with p.open('rb') as f:
        for b in iter(lambda: f.read(1048576), b''):
            h.update(b)
    return h.hexdigest()

def run(args):
    p = subprocess.run([str(FF), '-hide_banner', '-nostdin', *args], capture_output=True)
    if p.returncode:
        raise RuntimeError(p.stderr.decode('utf-8', 'replace'))
    return p

def streamhash(p):
    return run(['-v', 'error', '-i', str(p), '-map', '0:v:0', '-map', '0:a?', '-c', 'copy', '-f', 'streamhash', '-hash', 'sha256', '-']).stdout.decode()

def main():
    sources = sorted((BASE / 'source').rglob('*.mp4'))
    assert len(sources) == 4
    pins = {str(p): digest(p) for p in sources}
    OUT.mkdir(exist_ok=False)
    prior = {}
    sizes = {p.stat().st_size for p in sources}
    for p in (ROOT / 'outputs').rglob('*.mp4'):
        if BASE not in p.parents and p.stat().st_size in sizes:
            prior.setdefault(digest(p), []).append(str(p))
    report = {'status': 'LOCAL_PREPARED_NOT_PUBLISHED', 'capture_date': 'UNCONFIRMED',
              'audio_privacy': 'Original audio retained. Human listening approval required before publication.',
              'duplicate_scope': 'Exact SHA256 against all prior outputs MP4s with matching byte size; not perceptual deduplication.', 'videos': []}
    sheets = []
    font = ImageFont.truetype('C:/Windows/Fonts/arial.ttf', 20)
    for n, src in enumerate(sources, 1):
        name = f'latest-site-{n:02d}'
        probe = run(['-i', str(src), '-t', '0', '-f', 'null', '-']).stderr.decode('utf-8', 'replace')
        (OUT / (name + '-source-info.txt')).write_text(probe, encoding='utf-8')
        m = re.search(r'Duration: (\d+):(\d+):(\d+\.\d+)', probe)
        duration = int(m[1])*3600 + int(m[2])*60 + float(m[3])
        target = OUT / (name + '.mp4')
        run(['-v', 'error', '-n', '-i', str(src), '-map', '0:v:0', '-map', '0:a?', '-c', 'copy', '-map_metadata', '-1', '-map_metadata:s', '-1', '-map_chapters', '-1', '-movflags', '+faststart', str(target)])
        checks = {}
        for label, p in [('source', src), ('prepared', target)]:
            result = run(['-v', 'error', '-xerror', '-err_detect', 'explode', '-i', str(p), '-map', '0:v:0', '-map', '0:a?', '-fps_mode', 'passthrough', '-enc_time_base', 'demux', '-f', 'null', '-'])
            assert not result.stderr, result.stderr
            checks[label + '_full_decode'] = 'PASS'
        a, b = streamhash(src), streamhash(target)
        assert a == b, (a, b)
        cleaninfo = run(['-i', str(target), '-t', '0', '-f', 'null', '-']).stderr.decode('utf-8', 'replace')
        (OUT / (name + '-prepared-info.txt')).write_text(cleaninfo, encoding='utf-8')
        assert not re.search(r'location|creation_time|com\.apple|make\s*:|model\s*:', cleaninfo, re.I)
        sheet = Image.new('RGB', (1280, 820), '#ffffff')
        draw = ImageDraw.Draw(sheet)
        draw.text((12, 8), name + ' | capture date unconfirmed | original audio retained', fill='black', font=font)
        times = [duration * x for x in (.02, .15, .3, .45, .6, .75, .9, .98)]
        for k, t in enumerate(times):
            frame = OUT / f'{name}-frame-{k+1:02d}.png'
            run(['-v', 'error', '-n', '-ss', f'{t:.6f}', '-i', str(src), '-frames:v', '1', str(frame)])
            with Image.open(frame) as im:
                im.thumbnail((310, 350))
                x, y = (k % 4)*320, 45+(k//4)*385
                sheet.paste(im, (x+(320-im.width)//2, y))
                draw.text((x+8, y+355), f'{t:.2f} s', fill='black', font=font)
        sheetpath = OUT / (name + '-contact-sheet.jpg')
        sheet.save(sheetpath, quality=92)
        sheets.append(sheetpath)
        report['videos'].append({'name': name, 'source': str(src), 'source_bytes': src.stat().st_size,
            'source_sha256': pins[str(src)], 'duration_seconds_rounded_container': duration,
            'source_stream_lines': [l.strip() for l in probe.splitlines() if 'Stream #' in l],
            'prepared': str(target), 'prepared_sha256': digest(target), 'contact_sheet': str(sheetpath),
            'exact_prior_duplicates': prior.get(pins[str(src)], []), 'compressed_stream_sha256': a.strip(),
            'checks': checks, 'metadata_check': 'No source GPS/device/creation tags exposed by FFmpeg; technical muxer tags may remain.'})
        print(name, duration, flush=True)
    assert pins == {str(p): digest(p) for p in sources}
    report['sources_unchanged'] = True
    overview = Image.new('RGB', (1280, 3280), 'white')
    for k, p in enumerate(sheets):
        with Image.open(p) as im:
            overview.paste(im, (0, k*820))
    overview.save(OUT / 'all-four-contact-sheet.jpg', quality=92)
    (OUT / 'qa-manifest.json').write_text(json.dumps(report, ensure_ascii=True, indent=2), encoding='utf-8')
    print(json.dumps(report, ensure_ascii=True, indent=2))

if __name__ == '__main__':
    main()
