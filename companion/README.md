# Muzakily Mac Companion

A lightweight Python daemon that runs on your Mac and bridges between Tidal and muzakily.
It listens for download requests (triggered by the Apple Shortcut) via Pusher, downloads
the track using a Tidal downloader CLI, and uploads it to your muzakily library with the
tags you selected.

## Requirements

- Python 3.11+
- A Tidal downloader CLI (e.g. [tidal-dl](https://github.com/yaronzz/Tidal-Media-Downloader)
  or [tidal-dl-ng](https://github.com/exislow/tidal-dl-ng))

## Setup

1. Install Python dependencies:
   ```bash
   pip install -r requirements.txt
   ```

2. Copy the example env file and fill in your values:
   ```bash
   cp .env.example .env
   ```

   | Variable | Description |
   |---|---|
   | `MUZAKILY_URL` | Base URL of your muzakily server |
   | `MUZAKILY_TOKEN` | Sanctum API token (get from muzakily account settings) |
   | `MUZAKILY_USER_ID` | Your user UUID (visible in muzakily profile) |
   | `PUSHER_APP_KEY` | Pusher app key (from your muzakily `.env`) |
   | `PUSHER_APP_CLUSTER` | Pusher cluster (from your muzakily `.env`) |
   | `TIDAL_DOWNLOADER_CMD` | Path or command name for the Tidal downloader CLI |
   | `TIDAL_OUTPUT_DIR` | Directory where the downloader saves files |

3. Run the companion:
   ```bash
   python main.py
   ```

## Running as a background daemon with launchd

Create `~/Library/LaunchAgents/com.muzakily.companion.plist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.muzakily.companion</string>
    <key>ProgramArguments</key>
    <array>
        <string>/usr/bin/python3</string>
        <string>/path/to/muzakily/companion/main.py</string>
    </array>
    <key>WorkingDirectory</key>
    <string>/path/to/muzakily/companion</string>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>/tmp/muzakily-companion.log</string>
    <key>StandardErrorPath</key>
    <string>/tmp/muzakily-companion.log</string>
</dict>
</plist>
```

Load it:
```bash
launchctl load ~/Library/LaunchAgents/com.muzakily.companion.plist
```

## How it works

1. The Apple Shortcut (see `SHORTCUT.md`) POSTs a download request to muzakily
2. Muzakily broadcasts a `download.requested` event on your Pusher private channel
3. The companion receives the event, downloads the track from Tidal
4. The companion uploads the file to muzakily with the `download_request_id`
5. Muzakily processes the file, applies the selected tags, and marks the request completed
