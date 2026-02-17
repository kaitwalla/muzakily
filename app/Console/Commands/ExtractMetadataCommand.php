<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Library\MetadataExtractorService;
use Illuminate\Console\Command;

/**
 * Internal command used for subprocess metadata extraction.
 *
 * This command is called by LibraryScannerService when in-process
 * extraction fails or is likely to cause memory issues.
 */
class ExtractMetadataCommand extends Command
{
    protected $signature = 'internal:extract-metadata
        {file : Path to the audio file}
        {--file-size= : Actual file size for duration estimation}
        {--ffprobe-only : Use ffprobe only, skip getID3 (faster but no tags)}';

    protected $description = 'Extract metadata from an audio file (internal use)';

    protected $hidden = true;

    public function handle(MetadataExtractorService $extractor): int
    {
        /** @var string $filePath */
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->output->writeln((string) json_encode(['error' => 'File not found']));
            return Command::FAILURE;
        }

        try {
            $fileSizeOption = $this->option('file-size');
            $fileSize = null;
            if ($fileSizeOption !== null && $fileSizeOption !== '') {
                $parsedSize = filter_var($fileSizeOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($parsedSize === false) {
                    $this->output->writeln((string) json_encode(['error' => 'Invalid file-size: must be a positive integer']));
                    return Command::FAILURE;
                }
                $fileSize = $parsedSize;
            }

            // If ffprobe-only mode, skip getID3 entirely
            if ($this->option('ffprobe-only')) {
                $metadata = $this->extractWithFfprobeOnly($extractor, $filePath, $fileSize);
            } elseif ($fileSize !== null) {
                $metadata = $extractor->extractWithEstimation($filePath, $fileSize);
                // If getID3 failed to get duration, try ffprobe as fallback
                if ($metadata['duration'] <= 0) {
                    $ffprobeDuration = $extractor->extractDurationWithFfprobe($filePath);
                    if ($ffprobeDuration !== null) {
                        $metadata['duration'] = $ffprobeDuration;
                        $metadata['duration_estimated'] = false;
                    }
                }
            } else {
                $metadata = $extractor->extract($filePath);
                // If getID3 failed to get duration, try ffprobe as fallback
                if ($metadata['duration'] <= 0) {
                    $ffprobeDuration = $extractor->extractDurationWithFfprobe($filePath);
                    if ($ffprobeDuration !== null) {
                        $metadata['duration'] = $ffprobeDuration;
                    }
                }
            }

            // Remove cover_art from output - it's binary data that doesn't serialize well
            // and would be too large to pass via subprocess anyway
            unset($metadata['cover_art']);

            // Use JSON_INVALID_UTF8_SUBSTITUTE to handle non-UTF8 strings in metadata
            $json = json_encode($metadata, JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === false) {
                $this->output->writeln((string) json_encode([
                    'error' => 'Failed to encode metadata: ' . json_last_error_msg(),
                    'type' => 'JsonEncodingException',
                ]));
                return Command::FAILURE;
            }

            $this->output->writeln($json);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->output->writeln((string) json_encode([
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]));
            return Command::FAILURE;
        }
    }

    /**
     * Extract metadata using ffprobe only, skipping getID3.
     *
     * This is useful when getID3 hangs or crashes on certain files.
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
     * }
     */
    private function extractWithFfprobeOnly(MetadataExtractorService $extractor, string $filePath, ?int $fileSize): array
    {
        return $extractor->extractWithFfprobe($filePath, $fileSize);
    }
}
