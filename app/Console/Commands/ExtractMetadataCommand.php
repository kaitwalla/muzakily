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
        {--file-size= : Actual file size for duration estimation}';

    protected $description = 'Extract metadata from an audio file (internal use)';

    protected $hidden = true;

    public function handle(MetadataExtractorService $extractor): int
    {
        /** @var string $filePath */
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->output->writeln(json_encode(['error' => 'File not found']));
            return Command::FAILURE;
        }

        try {
            $fileSizeOption = $this->option('file-size');
            $fileSize = null;
            if ($fileSizeOption !== null && $fileSizeOption !== '') {
                $parsedSize = filter_var($fileSizeOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($parsedSize === false) {
                    $this->output->writeln(json_encode(['error' => 'Invalid file-size: must be a positive integer']));
                    return Command::FAILURE;
                }
                $fileSize = $parsedSize;
            }

            if ($fileSize !== null) {
                $metadata = $extractor->extractWithEstimation($filePath, $fileSize);
            } else {
                $metadata = $extractor->extract($filePath);
            }

            // Remove cover_art from output - it's binary data that doesn't serialize well
            // and would be too large to pass via subprocess anyway
            unset($metadata['cover_art']);

            // Use JSON_INVALID_UTF8_SUBSTITUTE to handle non-UTF8 strings in metadata
            $json = json_encode($metadata, JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === false) {
                $this->output->writeln(json_encode([
                    'error' => 'Failed to encode metadata: ' . json_last_error_msg(),
                    'type' => 'JsonEncodingException',
                ]));
                return Command::FAILURE;
            }

            $this->output->writeln($json);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->output->writeln(json_encode([
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]));
            return Command::FAILURE;
        }
    }
}
