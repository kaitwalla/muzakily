# Upload API

Upload audio files to the library.

## Upload File

```
POST /api/v1/upload
```

Upload an audio file for processing. The file is queued for metadata extraction and library indexing.

### Request

- **Content-Type**: `multipart/form-data`
- **Max file size**: 100MB
- **Supported formats**: MP3, M4A/AAC, FLAC

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `file` | file | Yes | Audio file to upload |

### Example Request

```bash
curl -X POST "https://api.example.com/api/v1/upload" \
  -H "Authorization: Bearer {token}" \
  -F "file=@/path/to/song.flac"
```

### JavaScript Example

```javascript
const formData = new FormData();
formData.append('file', fileInput.files[0]);

const response = await fetch('/api/v1/upload', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
  },
  body: formData,
});
```

### Success Response (202 Accepted)

```json
{
  "data": {
    "job_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "processing",
    "filename": "song.flac"
  }
}
```

The `202 Accepted` status indicates the file was received and queued for processing. The actual song record is created asynchronously.

## Error Responses

### Unsupported Format (415)

```json
{
  "error": {
    "code": "UNSUPPORTED_FORMAT",
    "message": "The uploaded file format is not supported. Supported formats: mp3, m4a, flac"
  }
}
```

### File Too Large (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "file": ["The file must not be greater than 102400 kilobytes."]
  }
}
```

### Missing File (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "file": ["The file field is required."]
  }
}
```

## Processing Pipeline

After upload, the file goes through these steps:

1. **Upload to R2** - File is stored in Cloudflare R2
2. **Metadata extraction** - ID3/FLAC tags are read
3. **Artist/Album creation** - Missing artists and albums are created
4. **Smart folder assignment** - Song is assigned to appropriate smart folder
5. **Tag auto-assignment** - Tags with matching patterns are applied
6. **Search indexing** - Song is indexed in Meilisearch

## Monitoring Upload Progress

For large files, you may want to show upload progress:

```javascript
const xhr = new XMLHttpRequest();
xhr.open('POST', '/api/v1/upload');
xhr.setRequestHeader('Authorization', `Bearer ${token}`);

xhr.upload.addEventListener('progress', (e) => {
  if (e.lengthComputable) {
    const percent = (e.loaded / e.total) * 100;
    progressBar.style.width = `${percent}%`;
  }
});

xhr.onload = () => {
  if (xhr.status === 202) {
    const result = JSON.parse(xhr.responseText);
    showSuccess(`Uploaded: ${result.data.filename}`);
  }
};

const formData = new FormData();
formData.append('file', file);
xhr.send(formData);
```

## Batch Upload

For multiple files, upload them sequentially or in parallel:

```javascript
async function uploadFiles(files) {
  const results = [];

  for (const file of files) {
    const formData = new FormData();
    formData.append('file', file);

    try {
      const response = await fetch('/api/v1/upload', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: formData,
      });

      if (response.ok) {
        results.push({ file: file.name, status: 'success' });
      } else {
        const error = await response.json();
        results.push({ file: file.name, status: 'error', error });
      }
    } catch (e) {
      results.push({ file: file.name, status: 'error', error: e.message });
    }
  }

  return results;
}
```

## File Naming

Uploaded files are renamed with a unique identifier to prevent conflicts. The original filename is preserved in the song's metadata.

## Permissions

Upload requires authentication. Administrators can configure which users are allowed to upload files through user roles and permissions.
