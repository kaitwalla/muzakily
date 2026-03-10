# Apple Shortcut: Queue Tidal Download

This shortcut grabs the current browser URL, lets you pick tags from muzakily, and queues
a download via the companion daemon.

## Prerequisites

- macOS Sonoma or later (for Shortcuts app)
- The muzakily companion running (see `README.md`)
- Your muzakily API token and URL

## Building the Shortcut

Open the **Shortcuts** app and create a new shortcut. Add the following actions in order:

### 1. Get current browser URL

Add a **Run AppleScript** action with:

```applescript
tell application "Google Chrome"
    return URL of active tab of front window
end tell
```

> For Safari, replace with:
> ```applescript
> tell application "Safari"
>     return URL of front document
> end tell
> ```

Store the result in a variable named `tidalURL`.

### 2. Fetch tag list from muzakily

Add a **Get Contents of URL** action:
- URL: `https://your-server.example.com/api/v1/tags?flat=true`
- Method: GET
- Headers: `Authorization: Bearer YOUR_TOKEN`

Store the result in a variable named `tagsResponse`.

### 3. Extract tag list

Add a **Get Dictionary Value** action:
- Dictionary: `tagsResponse`
- Key: `data`

Store the result in `tagList`.

### 4. Build tag name → ID mapping

Add a **Repeat with Each** action over `tagList`. Inside the loop:
- Add a **Get Dictionary Value** action to get `name` from `Repeat Item`
- Add a **Get Dictionary Value** action to get `id` from `Repeat Item`
- Add a **Set Dictionary Value** action to store `id` into a dictionary named `tagMap` at key `name`
- Add a **Add to Variable** action to add `name` to a list variable named `tagNames`

### 5. Show tag picker

Add a **Choose from List** action:
- List: `tagNames`
- Prompt: "Select tags for this track"
- Enable **Select Multiple**

Store the result in `selectedTagNames`.

### 6. Map names back to IDs

Add a **Repeat with Each** action over `selectedTagNames`. Inside the loop:
- Add a **Get Dictionary Value** action: dictionary = `tagMap`, key = `Repeat Item`
- Add a **Add to Variable** action to add the result to a list named `selectedTagIds`

### 7. POST download request

Add a **Get Contents of URL** action:
- URL: `https://your-server.example.com/api/v1/downloads`
- Method: POST
- Request Body: JSON
  ```json
  {
    "url": "tidalURL",
    "tag_ids": "selectedTagIds"
  }
  ```
- Headers:
  - `Authorization: Bearer YOUR_TOKEN`
  - `Content-Type: application/json`

### 8. Notify

Add a **Show Notification** action:
- Title: `Muzakily`
- Body: `Download queued!`

## Assigning a Keyboard Shortcut

1. In the Shortcuts app, right-click your shortcut → **Edit Details**
2. Click in the **Keyboard Shortcut** field and press your desired key combo
   (e.g. `⌘⌥D`)
3. The shortcut will now be available system-wide whenever the companion is running

## Tips

- If Chrome is not your browser, adapt the AppleScript in step 1
- You can hardcode your token directly in the shortcut or store it in a Text variable
  at the top for easier updates
- The shortcut works on Tidal web player URLs in the format:
  `https://tidal.com/browse/track/XXXXXXX`
