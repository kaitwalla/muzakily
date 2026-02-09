<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\SmartFolder;
use PHPUnit\Framework\TestCase;

class SmartFolderTest extends TestCase
{
    public function test_extracts_top_level_folder(): void
    {
        $path = 's3://bucket/Rock/Artist/Album/song.mp3';

        $result = SmartFolder::extractFromPath($path);

        $this->assertSame('Rock', $result);
    }

    public function test_extracts_second_level_for_special_folders(): void
    {
        $path = 's3://bucket/Xmas/Contemporary/song.mp3';

        $result = SmartFolder::extractFromPath($path, ['Xmas']);

        $this->assertSame('Xmas/Contemporary', $result);
    }

    public function test_handles_r2_protocol(): void
    {
        $path = 'r2://bucket/Jazz/Artist/song.flac';

        $result = SmartFolder::extractFromPath($path);

        $this->assertSame('Jazz', $result);
    }

    public function test_returns_null_for_paths_without_folder(): void
    {
        $path = 's3://bucket/song.mp3';

        $result = SmartFolder::extractFromPath($path);

        $this->assertNull($result);
    }

    public function test_special_folder_with_file_in_root_returns_first_level(): void
    {
        // When a file is directly in the special folder (no subfolder)
        $path = 's3://bucket/Xmas/song.mp3';

        $result = SmartFolder::extractFromPath($path, ['Xmas']);

        $this->assertSame('Xmas', $result);
    }

    public function test_non_special_folder_ignores_second_level(): void
    {
        $path = 's3://bucket/Rock/Alternative/song.mp3';

        $result = SmartFolder::extractFromPath($path, ['Xmas']);

        $this->assertSame('Rock', $result);
    }
}
