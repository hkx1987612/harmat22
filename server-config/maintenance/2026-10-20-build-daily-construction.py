"""Approved local daily construction editions; no cloud, upload, or business writes."""
import argparse
import array
import hashlib
import json
import re
import struct
import subprocess
import sys
import wave
from pathlib import Path

from PIL import ImageFont

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / 'outputs/2026-10-20-site-media'
OUT = BASE / 'final-v1'
FF = Path('C:/Users/Administrator/AppData/Roaming/Python/Python312/site-packages/imageio_ffmpeg/binaries/ffmpeg-win-x86_64-v7.1.exe')
MODEL = ROOT / 'outputs/2026-10-14-site-videos/local-tts-v1/model'
PINS = {'hu_HU-imre-medium.onnx': 'af7d98e2031b4f00cf3693cafc47b0b5347f23c28cd6a5957a693f76d7202c2d',
        'hu_HU-imre-medium.onnx.json': 'bb9c31dd8429b1414d486e5d52d52f0790949c63bfaf1345075d42e23ad10c83',
        'MODEL_CARD': '7816348278ffe352e0b41183705b163810c5439f9d4e361cbc7ee87aa919d612'}
FONT = ImageFont.truetype('C:/Windows/Fonts/arial.ttf', 48)
PHOTOS = {
    '2026-10-19': ['Fal- és pillérvasalás, zsaluzás.', 'A földszint feletti födém zsaluzása.',
                  'A pince feletti födém zsaluzása és vasalása.', 'A pincefödém vasalása a befejezéshez közelít.'],
    '2026-10-20': ['Földszinti fal- és pillérvasalás.', 'A földszint feletti födém zsaluzása.',
                  'Födémvasalási munkák.', 'A födém betonozása.'],
}
NUMBERS = ['egyes', 'kettes', 'hármas', 'négyes']


def sha(path):
    with Path(path).open('rb') as f:
        return hashlib.file_digest(f, 'sha256').hexdigest()


def read(path):
    return json.loads(Path(path).read_text(encoding='utf-8'))


def save(path, data):
    with Path(path).open('x', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)


def run(args):
    p = subprocess.run([str(FF), '-hide_banner', '-nostdin', *map(str, args)], capture_output=True)
    if p.returncode:
        raise RuntimeError(p.stderr.decode('utf-8', 'replace')[-6000:])
    return p


def source():
    manifest = read(BASE / 'prepared-v1/manifest.json')
    assert read(BASE / 'prepared-v1/technical-qa.json')['status'].startswith('PASS_LOCAL_SILENT')
    assert len(manifest['source_pins']) == 13
    assert {n: sha(BASE / 'source' / n) for n in manifest['source_pins']} == manifest['source_pins']
    return manifest


def wrap(text):
    if FONT.getlength(text) <= 1700:
        return text
    words = text.split()
    pairs = [(' '.join(words[:i]), ' '.join(words[i:])) for i in range(1, len(words))]
    pairs = [p for p in pairs if all(FONT.getlength(s) <= 1700 for s in p)]
    assert pairs, 'Caption must fit two safe lines'
    return '\n'.join(min(pairs, key=lambda p: abs(FONT.getlength(p[0])-FONT.getlength(p[1]))))


def stamp(seconds, ass=False):
    unit = 100 if ass else 1000
    h, rem = divmod(round(seconds*unit), 3600*unit)
    m, rem = divmod(rem, 60*unit)
    s, rem = divmod(rem, unit)
    return f'{h}:{m:02}:{s:02}.{rem:02}' if ass else f'{h:02}:{m:02}:{s:02},{rem:03}'


def audio():
    from piper import PiperVoice, SynthesisConfig
    original = source()
    assert {n: sha(MODEL/n) for n in PINS} == PINS
    OUT.mkdir(exist_ok=False)
    voice = PiperVoice.load(str(MODEL/'hu_HU-imre-medium.onnx'), use_cuda=False)
    config = SynthesisConfig(length_scale=1.0, normalize_audio=True, volume=.7)
    result = {'state': 'LOCAL_APPROVED_EDIT_NOT_PUBLISHED', 'source_pins': original['source_pins'],
              'script_sha256': sha(__file__), 'model_pins': PINS, 'days': [], 'spend': 0}
    for day in original['days']:
        date, total = day['date'], day['duration']
        photo_start = total-32
        # Each sentence is synthesized independently so exact waveform duration sets its caption cue.
        entries = [(0.5, 12, day['narration'][0]['text'], day['title'])]
        text = day['narration'][1]['text']
        sentences = [s.strip()+'.' for s in text.rstrip('.').split('. ')]
        entries += [(13 if i == 0 else None, photo_start-1, s, s) for i,s in enumerate(sentences)]
        offset = 0
        for i, name in enumerate(day['sources']):
            frames = day['part_frames'][f'{date}-site-{i+1:02}.mp4']
            if i:
                text = ('A helyszíni körkép a födémek és a környező vasbeton szerkezetek állapotát mutatja.' if i == 1 else
                        'A munkaterület különböző pontjain vasalási és zsaluzási részletek láthatók.')
                entries.append((offset+2, offset+frames/60-1, text, text))
            offset += frames/60
        for i, text in enumerate(PHOTOS[date]):
            speech = f'Az A {NUMBERS[i]} épület. {text}'
            entries.append((photo_start+i*8+.2, photo_start+(i+1)*8-.2, speech, f'A{i+1} · {text}'))
        timeline = array.array('h', [0])*round(total*48000)
        cues, end = [], 0.0
        for i,(start, limit, speech, caption) in enumerate(entries, 1):
            start = end+.25 if start is None else start
            raw, normalized = OUT/f'{date}-{i:02}-raw.wav', OUT/f'{date}-{i:02}.wav'
            with wave.open(str(raw), 'wb') as f:
                voice.synthesize_wav(speech, f, syn_config=config)
            run(['-v','error','-n','-i',raw,'-af','loudnorm=I=-18:TP=-2:LRA=7,aresample=48000',
                 '-ac','1','-c:a','pcm_s16le',normalized])
            with wave.open(str(normalized),'rb') as f:
                assert (f.getframerate(),f.getnchannels(),f.getsampwidth()) == (48000,1,2)
                samples = array.array('h', f.readframes(f.getnframes()))
            if sys.byteorder != 'little': samples.byteswap()
            seconds = len(samples)/48000
            assert start >= end and start+seconds < limit, (date, i, seconds, limit-start)
            assert samples and 0 < max(abs(x) for x in samples) < 32767
            pos = round(start*48000)
            assert pos+len(samples) <= len(timeline)
            timeline[pos:pos+len(samples)] = samples
            end = start+seconds
            display_end = limit if i > len(entries)-4 else end+.15
            cues.append({'start':start,'end':display_end,'audio_end':end,'text':caption,'speech':speech,
                         'wave':normalized.name,'sha256':sha(normalized)})
            print(f'VOICE {date} {i}/{len(entries)} {seconds:.3f}s',flush=True)
        audio_path = OUT/f'{date}-narration.wav'
        if sys.byteorder != 'little': timeline.byteswap()
        with wave.open(str(audio_path),'wb') as f:
            f.setnchannels(1);f.setsampwidth(2);f.setframerate(48000);f.writeframes(timeline.tobytes())
        assert all(cues[i]['end'] <= cues[i+1]['start'] for i in range(len(cues)-1))
        srt = OUT/f'{date}.hu.srt'
        srt.write_text('\n\n'.join(f'{i}\n{stamp(c["start"])} --> {stamp(c["end"])}\n{wrap(c["text"])}'
                         for i,c in enumerate(cues,1))+'\n',encoding='utf-8')
        ass = OUT/f'{date}.hu.ass'
        header = ('[Script Info]\nScriptType: v4.00+\nPlayResX: 1920\nPlayResY: 1080\nWrapStyle: 2\n\n[V4+ Styles]\n'
          'Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\n'
          'Style: Default,Arial,48,&H00FFFFFF,&H000000FF,&H00111F19,&H90000000,0,0,0,0,100,100,0,0,3,2,0,2,90,90,65,1\n'
          'Style: Brand,Arial,30,&H00FFFFFF,&H000000FF,&H00111F19,&H90000000,0,0,0,0,100,100,0,0,3,2,0,7,50,50,35,1\n'
          '\n[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n')
        events = ''.join(f'Dialogue: 0,{stamp(c["start"],True)},{stamp(c["end"],True)},Default,,0,0,0,,{wrap(c["text"]).replace(chr(10), r"\N")}\n' for c in cues)
        events += f'Dialogue: 1,0:00:00.00,{stamp(total,True)},Brand,,0,0,0,,Harmat Lakópark | {date}\n'
        events += f'Dialogue: 1,{stamp(total-10,True)},{stamp(total,True)},Brand,,0,0,0,,{{\\an9}}harmat22.hu\n'
        ass.write_text(header+events,encoding='utf-8')
        result['days'].append({**day,'cues':cues,'narration':audio_path.name,'audio_sha256':sha(audio_path),
                              'ass':ass.name,'ass_sha256':sha(ass),'srt':srt.name,'srt_sha256':sha(srt)})
    assert {n: sha(MODEL/n) for n in PINS} == PINS
    save(OUT/'plan.json',result)
    print('AUDIO_AND_CAPTION_PLAN_READY',flush=True)


def render():
    plan=read(OUT/'plan.json'); original=source()
    assert plan['source_pins'] == original['source_pins'] and plan['script_sha256'] == sha(__file__)
    files={}
    for day in plan['days']:
        date=day['date']; basename=f'{date}-compilation-hu-subtitled'
        assert sha(OUT/day['ass']) == day['ass_sha256'] and sha(OUT/day['narration']) == day['audio_sha256']
        args=['-v','error','-n','-filter_complex_threads','1']
        filters=[]
        for i,name in enumerate(day['sources']):
            args += ['-threads','2','-i',BASE/'source'/name]
            filters.append(f'[{i}:v:0]fps=60,setsar=1,settb=1/60,setpts=N[v{i}]')
        count=len(day['sources'])
        for j,(building,_) in enumerate(day['photos']):
            i=count+j
            args += ['-loop','1','-framerate','60','-t','8','-i',BASE/'source'/f'{date.replace("-", "")}-{building.lower()}.jpg']
            filters.append(f'[{i}:v:0]scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2:color=0x172b23,setsar=1,settb=1/60,setpts=N[v{i}]')
        all_inputs=''.join(f'[v{i}]' for i in range(count+4))
        ass=(OUT/day['ass']).as_posix().replace(':',r'\:')
        filters.append(f"{all_inputs}concat=n={count+4}:v=1:a=0,ass='{ass}'[out]")
        silent=OUT/(basename+'-silent.mp4')
        run(args+['-filter_complex',';'.join(filters),'-map','[out]','-an','-c:v','libx264',
             '-preset','fast','-crf','16','-pix_fmt','yuv420p','-threads','4','-r','60','-fps_mode','cfr',
             '-map_metadata','-1','-map_metadata:s','-1','-map_chapters','-1','-movflags','+faststart',silent])
        narrated=OUT/(basename+'.mp4')
        run(['-v','error','-n','-i',silent,'-i',OUT/day['narration'],'-map','0:v:0','-map','1:a:0',
             '-c:v','copy','-c:a','aac','-b:a','192k','-metadata:s:a:0','language=hun',
             '-map_metadata','-1','-map_chapters','-1','-movflags','+faststart',narrated])
        poster=OUT/(basename+'.jpg')
        run(['-v','error','-n','-ss','8','-i',narrated,'-frames:v','1','-q:v','2',poster])
        for path in (silent,narrated,poster):
            files[path.name]={'sha256':sha(path),'bytes':path.stat().st_size}
        print('FINAL_RENDER_READY '+date,flush=True)
    assert plan['source_pins']==source()['source_pins']
    save(OUT/'render-manifest.json',{'state':'RENDERED_AWAITING_QA','files':files,'plan_sha256':sha(OUT/'plan.json')})


def qa():
    plan,rendered=read(OUT/'plan.json'),read(OUT/'render-manifest.json')
    assert rendered['plan_sha256']==sha(OUT/'plan.json')
    report={'status':'PASS_FINAL_LOCAL_TECHNICAL_QA_NOT_PUBLISHED','days':[],'files':rendered['files']}
    for day in plan['days']:
        date=day['date']; base=f'{date}-compilation-hu-subtitled'
        streams=[]
        for suffix in ('-silent.mp4','.mp4'):
            path=OUT/(base+suffix);entry=rendered['files'][path.name]
            assert sha(path)==entry['sha256'] and path.stat().st_size==entry['bytes']
            info=run(['-i',path,'-t','0','-f','null','-']).stderr.decode('utf-8','replace')
            assert '1920x1080' in info and 'Video: h264' in info and '60 fps' in info
            assert ('Audio:' in info)==(suffix=='.mp4')
            assert not re.search(r'location|creation_time|recorder.timestamp|com.openharmony|Data:',info,re.I)
            decoded=run(['-v','error','-xerror','-threads','4','-i',path,'-map','0:v:0','-map','0:a?',
                         '-progress','pipe:1','-nostats','-f','null','-'])
            values=dict(line.split('=',1) for line in decoded.stdout.decode().splitlines() if '=' in line)
            assert values['progress']=='end' and not decoded.stderr.strip()
            assert int(values['frame'])==day['expected_frames'], (date,values['frame'],day['expected_frames'])
            stream=run(['-v','error','-i',path,'-map','0:v:0','-c','copy','-f','streamhash','-hash','sha256','-']).stdout
            streams.append(stream)
            with path.open('rb') as f:
                kinds=[];offset=0
                while offset<path.stat().st_size:
                    f.seek(offset);size,kind=struct.unpack('>I4s',f.read(8))
                    if size==1:size=struct.unpack('>Q',f.read(8))[0]
                    assert 8<=size<=path.stat().st_size-offset
                    kinds.append(kind);offset+=size
                assert kinds.index(b'moov')<kinds.index(b'mdat')
        assert streams[0]==streams[1], 'Narrated and Facebook video bitstreams differ'
        report['days'].append({'date':date,'frames':day['expected_frames'],'duration':day['duration'],
             'captions':len(day['cues']),'no_original_audio':True,'same_video_bitstream':True,
             'full_decode':True,'metadata_stripped':True,'faststart':True})
        print('QA_PASS '+date,flush=True)
    assert source()['source_pins']==plan['source_pins']
    save(OUT/'qa.json',report)
    print(report['status'],flush=True)


if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('mode',choices=['audio','render','qa'])
    {'audio':audio,'render':render,'qa':qa}[parser.parse_args().mode]()
