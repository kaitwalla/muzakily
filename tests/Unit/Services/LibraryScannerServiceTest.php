<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AudioFormat;
use App\Models\Artist;
use App\Models\ScanCache;
use App\Models\SmartFolder;
use App\Models\Song;
use App\Services\Library\LibraryScannerService;
use App\Services\Library\MetadataExtractorService;
use App\Services\Library\SmartFolderService;
use App\Services\Library\TagService;
use App\Services\Storage\R2StorageService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LibraryScannerServiceTest extends TestCase
{
    use RefreshDatabase;

    private R2StorageService&MockInterface $r2StorageMock;
    private MetadataExtractorService&MockInterface $metadataExtractorMock;
    private SmartFolderService&MockInterface $smartFolderServiceMock;
    private TagService&MockInterface $tagServiceMock;
    private LibraryScannerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up required config
        config([
            'filesystems.disks.r2.bucket' => 'test-bucket',
            'muzakily.scanning.extensions' => ['mp3', 'aac', 'm4a', 'flac'],
            'muzakily.tags.auto_create_from_folders' => true,
        ]);

        $this->r2StorageMock = Mockery::mock(R2StorageService::class);
        $this->metadataExtractorMock = Mockery::mock(MetadataExtractorService::class);
        $this->smartFolderServiceMock = Mockery::mock(SmartFolderService::class);
        $this->tagServiceMock = Mockery::mock(TagService::class);

        $this->service = new LibraryScannerService(
            $this->r2StorageMock,
            $this->metadataExtractorMock,
            $this->smartFolderServiceMock,
            $this->tagServiceMock
        );
    }

    #[Test]
    public function scan_uses_partial_download_for_metadata_extraction(): void
    {
        $objectKey = 'music/test.mp3';
        $fileSize = 10_000_000;
        $etag = 'abc123';

        // Mock listObjects to return one file
        $this->r2StorageMock
            ->shouldReceive('listObjects')
            ->once()
            ->andReturn($this->createGenerator([
                [
                    'key' => $objectKey,
                    'size' => $fileSize,
                    'last_modified' => new DateTimeImmutable(),
                    'etag' => $etag,
                ],
            ]));

        // Mock partial download (the key optimization)
        $this->r2StorageMock
            ->shouldReceive('downloadPartial')
            ->once()
            ->with($objectKey)
            ->andReturn([
                'header' => 'header_content',
                'footer' => 'footer_content',
                'file_size' => $fileSize,
            ]);

        // Mock createPartialTempFile
        $tempPath = tempnam(sys_get_temp_dir(), 'test_');
        $this->assertNotFalse($tempPath, 'Failed to create temp file');
        $this->r2StorageMock
            ->shouldReceive('createPartialTempFile')
            ->once()
            ->with('header_content', 'footer_content', $fileSize)
            ->andReturn($tempPath);

        // Mock metadata extraction with estimation
        $this->metadataExtractorMock
            ->shouldReceive('extractWithEstimation')
            ->once()
            ->with($tempPath, $fileSize)
            ->andReturn([
                'title' => 'Test Song',
                'artist' => 'Test Artist',
                'album' => 'Test Album',
                'year' => 2024,
                'track' => 1,
                'disc' => 1,
                'genre' => 'Rock',
                'duration' => 180.0,
                'bitrate' => 320000,
                'lyrics' => null,
                'duration_estimated' => false,
            ]);

        // Mock smart folder assignment
        $smartFolder = SmartFolder::factory()->create();
        $this->smartFolderServiceMock
            ->shouldReceive('assignFromPath')
            ->once()
            ->with($objectKey)
            ->andReturn($smartFolder);

        // Mock tag assignment - use Mockery::any() for the Song argument
        $this->tagServiceMock
            ->shouldReceive('assignFromPath')
            ->once()
            ->with(Mockery::type(Song::class));

        $this->service->scan();

        // Verify song was created
        $this->assertDatabaseHas('songs', [
            'title' => 'Test Song',
            'storage_path' => $objectKey,
        ]);

        // Clean up
        @unlink($tempPath);
    }

    #[Test]
    public function scan_falls_back_to_full_download_when_partial_fails(): void
    {
        $objectKey = 'music/test.mp3';
        $fileSize = 10_000_000;
        $etag = 'abc123';

        $this->r2StorageMock
            ->shouldReceive('listObjects')
            ->once()
            ->andReturn($this->createGenerator([
                [
                    'key' => $objectKey,
                    'size' => $fileSize,
                    'last_modified' => new DateTimeImmutable(),
                    'etag' => $etag,
                ],
            ]));

        // Partial download fails
        $this->r2StorageMock
            ->shouldReceive('downloadPartial')
            ->once()
            ->andReturn(null);

        // Fall back to full download
        $this->r2StorageMock
            ->shouldReceive('download')
            ->once()
            ->andReturnUsing(function ($key, $path) {
                file_put_contents($path, 'full_file_content');
                return true;
            });

        // Mock metadata extraction (regular extract, not with estimation)
        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn([
                'title' => 'Test Song',
                'artist' => 'Test Artist',
                'album' => 'Test Album',
                'year' => 2024,
                'track' => 1,
                'disc' => 1,
                'genre' => 'Rock',
                'duration' => 180.0,
                'bitrate' => 320000,
                'lyrics' => null,
            ]);

        $smartFolder = SmartFolder::factory()->create();
        $this->smartFolderServiceMock
            ->shouldReceive('assignFromPath')
            ->once()
            ->andReturn($smartFolder);

        $this->tagServiceMock
            ->shouldReceive('assignFromPath')
            ->once();

        $this->service->scan();

        $this->assertDatabaseHas('songs', [
            'title' => 'Test Song',
            'storage_path' => $objectKey,
        ]);
    }

    #[Test]
    public function scan_falls_back_to_full_download_when_duration_is_zero(): void
    {
        $objectKey = 'music/test.mp3';
        $fileSize = 10_000_000;
        $etag = 'abc123';

        $this->r2StorageMock
            ->shouldReceive('listObjects')
            ->once()
            ->andReturn($this->createGenerator([
                [
                    'key' => $objectKey,
                    'size' => $fileSize,
                    'last_modified' => new DateTimeImmutable(),
                    'etag' => $etag,
                ],
            ]));

        // Partial download succeeds
        $this->r2StorageMock
            ->shouldReceive('downloadPartial')
            ->once()
            ->andReturn([
                'header' => 'header_content',
                'footer' => 'footer_content',
                'file_size' => $fileSize,
            ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_');
        $this->r2StorageMock
            ->shouldReceive('createPartialTempFile')
            ->once()
            ->andReturn($tempPath);

        // Metadata extraction returns zero duration (can't determine from partial)
        $this->metadataExtractorMock
            ->shouldReceive('extractWithEstimation')
            ->once()
            ->andReturn([
                'title' => 'Test Song',
                'artist' => 'Test Artist',
                'album' => null,
                'year' => null,
                'track' => null,
                'disc' => null,
                'genre' => null,
                'duration' => 0.0, // Zero duration
                'bitrate' => null, // No bitrate to estimate from
                'lyrics' => null,
                'duration_estimated' => false,
            ]);

        // Fall back to full download
        $this->r2StorageMock
            ->shouldReceive('download')
            ->once()
            ->andReturnUsing(function ($key, $path) {
                file_put_contents($path, 'full_file_content');
                return true;
            });

        // Full extraction gets proper duration
        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn([
                'title' => 'Test Song',
                'artist' => 'Test Artist',
                'album' => null,
                'year' => null,
                'track' => null,
                'disc' => null,
                'genre' => null,
                'duration' => 180.0,
                'bitrate' => 320000,
                'lyrics' => null,
            ]);

        $smartFolder = SmartFolder::factory()->create();
        $this->smartFolderServiceMock
            ->shouldReceive('assignFromPath')
            ->once()
            ->andReturn($smartFolder);

        $this->tagServiceMock
            ->shouldReceive('assignFromPath')
            ->once();

        $this->service->scan();

        $this->assertDatabaseHas('songs', [
            'title' => 'Test Song',
            'length' => 180.0,
        ]);

        @unlink($tempPath);
    }

    #[Test]
    public function scan_prunes_orphaned_songs(): void
    {
        // Create an existing song and cache entry that won't be seen during scan
        $orphanSong = Song::factory()->create([
            'storage_path' => 'music/orphan.mp3',
        ]);

        $orphanCache = \App\Models\ScanCache::create([
            'bucket' => 'test-bucket',
            'object_key' => 'music/orphan.mp3',
            'key_hash' => \App\Models\ScanCache::hashKey('music/orphan.mp3'),
            'etag' => 'old-etag',
            'size' => 1000,
            'last_scanned_at' => now()->subHours(1),
        ]);

        // Mock listObjects to return empty (no files in R2)
        $this->r2StorageMock
            ->shouldReceive('listObjects')
            ->once()
            ->andReturn($this->createGenerator([]));

        $this->service->scan();

        // Verify orphan was removed
        $this->assertDatabaseMissing('songs', [
            'id' => $orphanSong->id,
        ]);

        $this->assertDatabaseMissing('scan_cache', [
            'id' => $orphanCache->id,
        ]);
    }

    #[Test]
    public function scan_keeps_songs_that_exist_in_r2(): void
    {
        $objectKey = 'music/existing.mp3';
        $fileSize = 10_000_000;
        $etag = 'current-etag';

        // Create existing cache entry
        $existingCache = \App\Models\ScanCache::create([
            'bucket' => 'test-bucket',
            'object_key' => $objectKey,
            'key_hash' => \App\Models\ScanCache::hashKey($objectKey),
            'etag' => $etag,
            'size' => $fileSize,
            'last_scanned_at' => now()->subHours(1),
        ]);

        // Mock listObjects to return the file (still exists in R2)
        $this->r2StorageMock
            ->shouldReceive('listObjects')
            ->once()
            ->andReturn($this->createGenerator([
                [
                    'key' => $objectKey,
                    'size' => $fileSize,
                    'last_modified' => new DateTimeImmutable(),
                    'etag' => $etag,
                ],
            ]));

        // File hasn't changed, so no download needed
        // The cache entry will be marked as scanned

        $this->service->scan();

        // Verify cache entry still exists and was updated
        $this->assertDatabaseHas('scan_cache', [
            'object_key' => $objectKey,
        ]);
    }

    /**
     * @param array<array{key: string, size: int, last_modified: \DateTimeInterface, etag: string}> $items
     * @return \Generator<array{key: string, size: int, last_modified: \DateTimeInterface, etag: string}>
     */
    private function createGenerator(array $items): \Generator
    {
        foreach ($items as $item) {
            yield $item;
        }
    }
}
