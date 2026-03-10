import os
from dotenv import load_dotenv

load_dotenv()


def get(key: str, default: str | None = None) -> str:
    value = os.environ.get(key, default)
    if value is None:
        raise RuntimeError(f"Missing required config: {key}")
    return value


MUZAKILY_URL: str = get("MUZAKILY_URL")
MUZAKILY_TOKEN: str = get("MUZAKILY_TOKEN")
MUZAKILY_USER_ID: str = get("MUZAKILY_USER_ID")
PUSHER_APP_KEY: str = get("PUSHER_APP_KEY")
PUSHER_APP_CLUSTER: str = get("PUSHER_APP_CLUSTER")
GAMDL_CMD: str = get("GAMDL_CMD", "gamdl")
DOWNLOAD_OUTPUT_DIR: str = get("DOWNLOAD_OUTPUT_DIR", "/tmp/gamdl-downloads")
