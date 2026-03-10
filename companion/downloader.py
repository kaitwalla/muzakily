import glob
import os
import subprocess

import config


def download(url: str) -> str:
    """Download a Tidal track and return the path to the downloaded file.

    Raises RuntimeError if the download fails or no file is found.
    """
    os.makedirs(config.TIDAL_OUTPUT_DIR, exist_ok=True)

    result = subprocess.run(
        [config.TIDAL_DOWNLOADER_CMD, url, "--output", config.TIDAL_OUTPUT_DIR],
        capture_output=True,
        text=True,
    )

    if result.returncode != 0:
        raise RuntimeError(
            f"Tidal downloader failed (exit {result.returncode}): {result.stderr.strip()}"
        )

    # Find the most recently modified audio file in the output directory
    patterns = ["*.mp3", "*.flac", "*.m4a", "*.aac"]
    candidates: list[tuple[float, str]] = []

    for pattern in patterns:
        for path in glob.glob(os.path.join(config.TIDAL_OUTPUT_DIR, "**", pattern), recursive=True):
            candidates.append((os.path.getmtime(path), path))

    if not candidates:
        raise RuntimeError(
            f"No audio file found in {config.TIDAL_OUTPUT_DIR} after download"
        )

    # Return the most recently modified file
    candidates.sort(key=lambda x: x[0], reverse=True)
    return candidates[0][1]
