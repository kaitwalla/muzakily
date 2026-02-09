<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AudioFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Models\Transcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranscodeTest extends TestCase
{
    use RefreshDatabase;

    private Song $song;

    protected function setUp(): void
    {
        parent::setUp();

        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $this->song = Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
            'audio_format' => AudioFormat::FLAC,
        ]);
    }

    public function test_creates_transcode_record(): void
    {
        $transcode = Transcode::create([
            'song_id' => $this->song->id,
            'format' => 'mp3',
            'bitrate' => 256,
            'storage_key' => 'transcodes/test/mp3_256.mp3',
            'file_size' => 5000000,
        ]);

        $this->assertDatabaseHas('transcodes', [
            'id' => $transcode->id,
            'song_id' => $this->song->id,
            'format' => 'mp3',
            'bitrate' => 256,
        ]);
    }

    public function test_belongs_to_song(): void
    {
        $transcode = Transcode::factory()->create([
            'song_id' => $this->song->id,
        ]);

        $this->assertInstanceOf(Song::class, $transcode->song);
        $this->assertEquals($this->song->id, $transcode->song->id);
    }

    public function test_find_for_song_returns_matching_transcode(): void
    {
        Transcode::factory()->create([
            'song_id' => $this->song->id,
            'format' => 'mp3',
            'bitrate' => 256,
        ]);

        $found = Transcode::findForSong($this->song, 'mp3', 256);

        $this->assertNotNull($found);
        $this->assertEquals('mp3', $found->format);
        $this->assertEquals(256, $found->bitrate);
    }

    public function test_find_for_song_returns_null_for_different_format(): void
    {
        Transcode::factory()->create([
            'song_id' => $this->song->id,
            'format' => 'mp3',
            'bitrate' => 256,
        ]);

        $found = Transcode::findForSong($this->song, 'aac', 256);

        $this->assertNull($found);
    }

    public function test_find_for_song_returns_null_for_different_bitrate(): void
    {
        Transcode::factory()->create([
            'song_id' => $this->song->id,
            'format' => 'mp3',
            'bitrate' => 256,
        ]);

        $found = Transcode::findForSong($this->song, 'mp3', 320);

        $this->assertNull($found);
    }

    public function test_generate_storage_key(): void
    {
        $key = Transcode::generateStorageKey($this->song, 'mp3', 256);

        $this->assertStringStartsWith('transcodes/', $key);
        $this->assertStringContainsString($this->song->id, $key);
        $this->assertStringContainsString('mp3_256', $key);
        $this->assertStringEndsWith('.mp3', $key);
    }

    public function test_generate_storage_key_for_aac(): void
    {
        $key = Transcode::generateStorageKey($this->song, 'aac', 192);

        $this->assertStringContainsString('aac_192', $key);
        $this->assertStringEndsWith('.aac', $key);
    }

    public function test_get_audio_format_returns_enum(): void
    {
        $transcode = Transcode::factory()->create([
            'song_id' => $this->song->id,
            'format' => 'mp3',
            'bitrate' => 256,
        ]);

        $audioFormat = $transcode->getAudioFormat();

        $this->assertInstanceOf(AudioFormat::class, $audioFormat);
        $this->assertEquals(AudioFormat::MP3, $audioFormat);
    }

    public function test_get_audio_format_returns_null_for_invalid(): void
    {
        $transcode = Transcode::factory()->create([
            'song_id' => $this->song->id,
            'format' => 'unknown',
            'bitrate' => 256,
        ]);

        $audioFormat = $transcode->getAudioFormat();

        $this->assertNull($audioFormat);
    }

    public function test_created_at_auto_set(): void
    {
        $transcode = Transcode::create([
            'song_id' => $this->song->id,
            'format' => 'mp3',
            'bitrate' => 256,
            'storage_key' => 'transcodes/test/mp3_256.mp3',
            'file_size' => 5000000,
        ]);

        $this->assertNotNull($transcode->created_at);
    }
}
