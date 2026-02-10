<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Storage\R2StorageService;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3Client;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class R2StorageServiceTest extends TestCase
{
    private S3Client&MockInterface $s3ClientMock;
    private R2StorageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->s3ClientMock = Mockery::mock(S3Client::class);

        // Create service and inject mock client
        $this->service = new R2StorageService();
        $reflection = new ReflectionClass($this->service);
        $property = $reflection->getProperty('client');
        $property->setValue($this->service, $this->s3ClientMock);
    }

    #[Test]
    public function download_partial_fetches_header_and_footer_with_range_requests(): void
    {
        $key = 'music/test.mp3';
        $fileSize = 10_000_000; // 10MB
        $headerSize = 524288; // 512KB
        $footerSize = 131072; // 128KB

        // Mock headObject to return file size
        $this->s3ClientMock
            ->shouldReceive('headObject')
            ->once()
            ->with(Mockery::on(function ($args) use ($key) {
                return $args['Key'] === $key;
            }))
            ->andReturn(new Result([
                'ContentLength' => $fileSize,
                'ETag' => '"abc123"',
            ]));

        // Mock getObject for header range (first 512KB)
        $headerContent = str_repeat('H', $headerSize);
        $this->s3ClientMock
            ->shouldReceive('getObject')
            ->once()
            ->with(Mockery::on(function ($args) use ($key, $headerSize) {
                return $args['Key'] === $key
                    && $args['Range'] === 'bytes=0-' . ($headerSize - 1);
            }))
            ->andReturn(new Result([
                'Body' => $headerContent,
            ]));

        // Mock getObject for footer range (last 128KB)
        $footerContent = str_repeat('F', $footerSize);
        $footerStart = $fileSize - $footerSize;
        $this->s3ClientMock
            ->shouldReceive('getObject')
            ->once()
            ->with(Mockery::on(function ($args) use ($key, $footerStart, $fileSize) {
                return $args['Key'] === $key
                    && $args['Range'] === 'bytes=' . $footerStart . '-' . ($fileSize - 1);
            }))
            ->andReturn(new Result([
                'Body' => $footerContent,
            ]));

        $result = $this->service->downloadPartial($key, $headerSize, $footerSize);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('header', $result);
        $this->assertArrayHasKey('footer', $result);
        $this->assertArrayHasKey('file_size', $result);
        $this->assertEquals($headerContent, $result['header']);
        $this->assertEquals($footerContent, $result['footer']);
        $this->assertEquals($fileSize, $result['file_size']);
    }

    #[Test]
    public function download_partial_downloads_full_file_when_smaller_than_header_plus_footer(): void
    {
        $key = 'music/small.mp3';
        $fileSize = 100_000; // 100KB - smaller than header+footer
        $headerSize = 524288;
        $footerSize = 131072;
        $fileContent = str_repeat('S', $fileSize);

        // Mock headObject to return file size
        $this->s3ClientMock
            ->shouldReceive('headObject')
            ->once()
            ->with(Mockery::on(function ($args) use ($key) {
                return $args['Key'] === $key;
            }))
            ->andReturn(new Result([
                'ContentLength' => $fileSize,
                'ETag' => '"abc123"',
            ]));

        // Mock getObject for full file (no Range header when file is small)
        $this->s3ClientMock
            ->shouldReceive('getObject')
            ->once()
            ->with(Mockery::on(function ($args) use ($key) {
                return $args['Key'] === $key
                    && !isset($args['Range']);
            }))
            ->andReturn(new Result([
                'Body' => $fileContent,
            ]));

        $result = $this->service->downloadPartial($key, $headerSize, $footerSize);

        $this->assertNotNull($result);
        $this->assertEquals($fileContent, $result['header']);
        $this->assertEquals('', $result['footer']);
        $this->assertEquals($fileSize, $result['file_size']);
    }

    #[Test]
    public function download_partial_returns_null_on_error(): void
    {
        $key = 'music/nonexistent.mp3';

        $this->s3ClientMock
            ->shouldReceive('headObject')
            ->once()
            ->andThrow(new \Exception('Object not found'));

        $result = $this->service->downloadPartial($key);

        $this->assertNull($result);
    }

    #[Test]
    public function download_partial_uses_default_sizes(): void
    {
        $key = 'music/test.mp3';
        $fileSize = 10_000_000;
        $defaultHeaderSize = 524288; // 512KB
        $defaultFooterSize = 131072; // 128KB

        $this->s3ClientMock
            ->shouldReceive('headObject')
            ->once()
            ->andReturn(new Result([
                'ContentLength' => $fileSize,
            ]));

        $this->s3ClientMock
            ->shouldReceive('getObject')
            ->once()
            ->with(Mockery::on(function ($args) use ($defaultHeaderSize) {
                return $args['Range'] === 'bytes=0-' . ($defaultHeaderSize - 1);
            }))
            ->andReturn(new Result(['Body' => 'header']));

        $footerStart = $fileSize - $defaultFooterSize;
        $this->s3ClientMock
            ->shouldReceive('getObject')
            ->once()
            ->with(Mockery::on(function ($args) use ($footerStart, $fileSize) {
                return $args['Range'] === 'bytes=' . $footerStart . '-' . ($fileSize - 1);
            }))
            ->andReturn(new Result(['Body' => 'footer']));

        $result = $this->service->downloadPartial($key);

        $this->assertNotNull($result);
    }

    #[Test]
    public function create_partial_temp_file_creates_file_with_correct_structure(): void
    {
        $headerContent = str_repeat('H', 1000);
        $footerContent = str_repeat('F', 500);
        $fileSize = 10_000;

        $tempPath = $this->service->createPartialTempFile(
            $headerContent,
            $footerContent,
            $fileSize
        );

        $this->assertNotFalse($tempPath);
        $this->assertFileExists($tempPath);
        $this->assertEquals($fileSize, filesize($tempPath));

        // Check header content
        $handle = fopen($tempPath, 'rb');
        $this->assertNotFalse($handle);
        $readHeader = fread($handle, strlen($headerContent));
        $this->assertEquals($headerContent, $readHeader);

        // Check footer content
        fseek($handle, -strlen($footerContent), SEEK_END);
        $readFooter = fread($handle, strlen($footerContent));
        $this->assertEquals($footerContent, $readFooter);

        fclose($handle);
        @unlink($tempPath);
    }
}
