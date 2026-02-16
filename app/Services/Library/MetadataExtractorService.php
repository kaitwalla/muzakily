<?php

declare(strict_types=1);

namespace App\Services\Library;

use getID3;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class MetadataExtractorService
{
    /**
     * Memory threshold (in bytes) below which we use subprocess extraction.
     * Default: 256MB available memory required for in-process extraction.
     */
    private const MEMORY_THRESHOLD = 268435456;

    /**
     * File size threshold (in bytes) above which we always use subprocess.
     * Default: 50MB - large files are more likely to cause memory issues.
     */
    private const LARGE_FILE_THRESHOLD = 52428800;

    /**
     * File extensions that are known to be memory-intensive to parse.
     *
     * @var array<string>
     */
    private const MEMORY_INTENSIVE_FORMATS = ['flac', 'wav', 'aiff', 'aif'];

    /**
     * Create a fresh getID3 instance.
     *
     * We create a new instance per extraction to avoid memory accumulation
     * from internal caches and buffers when processing many files.
     */
    private function createGetID3(): getID3
    {
        $getID3 = new getID3();
        $getID3->option_md5_data = false;
        $getID3->option_md5_data_source = false;
        return $getID3;
    }

    /**
     * Safely extract metadata, falling back to subprocess if needed.
     *
     * This method checks memory conditions and file characteristics to decide
     * whether to extract in-process or via subprocess. If in-process extraction
     * fails, it automatically falls back to subprocess extraction.
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
     *     cover_art: array{data: string, mime_type: string}|null,
     * }|null
     */
    public function safeExtract(string $filePath): ?array
    {
        // Check if we should use subprocess directly
        if ($this->shouldUseSubprocess($filePath)) {
            Log::debug('Using subprocess extraction for memory-intensive file', [
                'file' => basename($filePath),
            ]);
            return $this->extractInSubprocess($filePath);
        }

        // Try in-process extraction first
        try {
            return $this->extract($filePath);
        } catch (\Throwable $e) {
            Log::warning('In-process metadata extraction failed, trying subprocess', [
                'file' => basename($filePath),
                'error' => $e->getMessage(),
            ]);

            return $this->extractInSubprocess($filePath);
        }
    }

    /**
     * Determine if subprocess extraction should be used for this file.
     */
    private function shouldUseSubprocess(string $filePath): bool
    {
        // Check file extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($extension, self::MEMORY_INTENSIVE_FORMATS, true)) {
            return true;
        }

        // Check file size
        $fileSize = @filesize($filePath);
        if ($fileSize !== false && $fileSize > self::LARGE_FILE_THRESHOLD) {
            return true;
        }

        // Check available memory
        $memoryLimit = $this->getMemoryLimitBytes();
        $memoryUsed = memory_get_usage(true);
        $availableMemory = $memoryLimit - $memoryUsed;

        if ($availableMemory < self::MEMORY_THRESHOLD) {
            return true;
        }

        return false;
    }

    /**
     * Get PHP memory limit in bytes.
     */
    private function getMemoryLimitBytes(): int
    {
        $limit = ini_get('memory_limit');

        // Handle empty or unlimited memory
        if ($limit === '' || $limit === '-1') {
            return PHP_INT_MAX;
        }

        $limit = strtolower($limit);
        $value = (int) $limit;

        if (str_ends_with($limit, 'g')) {
            return $value * 1024 * 1024 * 1024;
        }
        if (str_ends_with($limit, 'm')) {
            return $value * 1024 * 1024;
        }
        if (str_ends_with($limit, 'k')) {
            return $value * 1024;
        }

        return $value;
    }

    /**
     * Extract metadata in a subprocess to isolate memory usage.
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
     *     cover_art: null,
     * }|null
     */
    public function extractInSubprocess(string $filePath): ?array
    {
        $result = Process::timeout(120)->run([
            'php',
            base_path('artisan'),
            'internal:extract-metadata',
            $filePath,
        ]);

        if (!$result->successful()) {
            Log::error('Subprocess metadata extraction failed', [
                'file' => basename($filePath),
                'exit_code' => $result->exitCode(),
                'stderr' => $result->errorOutput(),
            ]);
            return null;
        }

        $output = trim($result->output());

        if (empty($output)) {
            return null;
        }

        /** @var array{error?: string}|array{title: string|null, artist: string|null, album: string|null, year: int|null, track: int|null, disc: int|null, genre: string|null, duration: float, bitrate: int|null, lyrics: string|null}|null $data */
        $data = json_decode($output, true);

        if ($data === null || isset($data['error'])) {
            Log::error('Subprocess metadata extraction returned error', [
                'file' => basename($filePath),
                'error' => $data['error'] ?? 'Invalid JSON output',
            ]);
            return null;
        }

        // Subprocess extraction doesn't include cover_art
        $data['cover_art'] = null;

        return $data;
    }

    /**
     * Extract metadata from an audio file.
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
     *     cover_art: array{data: string, mime_type: string}|null,
     * }
     */
    public function extract(string $filePath): array
    {
        $getID3 = $this->createGetID3();
        $info = $getID3->analyze($filePath);

        // Get tags from various sources
        $tags = $this->extractTags($info);

        return [
            'title' => $tags['title'] ?? null,
            'artist' => $tags['artist'] ?? null,
            'album' => $tags['album'] ?? null,
            'year' => $this->extractYear($tags),
            'track' => $this->extractTrack($tags),
            'disc' => $this->extractDisc($tags),
            'genre' => $tags['genre'] ?? null,
            'duration' => (float) ($info['playtime_seconds'] ?? 0),
            'bitrate' => isset($info['audio']['bitrate']) ? (int) $info['audio']['bitrate'] : null,
            'lyrics' => $tags['unsynchronised_lyric'] ?? $tags['lyrics'] ?? null,
            'cover_art' => $this->extractCoverArt($info),
        ];
    }

    /**
     * Extract embedded cover art from the file.
     *
     * @param array<string, mixed> $info
     * @return array{data: string, mime_type: string}|null
     */
    private function extractCoverArt(array $info): ?array
    {
        // Try comments.picture first (most common location)
        if (!empty($info['comments']['picture'])) {
            $picture = $info['comments']['picture'][0];
            if (!empty($picture['data']) && !empty($picture['image_mime'])) {
                return [
                    'data' => $picture['data'],
                    'mime_type' => $picture['image_mime'],
                ];
            }
        }

        // Try ID3v2 APIC frames
        if (!empty($info['id3v2']['APIC'])) {
            foreach ($info['id3v2']['APIC'] as $apic) {
                if (!empty($apic['data']) && !empty($apic['mime'])) {
                    return [
                        'data' => $apic['data'],
                        'mime_type' => $apic['mime'],
                    ];
                }
            }
        }

        // Try Vorbis/FLAC picture
        if (!empty($info['flac']['PICTURE'])) {
            foreach ($info['flac']['PICTURE'] as $picture) {
                if (!empty($picture['data']) && !empty($picture['image_mime'])) {
                    return [
                        'data' => $picture['data'],
                        'mime_type' => $picture['image_mime'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Extract tags from getID3 info.
     *
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    private function extractTags(array $info): array
    {
        $tags = [];

        // Priority: ID3v2 > ID3v1 > Vorbis > QuickTime (M4A/AAC) > APE
        $tagSources = [
            'tags.id3v2' => $info['tags']['id3v2'] ?? [],
            'tags.id3v1' => $info['tags']['id3v1'] ?? [],
            'tags.vorbiscomment' => $info['tags']['vorbiscomment'] ?? [],
            'tags.quicktime' => $info['tags']['quicktime'] ?? [],
            'tags.ape' => $info['tags']['ape'] ?? [],
        ];

        $mappings = [
            'title' => ['title', 'TIT2'],
            'artist' => ['artist', 'TPE1', 'band'],
            'album' => ['album', 'TALB'],
            'year' => ['year', 'date', 'TDRC', 'TYER'],
            'track' => ['track_number', 'tracknumber', 'TRCK', 'track'],
            'disc' => ['disc_number', 'discnumber', 'TPOS', 'part_of_set'],
            'genre' => ['genre', 'TCON'],
            'unsynchronised_lyric' => ['unsynchronised_lyric', 'USLT', 'lyrics'],
        ];

        foreach ($mappings as $key => $possibleKeys) {
            foreach ($tagSources as $source) {
                foreach ($possibleKeys as $possibleKey) {
                    if (isset($source[$possibleKey])) {
                        $value = $source[$possibleKey];
                        $tags[$key] = is_array($value) ? $value[0] : $value;
                        break 2;
                    }
                }
            }
        }

        return $tags;
    }

    /**
     * Extract year as integer.
     *
     * @param array<string, mixed> $tags
     */
    private function extractYear(array $tags): ?int
    {
        $year = $tags['year'] ?? null;

        if ($year === null) {
            return null;
        }

        // Handle "2023-01-01" format
        if (preg_match('/^(\d{4})/', (string) $year, $matches)) {
            return (int) $matches[1];
        }

        return is_numeric($year) ? (int) $year : null;
    }

    /**
     * Extract track number as integer.
     *
     * @param array<string, mixed> $tags
     */
    private function extractTrack(array $tags): ?int
    {
        $track = $tags['track'] ?? null;

        if ($track === null) {
            return null;
        }

        // Handle "5/12" format
        if (preg_match('/^(\d+)/', (string) $track, $matches)) {
            return (int) $matches[1];
        }

        return is_numeric($track) ? (int) $track : null;
    }

    /**
     * Extract disc number as integer.
     *
     * @param array<string, mixed> $tags
     */
    private function extractDisc(array $tags): ?int
    {
        $disc = $tags['disc'] ?? null;

        if ($disc === null) {
            return null;
        }

        // Handle "1/2" format
        if (preg_match('/^(\d+)/', (string) $disc, $matches)) {
            return (int) $matches[1];
        }

        return is_numeric($disc) ? (int) $disc : null;
    }

    /**
     * Extract metadata with duration estimation support for partial files.
     *
     * When extracting from a partial file (header+footer only), the duration
     * may be missing or inaccurate. This method can estimate duration from
     * the bitrate and file size when needed.
     *
     * @param string $filePath Path to the (possibly partial) audio file
     * @param int|null $actualFileSize The actual file size (for estimation)
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
     *     cover_art: array{data: string, mime_type: string}|null,
     *     duration_estimated: bool,
     * }
     */
    public function extractWithEstimation(string $filePath, ?int $actualFileSize = null): array
    {
        $getID3 = $this->createGetID3();
        $info = $getID3->analyze($filePath);

        $tags = $this->extractTags($info);
        $bitrate = isset($info['audio']['bitrate']) ? (int) $info['audio']['bitrate'] : null;
        $duration = (float) ($info['playtime_seconds'] ?? 0);
        $durationEstimated = false;

        // If duration is missing or zero and we have bitrate and file size, estimate it
        if ($duration <= 0 && $bitrate !== null && $bitrate > 0 && $actualFileSize !== null) {
            $duration = $this->estimateDuration($bitrate, $actualFileSize);
            $durationEstimated = true;
        }

        return [
            'title' => $tags['title'] ?? null,
            'artist' => $tags['artist'] ?? null,
            'album' => $tags['album'] ?? null,
            'year' => $this->extractYear($tags),
            'track' => $this->extractTrack($tags),
            'disc' => $this->extractDisc($tags),
            'genre' => $tags['genre'] ?? null,
            'duration' => $duration,
            'bitrate' => $bitrate,
            'lyrics' => $tags['unsynchronised_lyric'] ?? $tags['lyrics'] ?? null,
            'cover_art' => $this->extractCoverArt($info),
            'duration_estimated' => $durationEstimated,
        ];
    }

    /**
     * Estimate duration from bitrate and file size.
     *
     * This is less accurate for VBR files but provides a reasonable estimate
     * when the actual duration cannot be determined from file headers.
     *
     * @param int $bitrate Bitrate in bits per second
     * @param int $fileSize File size in bytes
     * @return float Estimated duration in seconds
     */
    public function estimateDuration(int $bitrate, int $fileSize): float
    {
        if ($bitrate <= 0) {
            return 0.0;
        }

        // Duration = (file_size_in_bits) / bitrate
        // file_size_in_bits = file_size_in_bytes * 8
        return ($fileSize * 8) / $bitrate;
    }
}
