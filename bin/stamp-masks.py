"""Object masks for the stamp art, made by an AI segmentation model (rembg).

A preprocessing step outside the Laravel runtime: for each art file it writes a
greyscale mask (255 = object) to <cache>/<model>/<sha1 of the source>.png, which
App\\Achievements\\Stamps\\BackgroundRemoval reads. Masks already made are skipped.
The art files are only read, never written.

    ~/.venvs/oof-rembg/bin/python -I bin/stamp-masks.py \\
        --art resources/images/origami/achievements \\
        --cache storage/app/private/stamps/masks \\
        --model birefnet-general glory/fun-size-bronze.png ...

Without file arguments every PNG under --art is done. It runs on the CPU only:
onnxruntime's CoreML provider hangs while compiling birefnet-general on macOS.
"""

import argparse
import hashlib
import pathlib
import sys
import time

from PIL import Image
from rembg import new_session, remove


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--art", required=True)
    parser.add_argument("--cache", required=True)
    parser.add_argument("--model", default="birefnet-general")
    parser.add_argument("files", nargs="*", help="Art files relative to --art")
    args = parser.parse_args()

    art = pathlib.Path(args.art).resolve()
    out = pathlib.Path(args.cache).resolve() / args.model
    out.mkdir(parents=True, exist_ok=True)
    files = [art / name for name in args.files] if args.files else sorted(art.rglob("*.png"))
    session = None

    for source in files:
        digest = hashlib.sha1(source.read_bytes()).hexdigest()
        target = out / f"{digest}.png"

        if target.exists():
            print(f"cached  {source.relative_to(art)}")
            continue

        session = session or new_session(args.model, providers=["CPUExecutionProvider"])
        started = time.time()

        with Image.open(source) as image:
            mask = remove(image.convert("RGB"), session=session, only_mask=True)

        mask.convert("L").save(target)
        print(f"made    {source.relative_to(art)} in {time.time() - started:.1f}s")

    return 0


if __name__ == "__main__":
    sys.exit(main())
