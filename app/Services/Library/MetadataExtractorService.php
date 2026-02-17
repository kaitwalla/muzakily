<?php

declare(strict_types=1);

namespace App\Services\Library;

use getID3;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class MetadataExtractorService
{
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
        // Always use subprocess extraction during scanning.
        // getID3 can trigger PHP OOM FatalError which can't be caught.
        // Subprocess isolates memory so crashes don't kill the queue worker.
        return $this->extractInSubprocess($filePath);
    }
    /**
     * Safely extract metadata with duration estimation, using subprocess.
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
     *     duration_estimated?: bool,
     * }|null
     */
    public function safeExtractWithEstimation(string $filePath, int $fileSize, bool $ffprobeOnly = false): ?array
    {
        return $this->extractInSubprocess($filePath, $fileSize, $ffprobeOnly);
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
     *     duration_estimated?: bool,
     * }|null
     */
    public function extractInSubprocess(string $filePath, ?int $fileSize = null, bool $ffprobeOnly = false): ?array
    {
        $command = [
            'php',
            base_path('artisan'),
            'internal:extract-metadata',
            $filePath,
        ];

        if ($fileSize !== null) {
            $command[] = '--file-size=' . $fileSize;
        }

        if ($ffprobeOnly) {
            $command[] = '--ffprobe-only';
        }

        $result = Process::timeout(120)->run($command);

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
        // Also check id3v2.comments as fallback - this contains parsed tags even when
        // getID3 can't determine the file format (e.g., with partial file downloads)
        $tagSources = [
            'tags.id3v2' => $info['tags']['id3v2'] ?? [],
            'id3v2.comments' => $info['id3v2']['comments'] ?? [],
            'tags.id3v1' => $info['tags']['id3v1'] ?? [],
            'tags.vorbiscomment' => $info['tags']['vorbiscomment'] ?? [],
            'tags.quicktime' => $info['tags']['quicktime'] ?? [],
            'tags.ape' => $info['tags']['ape'] ?? [],
        ];

        $mappings = [
            'title' => ['title', 'TIT2'],
            'artist' => ['artist', 'TPE1', 'band'],
            'album' => ['album', 'TALB'],
            'year' => ['year', 'date', 'TDRC', 'TYER', 'recording_time'],
            'track' => ['track_number', 'tracknumber', 'TRCK', 'track'],
            'disc' => ['disc_number', 'discnumber', 'TPOS', 'part_of_set', 'part_of_a_set'],
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

    /**
     * Extract duration using ffprobe.
     *
     * This is more reliable than getID3 for certain files and is used
     * as a fallback when getID3 fails to extract duration.
     *
     * @param string $filePath Path to the audio file
     * @return float|null Duration in seconds, or null if extraction failed
     */
    public function extractDurationWithFfprobe(string $filePath): ?float
    {
        $result = Process::timeout(30)->run([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $filePath,
        ]);

        if (!$result->successful()) {
            return null;
        }

        $output = trim($result->output());

        if ($output === '' || $output === 'N/A') {
            return null;
        }

        $duration = (float) $output;

        return $duration > 0 ? $duration : null;
    }

    /**
     * Extract bitrate using ffprobe.
     *
     * @param string $filePath Path to the audio file
     * @return int|null Bitrate in bits per second, or null if extraction failed
     */
    public function extractBitrateWithFfprobe(string $filePath): ?int
    {
        $result = Process::timeout(30)->run([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=bit_rate',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $filePath,
        ]);

        if (!$result->successful()) {
            return null;
        }

        $output = trim($result->output());

        if ($output === '' || $output === 'N/A') {
            return null;
        }

        $bitrate = (int) $output;

        return $bitrate > 0 ? $bitrate : null;
    }

    /**
     * Extract all metadata using ffprobe (duration, bitrate, and tags).
     *
     * This is an alternative to getID3 that works when getID3 hangs.
     *
     * @param string $filePath Path to the audio file
     * @param int|null $fileSize File size for duration estimation fallback
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
     * }
     */
    public function extractWithFfprobe(string $filePath, ?int $fileSize = null): array
    {
        // Get duration and bitrate
        $result = Process::timeout(30)->run([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration,bit_rate:format_tags',
            '-of', 'json',
            $filePath,
        ]);

        $duration = 0.0;
        $bitrate = null;
        $tags = [];

        if ($result->successful()) {
            /** @var array{format?: array{duration?: string, bit_rate?: string, tags?: array<string, string>}}|null $data */
            $data = json_decode($result->output(), true);

            if ($data !== null && isset($data['format'])) {
                $format = $data['format'];

                if (isset($format['duration']) && is_numeric($format['duration'])) {
                    $duration = (float) $format['duration'];
                }

                if (isset($format['bit_rate']) && is_numeric($format['bit_rate'])) {
                    $bitrate = (int) $format['bit_rate'];
                }

                if (isset($format['tags'])) {
                    $tags = $format['tags'];
                }
            }
        }

        // If duration is still 0 and we have bitrate and file size, estimate
        if ($duration <= 0 && $bitrate !== null && $bitrate > 0 && $fileSize !== null) {
            $duration = $this->estimateDuration($bitrate, $fileSize);
        }

        // Extract year from date field
        $year = null;
        $yearSource = $tags['date'] ?? $tags['TDRC'] ?? $tags['TYER'] ?? $tags['year'] ?? null;
        if ($yearSource !== null && preg_match('/^(\d{4})/', $yearSource, $matches)) {
            $year = (int) $matches[1];
        }

        // Extract track number
        $track = null;
        $trackSource = $tags['track'] ?? $tags['TRCK'] ?? null;
        if ($trackSource !== null && preg_match('/^(\d+)/', $trackSource, $matches)) {
            $track = (int) $matches[1];
        }

        // Extract disc number
        $disc = null;
        $discSource = $tags['disc'] ?? $tags['TPOS'] ?? null;
        if ($discSource !== null && preg_match('/^(\d+)/', $discSource, $matches)) {
            $disc = (int) $matches[1];
        }

        return [
            'title' => $tags['title'] ?? $tags['TIT2'] ?? null,
            'artist' => $tags['artist'] ?? $tags['TPE1'] ?? null,
            'album' => $tags['album'] ?? $tags['TALB'] ?? null,
            'year' => $year,
            'track' => $track,
            'disc' => $disc,
            'genre' => $tags['genre'] ?? $tags['TCON'] ?? null,
            'duration' => $duration,
            'bitrate' => $bitrate,
            'lyrics' => $tags['lyrics'] ?? $tags['USLT'] ?? null,
            'cover_art' => null, // ffprobe doesn't easily extract cover art
        ];
    }
}
