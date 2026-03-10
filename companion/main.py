#!/usr/bin/env python3
"""Muzakily Mac companion daemon.

Listens for download.requested Pusher events on the user's private channel,
downloads the track via the configured Tidal downloader, and uploads it to muzakily.
"""

import logging
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


def pusher_auth(socket_id: str, channel_name: str) -> str:
    """Authenticate with muzakily's broadcasting auth endpoint."""
    response = requests.post(
        f"{config.MUZAKILY_URL}/broadcasting/auth",
        headers={"Authorization": f"Bearer {config.MUZAKILY_TOKEN}"},
        json={"socket_id": socket_id, "channel_name": channel_name},
        timeout=10,
    )
    response.raise_for_status()
    return response.text


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


def connect() -> None:
    """Connect to Pusher and subscribe to the user's private channel."""
    channel_name = f"private-user.{config.MUZAKILY_USER_ID}"

    pusher_client = pysher.Pusher(
        key=config.PUSHER_APP_KEY,
        cluster=config.PUSHER_APP_CLUSTER,
        auth_endpoint=pusher_auth,
    )

    def on_connect(data: str) -> None:
        logger.info("Connected to Pusher")
        channel = pusher_client.subscribe(channel_name)
        channel.bind("download.requested", lambda data: handle_download_requested(
            __import__("json").loads(data)
        ))
        logger.info("Subscribed to %s", channel_name)

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
    connect()
