<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Playlist;
use App\Models\Song;
use App\Models\Tag;
use App\Models\User;
use App\Services\Playlist\SmartPlaylistEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartPlaylistEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private SmartPlaylistEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = app(SmartPlaylistEvaluator::class);
    }

    public function test_tag_has_operator_filters_songs_with_tag(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['name' => 'rock']);

        $songWithTag = Song::factory()->create();
        $songWithTag->tags()->attach($tag);

        $songWithoutTag = Song::factory()->create();

        $playlist = new Playlist([
            'is_smart' => true,
            'rules' => [
                [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'tag', 'operator' => 'has', 'value' => 'rock'],
                    ],
                ],
            ],
        ]);

        $matchingSongs = $this->evaluator->evaluateDynamic($playlist, $user);

        $this->assertCount(1, $matchingSongs);
        $this->assertTrue($matchingSongs->contains($songWithTag));
        $this->assertFalse($matchingSongs->contains($songWithoutTag));
    }

    public function test_tag_has_not_operator_filters_songs_without_tag(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['name' => 'rock']);

        $songWithTag = Song::factory()->create();
        $songWithTag->tags()->attach($tag);

        $songWithoutTag = Song::factory()->create();

        $playlist = new Playlist([
            'is_smart' => true,
            'rules' => [
                [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'tag', 'operator' => 'has_not', 'value' => 'rock'],
                    ],
                ],
            ],
        ]);

        $matchingSongs = $this->evaluator->evaluateDynamic($playlist, $user);

        $this->assertCount(1, $matchingSongs);
        $this->assertFalse($matchingSongs->contains($songWithTag));
        $this->assertTrue($matchingSongs->contains($songWithoutTag));
    }

    public function test_is_favorite_and_tag_has_combined_with_and_logic(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['name' => 'christmas']);

        // Song 1: favorite + tag
        $favoriteSongWithTag = Song::factory()->create();
        $favoriteSongWithTag->tags()->attach($tag);
        $user->favorites()->create([
            'favoritable_id' => $favoriteSongWithTag->id,
            'favoritable_type' => Song::class,
        ]);

        // Song 2: favorite, no tag
        $favoriteSongWithoutTag = Song::factory()->create();
        $user->favorites()->create([
            'favoritable_id' => $favoriteSongWithoutTag->id,
            'favoritable_type' => Song::class,
        ]);

        // Song 3: not favorite, has tag
        $nonFavoriteSongWithTag = Song::factory()->create();
        $nonFavoriteSongWithTag->tags()->attach($tag);

        // Song 4: neither
        Song::factory()->create();

        $playlist = new Playlist([
            'is_smart' => true,
            'rules' => [
                [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'is_favorite', 'operator' => 'is', 'value' => true],
                        ['field' => 'tag', 'operator' => 'has', 'value' => 'christmas'],
                    ],
                ],
            ],
        ]);

        $matchingSongs = $this->evaluator->evaluateDynamic($playlist, $user);

        $this->assertCount(1, $matchingSongs);
        $this->assertTrue($matchingSongs->contains($favoriteSongWithTag));
        $this->assertFalse($matchingSongs->contains($favoriteSongWithoutTag));
        $this->assertFalse($matchingSongs->contains($nonFavoriteSongWithTag));
    }

    public function test_tag_is_operator_works_same_as_has(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['name' => 'jazz']);

        $songWithTag = Song::factory()->create();
        $songWithTag->tags()->attach($tag);

        Song::factory()->create();

        $playlistWithIs = new Playlist([
            'is_smart' => true,
            'rules' => [
                [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'tag', 'operator' => 'is', 'value' => 'jazz'],
                    ],
                ],
            ],
        ]);

        $playlistWithHas = new Playlist([
            'is_smart' => true,
            'rules' => [
                [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'tag', 'operator' => 'has', 'value' => 'jazz'],
                    ],
                ],
            ],
        ]);

        $countWithIs = $this->evaluator->count($playlistWithIs, $user);
        $countWithHas = $this->evaluator->count($playlistWithHas, $user);

        $this->assertEquals($countWithIs, $countWithHas);
        $this->assertEquals(1, $countWithHas);
    }
}
