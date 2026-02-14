#!/usr/bin/env python3
"""
Export rated songs from Plex to a JSON file for import into Muzakily.

Usage:
    python export_plex_ratings.py --url http://localhost:32400 --token YOUR_TOKEN

To find your Plex token:
    1. Open Plex Web App and sign in
    2. Browse to any media item
    3. Click "Get Info" then "View XML"
    4. Look for "X-Plex-Token" in the URL
"""

import argparse
import json
import sys
from datetime import datetime
from urllib.request import Request, urlopen
from urllib.error import URLError, HTTPError
from urllib.parse import urlencode


def get_json(url: str, token: str) -> dict:
    """Fetch JSON from Plex API."""
    req = Request(url)
    req.add_header("X-Plex-Token", token)
    req.add_header("Accept", "application/json")

    with urlopen(req, timeout=120) as response:
        return json.loads(response.read().decode("utf-8"))


def find_music_section(base_url: str, token: str) -> str | None:
    """Find the music library section ID."""
    data = get_json(f"{base_url}/library/sections", token)

    for section in data.get("MediaContainer", {}).get("Directory", []):
        if section.get("type") == "artist":
            return section.get("key")

    return None


def export_rated_tracks(base_url: str, token: str, section_id: str, min_stars: int) -> list[dict]:
    """Export all tracks with rating >= min_stars."""
    tracks = []
    min_rating = min_stars * 2  # Plex uses 0-10 scale

    # Fetch all tracks - we'll filter client-side since the API filter can be unreliable
    url = f"{base_url}/library/sections/{section_id}/all?type=10"
    data = get_json(url, token)

    for item in data.get("MediaContainer", {}).get("Metadata", []):
        rating = item.get("userRating", 0)

        if rating < min_rating:
            continue

        # Get file path from media parts
        file_path = None
        for media in item.get("Media", []):
            for part in media.get("Part", []):
                if "file" in part:
                    file_path = part["file"]
                    break
            if file_path:
                break

        if not file_path:
            continue

        tracks.append({
            "title": item.get("title", "Unknown"),
            "artist": item.get("grandparentTitle") or item.get("originalTitle") or "Unknown",
            "album": item.get("parentTitle") or "Unknown",
            "path": file_path,
            "rating": rating // 2,  # Convert to 1-5 stars
            "duration_ms": item.get("duration"),
        })

    return tracks


def main():
    parser = argparse.ArgumentParser(
        description="Export rated songs from Plex to JSON",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__
    )
    parser.add_argument("--url", required=True, help="Plex server URL (e.g., http://localhost:32400)")
    parser.add_argument("--token", required=True, help="Plex authentication token")
    parser.add_argument("--section", help="Music library section ID (auto-detects if not provided)")
    parser.add_argument("--min-stars", type=int, default=1, help="Minimum star rating to export (1-5, default: 1)")
    parser.add_argument("--output", "-o", default="plex_ratings.json", help="Output file path (default: plex_ratings.json)")
    parser.add_argument("--strip-prefix", help="Path prefix to strip from file paths")

    args = parser.parse_args()

    base_url = args.url.rstrip("/")

    # Find or validate section
    section_id = args.section
    if not section_id:
        print("Auto-detecting music library section...", file=sys.stderr)
        section_id = find_music_section(base_url, args.token)
        if not section_id:
            print("Error: Could not find music library section. Use --section to specify.", file=sys.stderr)
            sys.exit(1)
        print(f"Found music section: {section_id}", file=sys.stderr)

    # Export tracks
    print(f"Fetching tracks with rating >= {args.min_stars} stars...", file=sys.stderr)

    try:
        tracks = export_rated_tracks(base_url, args.token, section_id, args.min_stars)
    except HTTPError as e:
        print(f"Error: HTTP {e.code} - {e.reason}", file=sys.stderr)
        sys.exit(1)
    except URLError as e:
        print(f"Error: Could not connect to Plex server - {e.reason}", file=sys.stderr)
        sys.exit(1)

    if not tracks:
        print("No rated tracks found.", file=sys.stderr)
        sys.exit(0)

    # Strip prefix if requested
    if args.strip_prefix:
        prefix = args.strip_prefix.rstrip("/")
        for track in tracks:
            if track["path"].startswith(prefix):
                track["path"] = track["path"][len(prefix):].lstrip("/")

    # Build export data
    export_data = {
        "exported_at": datetime.utcnow().isoformat() + "Z",
        "plex_url": base_url,
        "section_id": section_id,
        "min_stars": args.min_stars,
        "track_count": len(tracks),
        "tracks": tracks,
    }

    # Write output
    with open(args.output, "w", encoding="utf-8") as f:
        json.dump(export_data, f, indent=2, ensure_ascii=False)

    print(f"Exported {len(tracks)} tracks to {args.output}", file=sys.stderr)

    # Summary by rating
    by_rating = {}
    for track in tracks:
        r = track["rating"]
        by_rating[r] = by_rating.get(r, 0) + 1

    print("\nBy rating:", file=sys.stderr)
    for stars in sorted(by_rating.keys(), reverse=True):
        print(f"  {'★' * stars}{'☆' * (5 - stars)}: {by_rating[stars]} tracks", file=sys.stderr)


if __name__ == "__main__":
    main()
