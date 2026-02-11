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

        $tagNames = $this->tagService->extractTagNamesFromPath($path);

        $this->assertCount(1, $tagNames);
        $this->assertContains('Rock', $tagNames);
    }

    public function test_extracts_multiple_tags_for_special_folders(): void
    {
        $path = 'Xmas/Contemporary/Artist/song.mp3';

        $tagNames = $this->tagService->extractTagNamesFromPath($path);

        $this->assertCount(2, $tagNames);
        $this->assertContains('xmas', $tagNames);
        $this->assertContains('xmas - contemporary', $tagNames);
    }

    public function test_holiday_is_not_a_special_folder(): void
    {
        $path = 'Holiday/Classic/Artist/song.mp3';

        $tagNames = $this->tagService->extractTagNamesFromPath($path);

        $this->assertCount(1, $tagNames);
        $this->assertContains('Holiday', $tagNames);
    }

    public function test_seasonal_is_not_a_special_folder(): void
    {
        $path = 'Seasonal/Winter/Artist/song.mp3';

        $tagNames = $this->tagService->extractTagNamesFromPath($path);

        $this->assertCount(1, $tagNames);
        $this->assertContains('Seasonal', $tagNames);
    }

    public function test_special_folder_without_subfolder_returns_folder_only(): void
    {
        $path = 'Xmas/song.mp3';

        $tagNames = $this->tagService->extractTagNamesFromPath($path);

        $this->assertCount(1, $tagNames);
        $this->assertContains('xmas', $tagNames);
    }

    public function test_returns_empty_for_empty_path(): void
    {
        $tagNames = $this->tagService->extractTagNamesFromPath('');

        $this->assertEmpty($tagNames);
    }

    public function test_returns_empty_for_root_file(): void
    {
        $tagNames = $this->tagService->extractTagNamesFromPath('song.mp3');

        $this->assertEmpty($tagNames);
    }

    public function test_handles_deep_nested_paths(): void
    {
        $path = 'Jazz/Miles Davis/Kind of Blue/01 - So What.mp3';

        $tagNames = $this->tagService->extractTagNamesFromPath($path);

        $this->assertCount(1, $tagNames);
        $this->assertContains('Jazz', $tagNames);
    }

    public function test_handles_paths_with_special_characters(): void
    {
        $path = 'Rock & Roll/Artist (Live)/Album [2024]/song.mp3';

        $tagNames = $this->tagService->extractTagNamesFromPath($path);

        $this->assertCount(1, $tagNames);
        $this->assertContains('Rock & Roll', $tagNames);
    }

    public function test_case_insensitive_special_folder_matching(): void
    {
        // lowercase 'xmas' SHOULD be treated as special (case insensitive)
        $path = 'xmas/Contemporary/song.mp3';

        $tagNames = $this->tagService->extractTagNamesFromPath($path);

        $this->assertCount(2, $tagNames);
        $this->assertContains('xmas', $tagNames);
        $this->assertContains('xmas - contemporary', $tagNames);
    }

    public function test_custom_special_folders_can_be_configured(): void
    {
        config(['muzakily.tags.special_folders' => ['CustomSpecial']]);

        $path = 'CustomSpecial/SubFolder/song.mp3';

        $tagNames = $this->tagService->extractTagNamesFromPath($path);

        $this->assertCount(2, $tagNames);
        $this->assertContains('customspecial', $tagNames);
        $this->assertContains('customspecial - subfolder', $tagNames);
    }
}
