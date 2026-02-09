<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\AudioFormat;
use PHPUnit\Framework\TestCase;

class AudioFormatTest extends TestCase
{
    public function test_from_extension_returns_correct_format(): void
    {
        $this->assertSame(AudioFormat::MP3, AudioFormat::fromExtension('mp3'));
        $this->assertSame(AudioFormat::AAC, AudioFormat::fromExtension('aac'));
        $this->assertSame(AudioFormat::AAC, AudioFormat::fromExtension('m4a'));
        $this->assertSame(AudioFormat::FLAC, AudioFormat::fromExtension('flac'));
    }

    public function test_from_extension_is_case_insensitive(): void
    {
        $this->assertSame(AudioFormat::MP3, AudioFormat::fromExtension('MP3'));
        $this->assertSame(AudioFormat::FLAC, AudioFormat::fromExtension('FLAC'));
    }

    public function test_from_extension_returns_null_for_unknown(): void
    {
        $this->assertNull(AudioFormat::fromExtension('wav'));
        $this->assertNull(AudioFormat::fromExtension('ogg'));
    }

    public function test_mime_type_returns_correct_value(): void
    {
        $this->assertSame('audio/mpeg', AudioFormat::MP3->mimeType());
        $this->assertSame('audio/aac', AudioFormat::AAC->mimeType());
        $this->assertSame('audio/flac', AudioFormat::FLAC->mimeType());
    }
}
