<?php

declare(strict_types=1);

namespace App\Services\Library;

use getID3;

class MetadataExtractorService
{
    private getID3 $getID3;

    public function __construct()
    {
        $this->getID3 = new getID3();
        $this->getID3->option_md5_data = false;
        $this->getID3->option_md5_data_source = false;
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
        $info = $this->getID3->analyze($filePath);

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
        $info = $this->getID3->analyze($filePath);

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
