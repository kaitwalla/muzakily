<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\MusicStorageInterface;
use App\Enums\AudioFormat;
use App\Models\Artist;
use App\Models\ScanCache;
use App\Models\SmartFolder;
use App\Models\Song;
use App\Services\Library\CoverArtService;
use App\Services\Library\LibraryScannerService;
use App\Services\Library\MetadataExtractorService;
use App\Services\Library\SmartFolderService;
use App\Services\Library\TagService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LibraryScannerServiceTest extends TestCase
{
    use RefreshDatabase;

    private MusicStorageInterface&MockInterface $storageMock;
    private MetadataExtractorService&MockInterface $metadataExtractorMock;
    private SmartFolderService&MockInterface $smartFolderServiceMock;
    private TagService&MockInterface $tagServiceMock;
    private CoverArtService&MockInterface $coverArtServiceMock;
    private LibraryScannerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the queue to prevent actual job dispatch during tests
        Queue::fake();

        // Set up required config for R2 by default
        config([
            'filesystems.disks.r2.bucket' => 'test-bucket',
            'muzakily.storage.driver' => 'r2',
            'muzakily.scanning.extensions' => ['mp3', 'aac', 'm4a', 'flac'],
            'muzakily.tags.auto_create_from_folders' => true,
        ]);

        $this->storageMock = Mockery::mock(MusicStorageInterface::class);
        $this->metadataExtractorMock = Mockery::mock(MetadataExtractorService::class);
        $this->smartFolderServiceMock = Mockery::mock(SmartFolderService::class);
        $this->tagServiceMock = Mockery::mock(TagService::class);
        $this->coverArtServiceMock = Mockery::mock(CoverArtService::class);

        $this->service = new LibraryScannerService(
            $this->storageMock,
            $this->metadataExtractorMock,
            $this->smartFolderServiceMock,
            $this->tagServiceMock,
            $this->coverArtServiceMock
        );
    }

    // ==========================================
    // R2 (Remote Storage) Tests
    // ==========================================

    #[Test]
    public function r2_scan_uses_partial_download_for_metadata_extraction(): void
    {
        $objectKey = 'music/test.mp3';
        $fileSize = 10_000_000;
        $etag = 'abc123';

        $this->storageMock
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

        // R2 storage returns null for getLocalPath (remote storage)
        $this->storageMock
            ->shouldReceive('getLocalPath')
            ->once()
            ->with($objectKey)
            ->andReturn(null);

        // Mock partial download (the key optimization for R2)
        $this->storageMock
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
        $this->storageMock
            ->shouldReceive('createPartialTempFile')
            ->once()
            ->with('header_content', 'footer_content', $fileSize)
            ->andReturn($tempPath);

        // Mock metadata extraction
        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->with($tempPath)
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

        // Mock smart folder assignment
        $smartFolder = SmartFolder::factory()->create();
        $this->smartFolderServiceMock
            ->shouldReceive('assignFromPath')
            ->once()
            ->with($objectKey)
            ->andReturn($smartFolder);

        $this->tagServiceMock
            ->shouldReceive('assignFromPath')
            ->once()
            ->with(Mockery::type(Song::class));

        $this->service->scan();

        $this->assertDatabaseHas('songs', [
            'title' => 'Test Song',
            'storage_path' => $objectKey,
        ]);

        @unlink($tempPath);
    }

    #[Test]
    public function r2_scan_falls_back_to_full_download_when_partial_fails(): void
    {
        $objectKey = 'music/test.mp3';
        $fileSize = 10_000_000;
        $etag = 'abc123';

        $this->storageMock
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

        // R2 storage returns null for getLocalPath
        $this->storageMock
            ->shouldReceive('getLocalPath')
            ->once()
            ->with($objectKey)
            ->andReturn(null);

        // Partial download fails
        $this->storageMock
            ->shouldReceive('downloadPartial')
            ->once()
            ->andReturn(null);

        // Fall back to full download
        $this->storageMock
            ->shouldReceive('download')
            ->once()
            ->andReturnUsing(function ($key, $path) {
                file_put_contents($path, 'full_file_content');
                return true;
            });

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
    public function r2_scan_falls_back_to_full_download_when_duration_is_zero(): void
    {
        $objectKey = 'music/test.mp3';
        $fileSize = 10_000_000;
        $etag = 'abc123';

        $this->storageMock
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

        // R2 storage returns null for getLocalPath
        $this->storageMock
            ->shouldReceive('getLocalPath')
            ->once()
            ->with($objectKey)
            ->andReturn(null);

        // Partial download succeeds
        $this->storageMock
            ->shouldReceive('downloadPartial')
            ->once()
            ->andReturn([
                'header' => 'header_content',
                'footer' => 'footer_content',
                'file_size' => $fileSize,
            ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_');
        $this->storageMock
            ->shouldReceive('createPartialTempFile')
            ->once()
            ->andReturn($tempPath);

        // First extraction returns zero duration
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
                'duration' => 0.0,
                'bitrate' => null,
                'lyrics' => null,
            ]);

        // Fall back to full download
        $this->storageMock
            ->shouldReceive('download')
            ->once()
            ->andReturnUsing(function ($key, $path) {
                file_put_contents($path, 'full_file_content');
                return true;
            });

        // Second extraction gets proper duration
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

    // ==========================================
    // Local Storage Tests
    // ==========================================

    #[Test]
    public function local_scan_reads_file_directly_without_download(): void
    {
        config(['muzakily.storage.driver' => 'local']);

        $objectKey = 'music/test.mp3';
        $fileSize = 10_000_000;
        $etag = 'abc123';

        // Create a real temp file to simulate local storage
        $localPath = tempnam(sys_get_temp_dir(), 'local_music_');
        $this->assertNotFalse($localPath, 'Failed to create temp file');
        file_put_contents($localPath, 'fake_audio_content');

        $this->storageMock
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

        // Local storage returns the file path directly
        $this->storageMock
            ->shouldReceive('getLocalPath')
            ->once()
            ->with($objectKey)
            ->andReturn($localPath);

        // downloadPartial should NOT be called for local storage
        $this->storageMock
            ->shouldNotReceive('downloadPartial');

        // download should NOT be called for local storage
        $this->storageMock
            ->shouldNotReceive('download');

        // Metadata extraction uses the local path directly
        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->with($localPath)
            ->andReturn([
                'title' => 'Local Song',
                'artist' => 'Local Artist',
                'album' => 'Local Album',
                'year' => 2024,
                'track' => 1,
                'disc' => 1,
                'genre' => 'Pop',
                'duration' => 200.0,
                'bitrate' => 256000,
                'lyrics' => null,
            ]);

        $smartFolder = SmartFolder::factory()->create();
        $this->smartFolderServiceMock
            ->shouldReceive('assignFromPath')
            ->once()
            ->with($objectKey)
            ->andReturn($smartFolder);

        $this->tagServiceMock
            ->shouldReceive('assignFromPath')
            ->once()
            ->with(Mockery::type(Song::class));

        $this->service->scan();

        $this->assertDatabaseHas('songs', [
            'title' => 'Local Song',
            'artist_name' => 'Local Artist',
            'album_name' => 'Local Album',
            'length' => 200.0,
            'storage_path' => $objectKey,
        ]);

        @unlink($localPath);
    }

    #[Test]
    public function local_scan_handles_multiple_files(): void
    {
        config(['muzakily.storage.driver' => 'local']);

        // Create temp files
        $file1Path = tempnam(sys_get_temp_dir(), 'local_music_1_');
        $file2Path = tempnam(sys_get_temp_dir(), 'local_music_2_');
        file_put_contents($file1Path, 'audio1');
        file_put_contents($file2Path, 'audio2');

        $this->storageMock
            ->shouldReceive('listObjects')
            ->once()
            ->andReturn($this->createGenerator([
                [
                    'key' => 'music/song1.mp3',
                    'size' => 5_000_000,
                    'last_modified' => new DateTimeImmutable(),
                    'etag' => 'etag1',
                ],
                [
                    'key' => 'music/song2.mp3',
                    'size' => 6_000_000,
                    'last_modified' => new DateTimeImmutable(),
                    'etag' => 'etag2',
                ],
            ]));

        $this->storageMock
            ->shouldReceive('getLocalPath')
            ->with('music/song1.mp3')
            ->andReturn($file1Path);

        $this->storageMock
            ->shouldReceive('getLocalPath')
            ->with('music/song2.mp3')
            ->andReturn($file2Path);

        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->with($file1Path)
            ->andReturn([
                'title' => 'Song One',
                'artist' => 'Artist A',
                'album' => null,
                'year' => null,
                'track' => null,
                'disc' => null,
                'genre' => null,
                'duration' => 120.0,
                'bitrate' => null,
                'lyrics' => null,
            ]);

        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->with($file2Path)
            ->andReturn([
                'title' => 'Song Two',
                'artist' => 'Artist B',
                'album' => null,
                'year' => null,
                'track' => null,
                'disc' => null,
                'genre' => null,
                'duration' => 150.0,
                'bitrate' => null,
                'lyrics' => null,
            ]);

        $smartFolder = SmartFolder::factory()->create();
        $this->smartFolderServiceMock
            ->shouldReceive('assignFromPath')
            ->twice()
            ->andReturn($smartFolder);

        $this->tagServiceMock
            ->shouldReceive('assignFromPath')
            ->twice();

        $this->service->scan();

        $this->assertDatabaseHas('songs', ['title' => 'Song One']);
        $this->assertDatabaseHas('songs', ['title' => 'Song Two']);
        $this->assertDatabaseCount('songs', 2);

        @unlink($file1Path);
        @unlink($file2Path);
    }

    // ==========================================
    // Common Tests (apply to both storage types)
    // ==========================================

    #[Test]
    public function scan_prunes_orphaned_songs(): void
    {
        $orphanSong = Song::factory()->create([
            'storage_path' => 'music/orphan.mp3',
        ]);

        $orphanCache = ScanCache::create([
            'bucket' => 'test-bucket',
            'object_key' => 'music/orphan.mp3',
            'key_hash' => ScanCache::hashKey('music/orphan.mp3'),
            'etag' => 'old-etag',
            'size' => 1000,
            'last_scanned_at' => now()->subHours(1),
        ]);

        $this->storageMock
            ->shouldReceive('listObjects')
            ->once()
            ->andReturn($this->createGenerator([]));

        $this->service->scan();

        $this->assertDatabaseMissing('songs', [
            'id' => $orphanSong->id,
        ]);

        $this->assertDatabaseMissing('scan_cache', [
            'id' => $orphanCache->id,
        ]);
    }

    #[Test]
    public function scan_keeps_songs_that_still_exist(): void
    {
        $objectKey = 'music/existing.mp3';
        $fileSize = 10_000_000;
        $etag = 'current-etag';

        $existingCache = ScanCache::create([
            'bucket' => 'test-bucket',
            'object_key' => $objectKey,
            'key_hash' => ScanCache::hashKey($objectKey),
            'etag' => $etag,
            'size' => $fileSize,
            'last_scanned_at' => now()->subHours(1),
        ]);

        $this->storageMock
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

        // File hasn't changed, so no extraction needed
        $this->service->scan();

        $this->assertDatabaseHas('scan_cache', [
            'object_key' => $objectKey,
        ]);
    }

    #[Test]
    public function scan_respects_limit_parameter(): void
    {
        config(['muzakily.storage.driver' => 'local']);

        $file1Path = tempnam(sys_get_temp_dir(), 'limit_test_1_');
        $file2Path = tempnam(sys_get_temp_dir(), 'limit_test_2_');
        $file3Path = tempnam(sys_get_temp_dir(), 'limit_test_3_');
        file_put_contents($file1Path, 'audio');
        file_put_contents($file2Path, 'audio');
        file_put_contents($file3Path, 'audio');

        $this->storageMock
            ->shouldReceive('listObjects')
            ->once()
            ->andReturn($this->createGenerator([
                ['key' => 'music/song1.mp3', 'size' => 1000, 'last_modified' => new DateTimeImmutable(), 'etag' => 'e1'],
                ['key' => 'music/song2.mp3', 'size' => 1000, 'last_modified' => new DateTimeImmutable(), 'etag' => 'e2'],
                ['key' => 'music/song3.mp3', 'size' => 1000, 'last_modified' => new DateTimeImmutable(), 'etag' => 'e3'],
            ]));

        $this->storageMock->shouldReceive('getLocalPath')->with('music/song1.mp3')->andReturn($file1Path);
        $this->storageMock->shouldReceive('getLocalPath')->with('music/song2.mp3')->andReturn($file2Path);
        // song3 should not be processed due to limit

        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->twice() // Only 2 files should be processed
            ->andReturn([
                'title' => 'Test',
                'artist' => null,
                'album' => null,
                'year' => null,
                'track' => null,
                'disc' => null,
                'genre' => null,
                'duration' => 100.0,
                'bitrate' => null,
                'lyrics' => null,
            ]);

        $smartFolder = SmartFolder::factory()->create();
        $this->smartFolderServiceMock->shouldReceive('assignFromPath')->twice()->andReturn($smartFolder);
        $this->tagServiceMock->shouldReceive('assignFromPath')->twice();

        $this->service->scan(force: false, limit: 2);

        $this->assertDatabaseCount('songs', 2);

        @unlink($file1Path);
        @unlink($file2Path);
        @unlink($file3Path);
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
