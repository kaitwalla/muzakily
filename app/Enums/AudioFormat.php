<?php

declare(strict_types=1);

namespace App\Enums;

enum AudioFormat: string
{
    case MP3 = 'mp3';
    case AAC = 'aac';
    case FLAC = 'flac';

    /**
     * Get the MIME type for this audio format.
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::MP3 => 'audio/mpeg',
            self::AAC => 'audio/aac',
            self::FLAC => 'audio/flac',
        };
    }

    /**
     * Get the file extension for this audio format.
     */
    public function extension(): string
    {
        return $this->value;
    }

    /**
     * Check if this format requires transcoding to the target format.
     */
    public function requiresTranscodingTo(self $target): bool
    {
        return $this !== $target;
    }

    /**
     * Get all supported MIME types.
     *
     * @return array<string>
     */
    public static function supportedMimeTypes(): array
    {
        return array_map(
            fn (self $format) => $format->mimeType(),
            self::cases()
        );
    }

    /**
     * Get all supported extensions.
     *
     * @return array<string>
     */
    public static function supportedExtensions(): array
    {
        return array_map(
            fn (self $format) => $format->extension(),
            self::cases()
        );
    }

    /**
     * Create from MIME type.
     */
    public static function fromMimeType(string $mimeType): ?self
    {
        return match ($mimeType) {
            'audio/mpeg', 'audio/mp3' => self::MP3,
            'audio/aac', 'audio/mp4', 'audio/x-m4a' => self::AAC,
            'audio/flac', 'audio/x-flac' => self::FLAC,
            default => null,
        };
    }

    /**
     * Create from file extension.
     */
    public static function fromExtension(string $extension): ?self
    {
        $extension = strtolower(ltrim($extension, '.'));

        return match ($extension) {
            'mp3' => self::MP3,
            'aac', 'm4a' => self::AAC,
            'flac' => self::FLAC,
            default => null,
        };
    }
}
