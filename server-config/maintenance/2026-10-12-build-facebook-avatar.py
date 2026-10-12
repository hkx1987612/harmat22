"""Add white padding to the pinned official logo without resizing it."""

import argparse
import hashlib
from pathlib import Path

from PIL import Image


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("source", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    source = args.source.resolve(strict=True)
    output = args.output.resolve()
    expected = "99a024d825dac605fd3e09227bcaacb898b30f5d59ef21ea0eeef990fd638c5f"
    if hashlib.sha256(source.read_bytes()).hexdigest() != expected:
        parser.error("Source does not match the approved official logo.")
    if output.name != "harmat-facebook-avatar.png" or source == output:
        parser.error("Use a separate harmat-facebook-avatar.png output.")
    if output.exists():
        parser.error("Output already exists; no overwrite is permitted.")
    with Image.open(source) as original:
        logo = original.convert("RGBA")
    if logo.size != (250, 250):
        parser.error("Unexpected source dimensions.")
    canvas = Image.new("RGBA", (360, 360), "white")
    canvas.alpha_composite(logo, (55, 55))
    for y in range(250):
        for x in range(250):
            pixel = logo.getpixel((x, y))
            if pixel[3] == 255:
                assert canvas.getpixel((x + 55, y + 55)) == pixel
    assert 2 * 125**2 < 180**2
    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open("xb") as handle:
        canvas.convert("RGB").save(handle, format="PNG")
    with Image.open(output) as saved:
        assert saved.size == (360, 360)
        assert saved.convert("RGB").tobytes() == canvas.convert("RGB").tobytes()
    assert hashlib.sha256(source.read_bytes()).hexdigest() == expected
    print("PASS: no resizing; opaque source pixels retained; circle-safe padding.")
    print("Output SHA-256:", hashlib.sha256(output.read_bytes()).hexdigest())


if __name__ == "__main__":
    main()
