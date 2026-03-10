# Muzakily Mac Companion

A lightweight Python daemon that runs on your Mac and bridges between Apple Music and muzakily.
It listens for download requests (triggered by the Apple Shortcut) via Pusher, downloads
the track using [gamdl](https://github.com/nicosemp/gamdl), and uploads it to your muzakily
library with the tags you selected.

## Requirements

- Python 3.11+
- [gamdl](https://github.com/nicosemp/gamdl) installed and configured with your Apple Music cookies

## Setup

1. Install gamdl and its dependencies:
   ```bash
   pip install gamdl
   ```
   Then follow gamdl's setup instructions to configure your Apple Music cookies.

2. Install companion dependencies:
   ```bash
   pip install -r requirements.txt
   ```

3. Copy the example env file and fill in your values:
   ```bash
   cp .env.example .env
   ```

   | Variable | Description |
   |---|---|
   | `MUZAKILY_URL` | Base URL of your muzakily server |
   | `MUZAKILY_TOKEN` | Sanctum API token (get from muzakily Settings → API Tokens) |
   | `MUZAKILY_USER_ID` | Your user UUID (visible in muzakily Settings → Profile) |
   | `PUSHER_APP_KEY` | Pusher app key (from your muzakily `.env`) |
   | `PUSHER_APP_CLUSTER` | Pusher cluster (from your muzakily `.env`) |
   | `GAMDL_CMD` | Path or command name for gamdl (default: `gamdl`) |
   | `DOWNLOAD_OUTPUT_DIR` | Directory where gamdl saves files (default: `/tmp/gamdl-downloads`) |

4. Run the companion:
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
3. The companion receives the event, downloads the track via gamdl
4. The companion uploads the file to muzakily with the `download_request_id`
5. Muzakily processes the file, applies the selected tags, and marks the request completed
