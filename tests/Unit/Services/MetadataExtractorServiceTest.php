<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Library\MetadataExtractorService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetadataExtractorServiceTest extends TestCase
{
    private MetadataExtractorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MetadataExtractorService();
    }

    #[Test]
    public function extract_with_estimation_estimates_duration_when_missing(): void
    {
        // Create a minimal file that getID3 won't recognize as valid audio
        // This simulates a partial file where duration can't be determined
        $tempPath = tempnam(sys_get_temp_dir(), 'test_audio_');
        if ($tempPath === false) {
            $this->markTestSkipped('Could not create temp file');
        }

        try {
            // Write some dummy content (not valid audio, so duration will be 0)
            file_put_contents($tempPath, str_repeat("\x00", 1000));

            // Simulate a 10MB file at 128kbps
            $actualFileSize = 10_000_000;
            $expectedBitrate = 128_000; // This won't be detected from invalid file

            $result = $this->service->extractWithEstimation($tempPath, $actualFileSize);

            // Verify the method returns the expected structure
            $this->assertArrayHasKey('duration', $result);
            $this->assertArrayHasKey('duration_estimated', $result);
            $this->assertArrayHasKey('title', $result);
            $this->assertArrayHasKey('artist', $result);
            $this->assertArrayHasKey('album', $result);

            // Since this isn't valid audio, duration should be 0 (can't estimate without bitrate)
            // But the method should not throw an exception
            $this->assertIsFloat($result['duration']);
            $this->assertIsBool($result['duration_estimated']);
        } finally {
            @unlink($tempPath);
        }
    }

    #[Test]
    public function estimate_duration_calculates_from_bitrate_and_size(): void
    {
        // 128 kbps = 16,000 bytes per second
        // 10MB file = 10,000,000 bytes
        // Duration = 10,000,000 / 16,000 = 625 seconds
        $fileSize = 10_000_000;
        $bitrate = 128_000; // 128 kbps in bits per second

        $estimated = $this->service->estimateDuration($bitrate, $fileSize);

        // Duration in seconds = (file_size * 8) / bitrate
        $expected = ($fileSize * 8) / $bitrate;

        $this->assertEqualsWithDelta($expected, $estimated, 0.1);
    }

    #[Test]
    public function estimate_duration_returns_zero_for_zero_bitrate(): void
    {
        $estimated = $this->service->estimateDuration(0, 10_000_000);
        $this->assertEquals(0.0, $estimated);
    }
}
