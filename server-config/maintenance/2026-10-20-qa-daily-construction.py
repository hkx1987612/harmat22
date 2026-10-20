"""Independent final-media QA; distinguish Metadata labels from Data streams."""
import importlib.util
import re
import struct
from pathlib import Path

spec = importlib.util.spec_from_file_location('daily_pinned_builder', Path(__file__).with_name('2026-10-20-build-daily-construction.py'))
B = importlib.util.module_from_spec(spec)
spec.loader.exec_module(B)


def metadata(info):
    assert not re.search(r'location|creation_time|recorder\.timestamp|com\.openharmony', info, re.I)
    assert not re.search(r'^\s*Stream #[^\n]*:\s*Data:', info, re.M | re.I)
    allowed = {'major_brand', 'minor_version', 'compatible_brands', 'encoder', 'handler_name', 'vendor_id'}
    for line in info.splitlines():
        if line.strip() == 'Metadata:':
            continue
        match = re.fullmatch(r'\s{4,}([\w.]+)\s*:\s*.*', line)
        if match:
            assert match[1] in allowed, ('Unknown metadata tag', match[1])


def main():
    plan = B.read(B.OUT/'plan.json')
    rendered = B.read(B.OUT/'render-manifest.json')
    assert plan['script_sha256'] == B.sha(B.__file__)
    assert rendered['plan_sha256'] == B.sha(B.OUT/'plan.json')
    # The original guard rejected the word Metadata itself, before any decoding.
    metadata('  Metadata:\n    encoder : Lavf\n  Stream #0:0: Video: h264\n')
    for bad in ('  Stream #0:2: Data: bin_data', '    location : private', '    creation_time : private'):
        try:
            metadata(bad)
        except AssertionError:
            pass
        else:
            raise AssertionError('Privacy guard accepted forbidden input')
    report = {'status': 'PASS_FINAL_LOCAL_TECHNICAL_QA_NOT_PUBLISHED', 'days': [],
              'files': rendered['files'], 'qa_script_sha256': B.sha(__file__),
              'original_qa_failure': 'Data: matched the non-sensitive Metadata: heading; outputs not altered'}
    for day in plan['days']:
        date = day['date']; base = date+'-compilation-hu-subtitled'; streams = []
        assert B.sha(B.OUT/day['ass']) == day['ass_sha256']
        assert B.sha(B.OUT/day['narration']) == day['audio_sha256']
        assert B.sha(B.OUT/day['srt']) == day['srt_sha256']
        for suffix in ('-silent.mp4', '.mp4'):
            file = B.OUT/(base+suffix); entry = rendered['files'][file.name]
            assert B.sha(file) == entry['sha256'] and file.stat().st_size == entry['bytes']
            info = B.run(['-i', file, '-t', '0', '-f', 'null', '-']).stderr.decode('utf-8', 'replace')
            original = info.split('Stream mapping:')[0]
            metadata(original)
            assert '1920x1080' in original and 'Video: h264' in original and '60 fps' in original
            assert original.count('Video:') == 1
            assert original.count('Audio:') == (1 if suffix == '.mp4' else 0)
            if suffix == '.mp4': assert '(hun): Audio: aac' in original and '48000 Hz, mono' in original
            decoded = B.run(['-v', 'error', '-xerror', '-threads', '4', '-i', file,
                '-map', '0:v:0', '-map', '0:a?', '-progress', 'pipe:1', '-nostats', '-f', 'null', '-'])
            values = dict(line.split('=', 1) for line in decoded.stdout.decode().splitlines() if '=' in line)
            assert values['progress'] == 'end' and not decoded.stderr.strip()
            assert int(values['frame']) == day['expected_frames'], (date, values['frame'], day['expected_frames'])
            streams.append(B.run(['-v', 'error', '-i', file, '-map', '0:v:0', '-c', 'copy',
                '-f', 'streamhash', '-hash', 'sha256', '-']).stdout)
            with file.open('rb') as f:
                kinds = []; offset = 0
                while offset < file.stat().st_size:
                    f.seek(offset); size, kind = struct.unpack('>I4s', f.read(8))
                    if size == 1: size = struct.unpack('>Q', f.read(8))[0]
                    assert 8 <= size <= file.stat().st_size-offset
                    kinds.append(kind); offset += size
                assert kinds.index(b'moov') < kinds.index(b'mdat')
        assert streams[0] == streams[1], 'Facebook and narrated video bitstreams differ'
        report['days'].append({'date': date, 'frames': day['expected_frames'], 'duration': day['duration'],
            'captions': len(day['cues']), 'no_original_audio': True, 'same_video_bitstream': True,
            'full_decode': True, 'metadata_stripped': True, 'faststart': True})
        print('QA_PASS '+date, flush=True)
    assert B.source()['source_pins'] == plan['source_pins']
    B.save(B.OUT/'qa.json', report)
    print(report['status'], flush=True)


if __name__ == '__main__': main()
