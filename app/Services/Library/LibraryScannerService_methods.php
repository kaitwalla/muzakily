    /**
     * Extract metadata using partial download (header + footer only).
     *
     * This is more efficient for large files as it only downloads the parts
     * of the file that contain metadata, typically ~640KB instead of the full file.
     *
     * @param array{key: string, size: int, last_modified: \DateTimeInterface, etag: string} $object
     * @return array{
     *     title: string|null,
     *     artist: string|null,
     *     album: string|null,
     *     year: int|null,
     *     track: int|null,
     *     disc: int|null,
     *     genre: string|null,
     *     duration: float,
     *     bitrate: int|null,
     *     lyrics: string|null,
     * }|null
     */
    private function extractMetadataWithPartialDownload(array $object): ?array
    {
        $partial = $this->r2Storage->downloadPartial($object['key']);

        if ($partial === null) {
            return null;
        }

        $tempPath = $this->r2Storage->createPartialTempFile(
            $partial['header'],
            $partial['footer'],
            $partial['file_size']
        );

        if ($tempPath === false) {
            return null;
        }

        try {
            // We use the regular extract method because createPartialTempFile makes a valid-looking file
            return $this->metadataExtractor->extract($tempPath);
        } catch (\Throwable $e) {
            // If extraction fails on partial file, return null to trigger fallback
            return null;
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Extract metadata using full file download.
     *
     * This is used as a fallback when partial download fails or doesn't
     * provide complete metadata (e.g., missing duration).
     *
     * @return array{
     *     title: string|null,
     *     artist: string|null,
     *     album: string|null,
     *     year: int|null,
     *     track: int|null,
     *     disc: int|null,
     *     genre: string|null,
     *     duration: float,
     *     bitrate: int|null,
     *     lyrics: string|null,
     * }|null
     */
    private function extractMetadataWithFullDownload(string $key): ?array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'muzakily_scan_');

        if ($tempPath === false) {
            throw new \RuntimeException('Failed to create temporary file for metadata extraction');
        }

        try {
            if (!$this->r2Storage->download($key, $tempPath)) {
                return null;
            }

            return $this->metadataExtractor->extract($tempPath);
        } finally {
            @unlink($tempPath);
        }
    }
}
