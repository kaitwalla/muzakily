#!/usr/bin/env python3
"""Muzakily Mac companion daemon.

Listens for download.requested Pusher events on the user's private channel,
downloads the track via the configured Tidal downloader, and uploads it to muzakily.
"""

import logging
import os
import shutil
import time

import pysher
import requests

import config
import downloader
import uploader

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)
logger = logging.getLogger(__name__)


def handle_download_requested(data: dict) -> None:
    """Handle a download.requested event from Pusher."""
    download_request_id: str = data.get("download_request_id", "")
    url: str = data.get("url", "")

    if not download_request_id or not url:
        logger.error("Received malformed download.requested event: %s", data)
        return

    logger.info("Download requested: %s (request_id=%s)", url, download_request_id)

    try:
        file_path = downloader.download(url)
        logger.info("Downloaded to: %s", file_path)
    except RuntimeError as e:
        logger.error("Download failed: %s", e)
        return

    try:
        result = uploader.upload(file_path, download_request_id)
        logger.info("Upload complete: %s", result)
    except requests.HTTPError as e:
        logger.error("Upload failed: %s", e)
        return
    finally:
        try:
            os.remove(file_path)
            logger.info("Cleaned up: %s", file_path)
        except OSError as e:
            logger.warning("Failed to clean up %s: %s", file_path, e)


def preflight_check() -> None:
    """Verify the API token is valid and MUZAKILY_USER_ID matches the authenticated user."""
    response = requests.get(
        f"{config.MUZAKILY_URL}/api/v1/auth/me",
        headers={"Authorization": f"Bearer {config.MUZAKILY_TOKEN}"},
        timeout=10,
    )
    if response.status_code == 401:
        raise RuntimeError("MUZAKILY_TOKEN is invalid or expired.")
    response.raise_for_status()

    actual_uuid = response.json().get("data", {}).get("uuid")
    if actual_uuid != config.MUZAKILY_USER_ID:
        raise RuntimeError(
            f"MUZAKILY_USER_ID mismatch: config has '{config.MUZAKILY_USER_ID}' "
            f"but token belongs to user '{actual_uuid}'."
        )

    logger.info("Preflight OK — authenticated as %s", actual_uuid)

    # Test broadcast auth endpoint with a dummy socket_id
    auth_response = requests.post(
        f"{config.MUZAKILY_URL}/broadcasting/auth",
        headers={
            "Authorization": f"Bearer {config.MUZAKILY_TOKEN}",
            "X-Companion": "1",
        },
        data={
            "socket_id": "000000.000000",
            "channel_name": f"presence-companion.{config.MUZAKILY_USER_ID}",
        },
        timeout=10,
    )
    logger.info("Broadcast auth test: HTTP %s — %s", auth_response.status_code, auth_response.text[:300])


def connect() -> None:
    """Connect to Pusher and subscribe to the relevant channels."""
    gamdl_available = shutil.which(config.GAMDL_CMD) is not None

    pusher_client = pysher.Pusher(
        key=config.PUSHER_APP_KEY,
        cluster=config.PUSHER_APP_CLUSTER,
        auth_endpoint=f"{config.MUZAKILY_URL}/broadcasting/auth",
        auth_endpoint_headers={
            "Authorization": f"Bearer {config.MUZAKILY_TOKEN}",
            "X-Companion": "1",
            "X-Companion-Gamdl": "1" if gamdl_available else "0",
        },
    )

    def on_connect(data: str) -> None:
        logger.info("Connected to Pusher (gamdl available: %s)", gamdl_available)

        # Presence channel — signals to the web UI that the companion is connected
        pusher_client.subscribe(f"presence-companion.{config.MUZAKILY_USER_ID}")

        # Private channel — receives download.requested events
        private_channel = pusher_client.subscribe(f"private-user.{config.MUZAKILY_USER_ID}")
        private_channel.bind("download.requested", lambda data: handle_download_requested(
            __import__("json").loads(data)
        ))
        logger.info("Subscribed to companion presence and private download channels")

    pusher_client.connection.bind("pusher:connection_established", on_connect)
    pusher_client.connect()

    logger.info("Muzakily companion running. Waiting for download requests...")

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        logger.info("Shutting down companion.")
        pusher_client.disconnect()


if __name__ == "__main__":
    preflight_check()
    connect()
