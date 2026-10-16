"""Explicit, offline regression tests; writes no fixtures or bytecode."""

import copy
import importlib.util
import io
import json
import sys
import unittest
from pathlib import Path
from unittest.mock import patch

from PIL import Image, ImageChops

sys.dont_write_bytecode = True
if "amenities_hu_renderer" not in sys.modules:
    spec = importlib.util.spec_from_file_location("amenities_hu_renderer", Path(__file__).with_name("2026-10-16-build-amenities-hu.py"))
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
r = sys.modules["amenities_hu_renderer"]


class AmenitiesTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.copy = r.load_copy()
        cls.overlays, cls.layouts = r.make_overlays(cls.copy)

    def test_actual_source_pin(self):
        r.source_guard()
        self.assertEqual(r.SOURCE.stat().st_size, 71254636)

    def test_source_hash_change_rejected(self):
        with patch.object(r, "sha256", return_value="0" * 64):
            with self.assertRaisesRegex(ValueError, "hash mismatch"):
                r.source_guard()

    def test_source_path_change_rejected(self):
        with self.assertRaisesRegex(ValueError, "source path"):
            r.source_guard(r.ROOT / "wrong.mp4")

    def test_source_byte_change_rejected(self):
        with patch.object(r, "SOURCE_BYTES", r.SOURCE_BYTES + 1):
            with self.assertRaisesRegex(ValueError, "byte count"):
                r.source_guard()

    def test_native_source_contract(self):
        r.check_video(r.legacy.inspect_mp4(r.SOURCE), audio=True, source=True)

    def test_accents_actual_font_cmap(self):
        accents = "\u00e1\u00e9\u00ed\u00f3\u00f6\u0151\u00fa\u00fc\u0171\u00c1\u00c9\u00cd\u00d3\u00d6\u0150\u00da\u00dc\u0170"
        for font in r.FONTS.values():
            self.assertLessEqual(set(map(ord, accents)), r.legacy.font_glyphs(font))
        r.legacy.glyph_check(self.copy)
        self.assertIn("\u00f3", self.copy["brand"])

    def test_cjk_rejection_all_text(self):
        for code in (0x4E2D, 0x3400, 0xF900, 0x20000, 0x31350):
            candidate = copy.deepcopy(self.copy)
            candidate["scenes"][2]["subtitle"] += chr(code)
            with self.assertRaisesRegex(ValueError, "CJK"):
                r.validate_copy(candidate)

    def test_damaged_accents_rejected(self):
        for suffix in ("\ufffd", "\u00c3\u00a1", "o\u030b"):
            candidate = copy.deepcopy(self.copy)
            candidate["scenes"][0]["subtitle"] += suffix
            with self.assertRaises(ValueError):
                r.validate_copy(candidate)

    def test_exact_continuous_integer_intervals(self):
        self.assertEqual(r.INTERVALS, ((0, 120), (120, 240), (240, 360), (360, 480), (480, 600),
                                      (600, 720), (720, 840), (840, 960), (960, 1089)))
        for n in range(r.FRAMES):
            self.assertEqual(sum(a <= n < b for a, b in r.INTERVALS), int(n < 1089))

    def test_intervals_reject_gap_overlap_float_bool_and_shift(self):
        for key, value in [("start_frame", 119), ("start_frame", 121), ("start_frame", 120.0),
                           ("start_frame", True), ("end_frame", 239), ("end_frame", 241)]:
            candidate = copy.deepcopy(self.copy)
            candidate["scenes"][1][key] = value
            with self.assertRaises(ValueError):
                r.validate_copy(candidate)

    def test_closing_and_intro_exact_boundaries(self):
        self.assertEqual((r.FRAMES, r.FPS, r.CLOSING_START, r.INTRO_END), (1292, 30, 1089, 135))
        self.assertAlmostEqual(r.FRAMES / r.FPS, 43.0666666667)
        self.assertEqual(r.enables()[0], "gte(n,0)*lt(n,135)")
        self.assertEqual(r.enables()[-1], "gte(n,1089)*lt(n,1292)")
        self.assertEqual(r.INTERVALS[-1][1], r.CLOSING_START)

    def test_all_masks_exact_opaque_and_unchanged_logo_region(self):
        r.validate_masks(self.overlays)
        for _, image in self.overlays[:-1]:
            self.assertIsNone(image.getchannel("A").crop((0, 0, 360, 220)).getbbox())
        self.assertEqual(self.overlays[-1][1].getpixel((0, 0)), (255, 255, 255, 255))
        self.assertEqual(self.overlays[-1][1].getchannel("A").getextrema(), (255, 255))

    def test_one_transparent_mask_pixel_rejected(self):
        changed = list(self.overlays)
        image = changed[1][1].copy()
        image.putpixel((1919, 1079), (0, 0, 0, 254))
        changed[1] = (changed[1][0], image)
        with self.assertRaisesRegex(ValueError, "exactly opaque"):
            r.validate_masks(changed)

    def test_genuine_logo_native_composite(self):
        expected = Image.new("RGBA", (250, 250), "white")
        with Image.open(r.LOGO) as logo:
            self.assertEqual(logo.size, (250, 250))
            expected.alpha_composite(logo)
        actual = self.overlays[-1][1].crop((835, 110, 1085, 360))
        self.assertIsNone(ImageChops.difference(expected, actual).getbbox())

    def test_font_layout_bounds_and_all_copy_drawn(self):
        self.assertEqual(len(self.layouts), 34)
        text = [item["text"] for item in self.layouts]
        for scene in self.copy["scenes"]:
            self.assertIn(scene["title"], text)
            self.assertIn(scene["subtitle"], text)
        for item in self.layouts:
            r.bounds(*item["box"])
            self.assertTrue(item["lines"])
            self.assertGreaterEqual(item["font_size"], 24)

    def test_essential_footer_text_above_meta_profile_overlay(self):
        self.assertEqual(r.FOOTER_BOX, (0, 700, 1920, 380))
        for scene in self.copy['scenes']:
            for key in ('title', 'subtitle'):
                layout = next(item for item in self.layouts if item['text'] == scene[key])
                self.assertLessEqual(layout['box'][1] + layout['box'][3], 880)
                self.assertEqual(len(layout['lines']), 1)

    def test_invalid_bounds_and_oversized_word_rejected(self):
        for box in ((-1, 0, 1, 1), (0, 765, 1920, 316), (320, 430, 1601, 215), (0, 0, 0, 1)):
            with self.assertRaises(ValueError):
                r.bounds(*box)
        with self.assertRaises(ValueError):
            r.text_block(Image.new("RGBA", (1920, 1080)), "W" * 100, (0, 0, 30, 20), 30)

    def test_all_qa_centers_boundaries_last_and_seconds(self):
        labels, seconds, samples = r.sample_frames(self.copy)
        self.assertEqual(len([k for k in labels if k.startswith("scene-")]), 9)
        self.assertLessEqual({0, 134, 135, 1088, 1089, 1291}, set(samples))
        self.assertEqual(seconds, list(range(0, 1292, 30)))
        self.assertEqual(samples, sorted(set(samples)))

    def test_render_command_native_silent_and_metadata_stripped(self):
        command = list(map(str, r.video_command()))
        for flag in ("-an", "-sn", "-dn", "-map_metadata", "-map_metadata:s", "-map_chapters", "-n"):
            self.assertIn(flag, command)
        for option, expected in (("-crf", "17"), ("-pix_fmt", "yuv420p"), ("-frames:v", "1292"),
                                 ("-c:v", "libx264"), ("-fps_mode", "passthrough"), ("-movflags", "+faststart")):
            self.assertEqual(command[command.index(option) + 1], expected)
        graph = command[command.index("-filter_complex") + 1]
        self.assertNotIn("scale=", graph)
        self.assertNotIn("crop=", graph)
        self.assertNotIn("setpts", graph)
        self.assertNotIn("-r", command)
        self.assertEqual(graph.count("overlay="), 11)

    def test_ffmpeg_actual_zero_based_overlay_counter(self):
        raw = r.execute(["-v", "error", "-f", "lavfi", "-i", "color=black:s=8x8:r=30:d=0.1",
                         "-f", "lavfi", "-i", "color=red:s=8x8:r=30:d=0.1", "-filter_complex_threads", "1",
                         "-filter_complex", "[0:v][1:v]overlay=enable='gte(n,1)*lt(n,2)'",
                         "-frames:v", "3", "-threads", "1", "-pix_fmt", "rgb24", "-f", "rawvideo", "-"])
        self.assertEqual(len(raw), 3 * 8 * 8 * 3)
        self.assertEqual(raw[:3], b"\x00\x00\x00")
        self.assertGreater(raw[192], 240)
        self.assertEqual(raw[384:387], b"\x00\x00\x00")

    def test_default_summary_has_no_process_or_output_writes(self):
        with patch.object(r, "execute", side_effect=AssertionError("Unexpected process")), \
             patch.object(Path, "mkdir", side_effect=AssertionError("Unexpected directory")), \
             patch.object(Path, "write_text", side_effect=AssertionError("Unexpected write")), \
             patch("sys.stdout", new_callable=io.StringIO) as stdout:
            r.main([])
            summary = json.loads(stdout.getvalue())
        self.assertEqual(summary["mode"], "OFFLINE_SUMMARY_NO_WRITES")
        self.assertFalse(summary["upload_allowed"])
        self.assertEqual(summary["rights"], "NOT_CONFIRMED")

    def test_audio_cannot_bypass_render_optin(self):
        with self.assertRaisesRegex(ValueError, "requires explicit"):
            r.main(["--original-audio"])

    def test_output_scope_and_existing_run_rejected(self):
        for path in (r.OUTPUT.parent, r.OUTPUT.parent / "source-review", r.OUTPUT.parent / "render-v1"):
            with self.assertRaisesRegex(ValueError, "exclusive"):
                r.output_guard(path)
        with patch.object(Path, "exists", return_value=True):
            with self.assertRaisesRegex(ValueError, "No-overwrite"):
                r.output_guard()

    def test_full_decode_report_requires_1292_continuous_frames(self):
        header = "#tb 0: 1/30\n#dimensions 0: 1920x1080\n"
        rows = [f"0, {n}, {n}, 1, 3110400, abc\n" for n in range(1292)]
        report = header + "".join(rows)
        self.assertEqual(r.decode_report(report)["decoded_video_frames"], 1292)
        for bad in (header + "".join(rows[:-1]), report.replace("0, 134, 134,", "0, 134, 135,"),
                    report.replace("#tb 0: 1/30", "#tb 0: 1/25"), report.replace("3110400", "1")):
            with self.assertRaises(ValueError):
                r.decode_report(bad)


def run_tests():
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(AmenitiesTests)
    names = [test.id().rsplit(".", 1)[-1] for test in suite]
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    r.require(result.wasSuccessful(), "Offline self-tests failed; no render allowed")
    return {"tests": result.testsRun, "failures": len(result.failures), "errors": len(result.errors), "passed": names,
            "ffmpeg_counter_test_explicit": True}


if __name__ == "__main__":
    print(json.dumps(run_tests(), indent=2))
