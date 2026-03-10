import os

import requests

import config


def upload(file_path: str, download_request_id: str) -> dict:
    """Upload a file to muzakily and return the response JSON.

    Raises requests.HTTPError if the upload fails.
    """
    url = f"{config.MUZAKILY_URL}/api/v1/upload"
    headers = {"Authorization": f"Bearer {config.MUZAKILY_TOKEN}"}

    filename = os.path.basename(file_path)
    mime_type = _mime_type_for(filename)

    with open(file_path, "rb") as f:
        response = requests.post(
            url,
            headers=headers,
            files={"file": (filename, f, mime_type)},
            data={"download_request_id": download_request_id},
            timeout=300,
        )

    response.raise_for_status()
    return response.json()


def _mime_type_for(filename: str) -> str:
    ext = os.path.splitext(filename)[1].lower()
    return {
        ".mp3": "audio/mpeg",
        ".flac": "audio/flac",
        ".m4a": "audio/mp4",
        ".aac": "audio/mp4",
    }.get(ext, "application/octet-stream")
