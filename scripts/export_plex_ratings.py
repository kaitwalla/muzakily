#!/usr/bin/env python3
"""
Export rated songs from Plex to a JSON file for import into Muzakily.

Usage:
    python export_plex_ratings.py

Configuration:
    Create a .env.plex file in the scripts directory (see .env.plex.example)

Requires: pip install plexapi python-dotenv
"""

import argparse
import json
import os
import sys
from datetime import datetime, timezone
from pathlib import Path

from dotenv import load_dotenv
from plexapi.server import PlexServer


# Load configuration from .env.plex
env_file = Path(__file__).parent / ".env.plex"
if env_file.exists():
    load_dotenv(env_file)

PLEX_URL = os.environ.get("PLEX_URL", "http://localhost:32400")
PLEX_TOKEN = os.environ.get("PLEX_TOKEN")
PLEX_MUSIC_SECTION = os.environ.get("PLEX_MUSIC_SECTION", "music")


def export_rated_tracks(plex: PlexServer, section: str, min_stars: int) -> list[dict]:
    """Export all tracks with rating >= min_stars."""
    tracks = []
    min_rating = min_stars * 2  # Plex uses 0-10 scale

    # Fetch all tracks
    print("Fetching all tracks...", file=sys.stderr)
    all_tracks = plex.library.section(section).searchTracks()
    print(f"Found {len(all_tracks)} total tracks", file=sys.stderr)

    # Debug: print first 50 tracks with their ratings
    print("\nFirst 50 tracks with ratings:", file=sys.stderr)
    for i, track in enumerate(all_tracks[:50]):
        print(f"  {track.title} | userRating: {track.userRating}", file=sys.stderr)
    print("", file=sys.stderr)

    for track in all_tracks:
        rating = track.userRating or 0

        if rating < min_rating:
            continue

        # Get file path
        file_path = None
        if track.media and track.media[0].parts:
            file_path = track.media[0].parts[0].file

        if not file_path:
            continue

        tracks.append({
            "title": track.title or "Unknown",
            "artist": track.grandparentTitle or track.originalTitle or "Unknown",
            "album": track.parentTitle or "Unknown",
            "path": file_path,
            "rating": int(rating) // 2,  # Convert to 1-5 stars
            "duration_ms": track.duration,
        })

    return tracks


def main():
    parser = argparse.ArgumentParser(
        description="Export rated songs from Plex to JSON",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__
    )
    parser.add_argument("--min-stars", type=int, default=1, help="Minimum star rating to export (1-5, default: 1)")
    parser.add_argument("--output", "-o", default="plex_ratings.json", help="Output file path (default: plex_ratings.json)")
    parser.add_argument("--strip-prefix", help="Path prefix to strip from file paths")
    parser.add_argument("--section", default=PLEX_MUSIC_SECTION, help=f"Plex music library section name (default: {PLEX_MUSIC_SECTION})")

    args = parser.parse_args()

    # Validate configuration
    if not PLEX_TOKEN:
        print("Error: PLEX_TOKEN not set. Create scripts/.env.plex from .env.plex.example", file=sys.stderr)
        sys.exit(1)

    # Connect to Plex
    print(f"Connecting to Plex at {PLEX_URL}...", file=sys.stderr)
    try:
        plex = PlexServer(PLEX_URL, PLEX_TOKEN)
    except Exception as e:
        print(f"Error: Could not connect to Plex server - {e}", file=sys.stderr)
        sys.exit(1)

    # Export tracks
    print(f"Fetching tracks with rating >= {args.min_stars} stars...", file=sys.stderr)
    tracks = export_rated_tracks(plex, args.section, args.min_stars)

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
        "exported_at": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "plex_url": PLEX_URL,
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
