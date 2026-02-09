<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Library\TagService;
use Tests\TestCase;

class TagExtractionTest extends TestCase
{
    private TagService $tagService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tagService = new TagService();
    }

    public function test_extracts_top_level_folder(): void
    {
        $path = 'Rock/Artist Name/Album Name/01 - Song.mp3';

        $tagName = $this->tagService->extractTagNameFromPath($path);

        $this->assertEquals('Rock', $tagName);
    }

    public function test_extracts_second_level_for_special_folders(): void
    {
        $path = 'Xmas/Contemporary/Artist/song.mp3';

        $tagName = $this->tagService->extractTagNameFromPath($path);

        $this->assertEquals('Xmas/Contemporary', $tagName);
    }

    public function test_handles_holiday_as_special_folder(): void
    {
        $path = 'Holiday/Classic/Artist/song.mp3';

        $tagName = $this->tagService->extractTagNameFromPath($path);

        $this->assertEquals('Holiday/Classic', $tagName);
    }

    public function test_handles_seasonal_as_special_folder(): void
    {
        $path = 'Seasonal/Winter/Artist/song.mp3';

        $tagName = $this->tagService->extractTagNameFromPath($path);

        $this->assertEquals('Seasonal/Winter', $tagName);
    }

    public function test_special_folder_without_subfolder_returns_folder_only(): void
    {
        $path = 'Xmas/song.mp3';

        $tagName = $this->tagService->extractTagNameFromPath($path);

        $this->assertEquals('Xmas', $tagName);
    }

    public function test_returns_null_for_empty_path(): void
    {
        $tagName = $this->tagService->extractTagNameFromPath('');

        $this->assertNull($tagName);
    }

    public function test_returns_null_for_root_file(): void
    {
        $tagName = $this->tagService->extractTagNameFromPath('song.mp3');

        $this->assertNull($tagName);
    }

    public function test_handles_deep_nested_paths(): void
    {
        $path = 'Jazz/Miles Davis/Kind of Blue/01 - So What.mp3';

        $tagName = $this->tagService->extractTagNameFromPath($path);

        $this->assertEquals('Jazz', $tagName);
    }

    public function test_handles_paths_with_special_characters(): void
    {
        $path = 'Rock & Roll/Artist (Live)/Album [2024]/song.mp3';

        $tagName = $this->tagService->extractTagNameFromPath($path);

        $this->assertEquals('Rock & Roll', $tagName);
    }

    public function test_case_sensitive_special_folder_matching(): void
    {
        // lowercase 'xmas' should NOT be treated as special
        $path = 'xmas/Contemporary/song.mp3';

        $tagName = $this->tagService->extractTagNameFromPath($path);

        // Should still extract as a regular folder (just the top level)
        $this->assertEquals('xmas', $tagName);
    }

    public function test_custom_special_folders_can_be_configured(): void
    {
        config(['muzakily.tags.special_folders' => ['CustomSpecial']]);

        $path = 'CustomSpecial/SubFolder/song.mp3';

        $tagName = $this->tagService->extractTagNameFromPath($path);

        $this->assertEquals('CustomSpecial/SubFolder', $tagName);
    }
}
