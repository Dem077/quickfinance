"""Fetch all images from a ScribeHow document and download them."""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path
from urllib.parse import urlparse
from urllib.request import Request, urlopen

DOC_ID = "ohkXFWA1R7inEOHCmLZ07w"
SLUG = "How_to_Create_and_Submit_a_New_Purchase_Request"
OUT_DIR = Path(__file__).resolve().parent / "scribe_images" / DOC_ID

CANDIDATE_URLS = [
    f"https://scribehow.com/embed/{SLUG}__{DOC_ID}?skipIntro=true",
    f"https://scribehow.com/shared/{SLUG}__{DOC_ID}",
    f"https://scribehow.com/viewer/{DOC_ID}",
    f"https://api.scribehow.com/v1/documents/{DOC_ID}",
    f"https://api.scribehow.com/documents/{DOC_ID}",
    f"https://scribehow.com/api/documents/{DOC_ID}",
    f"https://scribehow.com/page-data/shared/{SLUG}__{DOC_ID}/page-data.json",
]

UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36"
IMG_RE = re.compile(
    r"https?://[^\s\"'<>\\]+?\.(?:png|jpe?g|webp|gif)(?:\?[^\s\"'<>\\]*)?",
    re.I,
)
# Also catch Scribe/Cloudinary style URLs without obvious extension in path end
CDN_RE = re.compile(
    r"https?://(?:res\.cloudinary\.com|cdn\.scribehow\.com|images\.scribehow\.com|"
    r"scribe-prod[^\s\"'<>\\]+|storage\.googleapis\.com/[^\s\"'<>\\]+|"
    r"[^\s\"'<>\\]*scribehow[^\s\"'<>\\]*)/[^\s\"'<>\\]+",
    re.I,
)


def fetch(url: str) -> tuple[int, str, bytes]:
    req = Request(url, headers={"User-Agent": UA, "Accept": "*/*"})
    with urlopen(req, timeout=30) as resp:
        data = resp.read()
        ctype = resp.headers.get("Content-Type", "")
        return resp.status, ctype, data


def extract_urls_from_text(text: str) -> set[str]:
    urls = set(IMG_RE.findall(text))
    for m in CDN_RE.findall(text):
        # trim trailing punctuation/json junk
        u = m.rstrip("\\").rstrip(".,);]}")
        if any(x in u.lower() for x in (".png", ".jpg", ".jpeg", ".webp", ".gif", "image", "upload", "cloudinary")):
            urls.add(u)
    return urls


def walk_json(obj, found: set[str]) -> None:
    if isinstance(obj, dict):
        for k, v in obj.items():
            key = str(k).lower()
            if isinstance(v, str) and (
                key.endswith("url")
                or "image" in key
                or "screenshot" in key
                or "src" in key
                or "thumbnail" in key
            ):
                if v.startswith("http"):
                    found.add(v)
            walk_json(v, found)
    elif isinstance(obj, list):
        for item in obj:
            walk_json(item, found)
    elif isinstance(obj, str) and obj.startswith("http") and any(
        ext in obj.lower() for ext in (".png", ".jpg", ".jpeg", ".webp", ".gif", "cloudinary", "image")
    ):
        found.add(obj)


def ext_from_url(url: str, ctype: str = "") -> str:
    path = urlparse(url).path.lower()
    for ext in (".png", ".jpg", ".jpeg", ".webp", ".gif"):
        if path.endswith(ext):
            return ext.lstrip(".")
    if "png" in ctype:
        return "png"
    if "jpeg" in ctype or "jpg" in ctype:
        return "jpg"
    if "webp" in ctype:
        return "webp"
    if "gif" in ctype:
        return "gif"
    return "png"


def main() -> int:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    all_urls: set[str] = set()
    debug_dir = OUT_DIR / "_debug"
    debug_dir.mkdir(exist_ok=True)

    print("Probing candidate URLs...")
    for url in CANDIDATE_URLS:
        try:
            status, ctype, data = fetch(url)
            print(f"  [{status}] {ctype[:40]!r} {url}")
            suffix = ".json" if "json" in ctype else ".html"
            (debug_dir / (re.sub(r"[^a-zA-Z0-9]+", "_", url)[-80:] + suffix)).write_bytes(data)
            text = data.decode("utf-8", errors="replace")
            all_urls |= extract_urls_from_text(text)

            # __NEXT_DATA__
            m = re.search(r'<script id="__NEXT_DATA__"[^>]*>(.*?)</script>', text, re.S)
            if m:
                try:
                    payload = json.loads(m.group(1))
                    walk_json(payload, all_urls)
                    (debug_dir / "next_data.json").write_text(json.dumps(payload, indent=2)[:500000], encoding="utf-8")
                except json.JSONDecodeError:
                    pass

            if "json" in ctype or text.lstrip().startswith("{") or text.lstrip().startswith("["):
                try:
                    payload = json.loads(text)
                    walk_json(payload, all_urls)
                except json.JSONDecodeError:
                    pass
        except Exception as e:
            print(f"  [ERR] {url} -> {e}")

    # Also try GraphQL / public endpoints commonly used by Scribe
    graphql_candidates = [
        ("https://scribehow.com/graphql", {"query": f'{{ document(id: "{DOC_ID}") {{ id name }} }}'}),
    ]

    print(f"\nFound {len(all_urls)} candidate image URLs before filtering.")
    # Prefer real image URLs
    image_urls = sorted(
        u
        for u in all_urls
        if not u.endswith(".svg")
        and "favicon" not in u.lower()
        and "logo" not in u.lower()
        and ("upload" in u.lower() or "cloudinary" in u.lower() or "screenshot" in u.lower() or re.search(r"\.(png|jpe?g|webp|gif)", u, re.I))
    )

    if not image_urls:
        image_urls = sorted(all_urls)

    print(f"Downloading {len(image_urls)} URLs to {OUT_DIR}...\n")
    (OUT_DIR / "image_urls.txt").write_text("\n".join(image_urls), encoding="utf-8")

    saved = 0
    for i, url in enumerate(image_urls, 1):
        try:
            status, ctype, data = fetch(url)
            if status >= 400 or not data:
                print(f"  skip [{status}] {url}")
                continue
            if "html" in ctype and len(data) < 5000:
                print(f"  skip html {url}")
                continue
            ext = ext_from_url(url, ctype)
            name = f"step_{i:02d}.{ext}"
            path = OUT_DIR / name
            path.write_bytes(data)
            print(f"  saved {name} ({len(data)} bytes) <- {url[:120]}")
            saved += 1
        except Exception as e:
            print(f"  fail {url[:120]} -> {e}")

    print(f"\nDone. Saved {saved} image(s) to {OUT_DIR}")
    if saved == 0:
        print("No images downloaded. Inspect docs/scribe_images/.../_debug for page payloads.")
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
