<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Interactions;

use App\Actions\Interactions\RecordSongPlay;
use App\Models\Interaction;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordSongPlayTest extends TestCase
{
    use RefreshDatabase;

    private RecordSongPlay $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new RecordSongPlay();
        $this->user = User::factory()->create();
    }

    public function test_creates_interaction_on_first_play(): void
    {
        $song = Song::factory()->create();

        $interaction = $this->action->execute($this->user, $song);

        $this->assertInstanceOf(Interaction::class, $interaction);
        $this->assertEquals(1, $interaction->play_count);
        $this->assertEquals($this->user->id, $interaction->user_id);
        $this->assertEquals($song->id, $interaction->song_id);
        $this->assertNotNull($interaction->last_played_at);
    }

    public function test_increments_play_count_on_subsequent_plays(): void
    {
        $song = Song::factory()->create();

        $this->action->execute($this->user, $song);
        $interaction = $this->action->execute($this->user, $song);

        $this->assertEquals(2, $interaction->play_count);
    }

    public function test_updates_last_played_at(): void
    {
        $song = Song::factory()->create();

        $firstPlay = $this->action->execute($this->user, $song);
        $firstPlayedAt = $firstPlay->last_played_at;

        // Wait a moment to ensure time difference
        $this->travel(1)->seconds();

        $secondPlay = $this->action->execute($this->user, $song);

        $this->assertTrue($secondPlay->last_played_at->isAfter($firstPlayedAt));
    }

    public function test_creates_separate_interactions_for_different_users(): void
    {
        $song = Song::factory()->create();
        $otherUser = User::factory()->create();

        $this->action->execute($this->user, $song);
        $this->action->execute($otherUser, $song);

        $this->assertDatabaseCount('interactions', 2);
    }

    public function test_creates_separate_interactions_for_different_songs(): void
    {
        $song1 = Song::factory()->create();
        $song2 = Song::factory()->create();

        $this->action->execute($this->user, $song1);
        $this->action->execute($this->user, $song2);

        $this->assertDatabaseCount('interactions', 2);
    }
}
