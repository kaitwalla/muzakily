import glob
import os
import subprocess

import config


def download(url: str) -> str:
    """Download an Apple Music track via gamdl and return the path to the downloaded file.

    Raises RuntimeError if the download fails or no file is found.
    """
    os.makedirs(config.DOWNLOAD_OUTPUT_DIR, exist_ok=True)

    result = subprocess.run(
        [config.GAMDL_CMD, "--output-path", config.DOWNLOAD_OUTPUT_DIR, url],
        capture_output=True,
        text=True,
    )

    if result.returncode != 0:
        raise RuntimeError(
            f"gamdl failed (exit {result.returncode}): {result.stderr.strip()}"
        )

    # Find the most recently modified audio file in the output directory
    patterns = ["*.m4a", "*.aac", "*.mp3", "*.flac"]
    candidates: list[tuple[float, str]] = []

    for pattern in patterns:
        for path in glob.glob(os.path.join(config.DOWNLOAD_OUTPUT_DIR, "**", pattern), recursive=True):
            candidates.append((os.path.getmtime(path), path))

    if not candidates:
        raise RuntimeError(
            f"No audio file found in {config.DOWNLOAD_OUTPUT_DIR} after download"
        )

    # Return the most recently modified file
    candidates.sort(key=lambda x: x[0], reverse=True)
    return candidates[0][1]
