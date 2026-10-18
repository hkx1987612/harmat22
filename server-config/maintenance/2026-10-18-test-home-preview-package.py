"""Synthetic local packaging tests only; no network or production access."""
import base64
from html.parser import HTMLParser
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('home_preview_package', Path(__file__).with_name('2026-10-18-prepare-home-preview.py'))
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class Tags(HTMLParser):
    def __init__(self, text):
        super().__init__(convert_charrefs=True)
        self.tags = []
        self.feed(text)

    def handle_starttag(self, tag, attrs):
        self.tags.append((tag, dict(attrs)))


class PackagingTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.previous = M.OUT
        M.OUT = Path(self.temp.name)
        (M.OUT / 'assets').mkdir()
        (M.OUT / 'assets/test.png').write_bytes(b'\x89PNG\r\n\x1a\noriginal-image')
        (M.OUT / 'assets/hero-video.mp4').write_bytes(b'local-video')
        (M.OUT / 'assets/lucide.min.js').write_text('window.libraryReady=true;', encoding='utf-8')
        (M.OUT / 'home-family.js').write_text('window.pageReady=true;', encoding='utf-8')
        (M.OUT / 'home-family.css').write_text('body{background:white;letter-spacing:0}', encoding='utf-8')
        self.html = '<!doctype html><html lang="hu"><head><meta http-equiv="Content-Security-Policy" content="default-src none">' \
            '<link rel="stylesheet" href="home-family.css"><script defer src="assets/lucide.min.js"></script>' \
            '<script defer src="home-family.js"></script></head><body><main><h1>Harmat Lakópark</h1>' \
            '<img src="assets/test.png" width="10" height="10" alt="Image">' \
            '<video poster="assets/test.png" src="assets/hero-video.mp4"></video>' \
            '<a href="#contact">Contact</a><section id="contact">ertekesites@harmat22.hu</section></main></body></html>'

    def tearDown(self):
        M.OUT = self.previous
        self.temp.cleanup()

    def render(self):
        return ''.join(M.Bundle(self.html).parts)

    def test_original_images_embed_byte_exact(self):
        rows = Tags(self.render()).tags
        for tag, attrs in rows:
            if tag == 'img' or tag == 'video':
                value = attrs['src' if tag == 'img' else 'poster']
                self.assertEqual(base64.b64decode(value.split(',', 1)[1]), (M.OUT / 'assets/test.png').read_bytes())

    def test_style_and_scripts_are_inline_in_dependency_order_after_main(self):
        text = self.render()
        self.assertNotIn(' src="home-family.js"', text)
        self.assertNotIn(' src="assets/lucide.min.js"', text)
        self.assertNotIn('<link', text)
        self.assertLess(text.index('</main>'), text.index('window.libraryReady'))
        self.assertLess(text.index('window.libraryReady'), text.index('window.pageReady'))
        self.assertLess(text.index('window.pageReady'), text.index('</body>'))

    def test_local_video_remains_sibling_not_large_data_blob(self):
        text = self.render()
        self.assertIn('src="assets/hero-video.mp4"', text)
        self.assertNotIn('data:video', text)

    def test_remote_video_cannot_be_introduced_by_packaging(self):
        self.html = self.html.replace('src="assets/hero-video.mp4"', 'src="https://example.invalid/video.mp4"')
        with self.assertRaises(RuntimeError):
            self.render()

    def test_csp_forbids_connect_forms_and_frames(self):
        policy = next(attrs['content'] for tag, attrs in Tags(self.render()).tags if tag == 'meta')
        for directive in ("connect-src 'none'", "form-action 'none'", "frame-src 'none'", "object-src 'none'"):
            self.assertIn(directive, policy)

    def test_remote_image_never_downloaded_by_bundle(self):
        self.html = self.html.replace('src="assets/test.png"', 'src="https://example.invalid/private.png"')
        with self.assertRaises(RuntimeError):
            self.render()

    def test_path_escape_refused(self):
        with self.assertRaises(RuntimeError):
            M.Bundle.asset('../outside.txt')

    def test_css_external_dependency_fails_instead_of_missing_image(self):
        (M.OUT / 'home-family.css').write_text('body{background:url(assets/test.png)}', encoding='utf-8')
        with self.assertRaises(RuntimeError):
            self.render()

    def test_foreign_script_refused(self):
        self.html = self.html.replace('home-family.js', 'other.js')
        with self.assertRaises(RuntimeError):
            self.render()

    def test_original_panorama_cube_and_gallery_bytes_embed_locally(self):
        attrs = []
        for face in 'frblud':
            (M.OUT / ('assets/panorama-' + face + '.jpg')).write_bytes(('original-' + face).encode())
            attrs.append('data-cube-' + face + '="assets/panorama-' + face + '.jpg"')
        self.html = self.html.replace('<main>', '<main><div ' + ' '.join(attrs) + '></div><button data-full="assets/test.png">Gallery</button>')
        rows = Tags(self.render()).tags
        cube = next(a for tag, a in rows if tag == 'div')
        for face in 'frblud':
            self.assertEqual(base64.b64decode(cube['data-cube-' + face].split(',', 1)[1]), ('original-' + face).encode())
        gallery = next(a for tag, a in rows if tag == 'button')['data-full']
        self.assertEqual(base64.b64decode(gallery.split(',', 1)[1]), (M.OUT / 'assets/test.png').read_bytes())

    def test_exact_panorama_library_embeds_before_page_behavior(self):
        (M.OUT / 'assets/pannellum.js').write_text('window.panoramaLibrary=true;', encoding='utf-8')
        self.html = self.html.replace('<script defer src="home-family.js">', '<script defer src="assets/pannellum.js"></script><script defer src="home-family.js">')
        text = self.render()
        self.assertLess(text.index('window.panoramaLibrary'), text.index('window.pageReady'))

    def test_pinned_panorama_css_accepts_only_self_contained_image_urls(self):
        self.html = self.html.replace('<link rel="stylesheet" href="home-family.css">', '<link rel="stylesheet" href="assets/pannellum.css"><link rel="stylesheet" href="home-family.css">')
        css = M.OUT / 'assets/pannellum.css'
        css.write_text(".cursor{background:url('data:image/svg+xml,%3Csvg%2F%3E')}", encoding='utf-8')
        self.assertIn('data:image/svg+xml', self.render())
        for invalid in ("a{background:url(https://example.invalid/x.svg)}", 'a{background:url(img/cursor.svg)}', '@import "remote.css";'):
            css.write_text(invalid, encoding='utf-8')
            with self.assertRaises(RuntimeError):
                self.render()

    def test_owned_bundle_can_refresh_but_foreign_edit_never_overwritten(self):
        (M.OUT / 'index.html').write_text(self.html, encoding='utf-8')
        M.build_bundle()
        M.build_bundle()
        target = M.OUT / M.PREVIEW_NAME
        target.write_text('user edit', encoding='utf-8')
        with self.assertRaises(RuntimeError):
            M.build_bundle()
        self.assertEqual(target.read_text(), 'user edit')

    def test_no_submission_added_and_navigation_preserved(self):
        rows = Tags(self.render()).tags
        self.assertFalse(any(tag in ('form', 'input', 'iframe') for tag, attrs in rows))
        self.assertEqual(next(attrs['href'] for tag, attrs in rows if tag == 'a'), '#contact')

    def test_v3_revision_export_preserves_previously_reviewed_versions(self):
        previous = [M.OUT / ('harmat-home-family-preview-v' + version + '.html') for version in ('1', '2', '3')]
        for item in previous:
            item.write_text('previous reviewed preview', encoding='utf-8')
        (M.OUT / 'index.html').write_text(self.html, encoding='utf-8')
        M.build_bundle()
        for item in previous:
            self.assertEqual(item.read_text(encoding='utf-8'), 'previous reviewed preview')
        self.assertTrue((M.OUT / 'harmat-home-family-preview-v3-r1.html').is_file())


if __name__ == '__main__':
    unittest.main()
