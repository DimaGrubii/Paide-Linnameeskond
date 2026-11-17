#!/usr/bin/env python3
"""
Finds and copies the highest-resolution original image files needed for the 2016–2026
news archive from `/Users/dumitrugrubii/Downloads/Archive material for website`
into `assets/img/uploads/<YYYY>/<MM>/`, ignoring all low-resolution WordPress thumbnails.
"""

import os
import re
import shutil
import sys
import unicodedata

WORKSPACE = "/Users/dumitrugrubii/Documents/Paide Linnameeskond    Website"
MANIFEST = os.path.join(WORKSPACE, "data", "uploads-image-list.txt")
TARGET_UPLOADS = os.path.join(WORKSPACE, "assets", "img", "uploads")
IMAGE_EXTS = {".jpg", ".jpeg", ".png", ".webp", ".gif", ".svg"}


def find_best_file(src_root, rel_path):
    parts = rel_path.split("/")
    if len(parts) < 3:
        return None, None
    year, month, filename = parts[0], parts[1], "/".join(parts[2:])
    folder = os.path.join(src_root, year, month)
    if not os.path.isdir(folder):
        return None, None

    files_in_dir = os.listdir(folder)
    nfc_map = {unicodedata.normalize("NFC", fn).lower(): fn for fn in files_in_dir}

    target_nfc = unicodedata.normalize("NFC", filename)
    target_low = target_nfc.lower()
    _, ext = os.path.splitext(target_low)
    if ext not in IMAGE_EXTS:
        return None, None

    # Strip any WordPress -WIDTHxHEIGHT thumbnail suffix to find the full-res original
    base_no_dim_nfc = re.sub(r"-\d+x\d+(\.[a-z0-9]+)$", r"\1", target_nfc, flags=re.I)
    base_no_dim_low = base_no_dim_nfc.lower()
    name_root_low, ext_low = os.path.splitext(base_no_dim_low)

    candidates = []
    # 1. Original un-cropped full-res file (e.g. IMG_9332.jpg)
    if base_no_dim_low in nfc_map:
        real_fn = nfc_map[base_no_dim_low]
        candidates.append((os.path.join(folder, real_fn), unicodedata.normalize("NFC", real_fn)))
    # 2. WordPress -scaled full-res file (e.g. IMG_9332-scaled.jpg)
    scaled_low = f"{name_root_low}-scaled{ext_low}"
    if scaled_low in nfc_map:
        real_fn = nfc_map[scaled_low]
        candidates.append((os.path.join(folder, real_fn), base_no_dim_nfc))
    # 3. Exact requested file if original wasn't present
    if target_low in nfc_map:
        real_fn = nfc_map[target_low]
        candidates.append((os.path.join(folder, real_fn), target_nfc))
    # 4. Any other variant with the same root name (pick largest file size = highest res)
    if not candidates:
        for k, real_fn in nfc_map.items():
            if k.startswith(name_root_low) and k.endswith(ext_low):
                candidates.append((os.path.join(folder, real_fn), target_nfc))

    if not candidates:
        return None, None

    # Sort by file size descending so we always pick the highest-resolution original
    candidates.sort(key=lambda item: os.path.getsize(item[0]), reverse=True)
    best_src, best_dest_name = candidates[0]
    return best_src, f"{year}/{month}/{best_dest_name}"


def main():
    src_root = (
        sys.argv[1]
        if len(sys.argv) > 1
        else "/Users/dumitrugrubii/Downloads/Archive material for website"
    )
    src_root = os.path.abspath(src_root)
    if not os.path.exists(src_root):
        print(f"Error: Path does not exist: {src_root}")
        sys.exit(1)

    if os.path.isdir(os.path.join(src_root, "wp-content", "uploads")):
        src_root = os.path.join(src_root, "wp-content", "uploads")
    elif os.path.isdir(os.path.join(src_root, "uploads")):
        src_root = os.path.join(src_root, "uploads")

    with open(MANIFEST, encoding="utf-8") as f:
        needed = [
            unicodedata.normalize("NFC", line.strip().replace("wp-content/uploads/", ""))
            for line in f
            if line.strip()
        ]

    copied = 0
    upgraded = 0
    total_bytes = 0
    mapping = {}

    for rel_path in needed:
        best_src, clean_rel_dest = find_best_file(src_root, rel_path)
        if best_src and clean_rel_dest:
            dest_path = os.path.join(TARGET_UPLOADS, clean_rel_dest)
            os.makedirs(os.path.dirname(dest_path), exist_ok=True)
            shutil.copy2(best_src, dest_path)
            # Also if the original article requested a -WIDTHxHEIGHT name that differs from clean_rel_dest,
            # record it in mapping so build_news_archive.py can point directly to clean_rel_dest!
            mapping[rel_path] = clean_rel_dest
            if clean_rel_dest.lower() != rel_path.lower():
                upgraded += 1
            copied += 1
            total_bytes += os.path.getsize(dest_path)

    map_file = os.path.join(WORKSPACE, "data", "uploads-resolved-map.json")
    with open(map_file, "w", encoding="utf-8") as f:
        import json
        json.dump(mapping, f, ensure_ascii=False, indent=2)

    print(
        f"Done! Copied {copied} highest-resolution images ({total_bytes / (1024*1024):.1f} MB) into assets/img/uploads/."
    )
    print(f"Upgraded {upgraded} low-res thumbnail references to their full-resolution originals!")


if __name__ == "__main__":
    main()
