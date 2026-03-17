<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Metadata;

use App\Contracts\MusicStorageInterface;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Services\Library\CoverArtService;
use App\Services\Metadata\AcoustIdService;
use App\Services\Metadata\MetadataAggregatorService;
use App\Services\Metadata\MusicBrainzService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetadataAggregatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private MusicBrainzService&MockInterface $musicBrainz;

    private AcoustIdService&MockInterface $acoustId;

    private MusicStorageInterface&MockInterface $storage;

    private CoverArtService&MockInterface $coverArt;

    private MetadataAggregatorService $aggregator;

    private Artist $artist;

    private Album $album;

    protected function setUp(): void
    {
        parent::setUp();

        $this->musicBrainz = Mockery::mock(MusicBrainzService::class);
        $this->acoustId = Mockery::mock(AcoustIdService::class);
        $this->storage = Mockery::mock(MusicStorageInterface::class);
        $this->coverArt = Mockery::mock(CoverArtService::class);

        $this->aggregator = new MetadataAggregatorService(
            musicBrainz: $this->musicBrainz,
            coverArtService: $this->coverArt,
            acoustId: $this->acoustId,
            storage: $this->storage,
        );

        $this->artist = Artist::factory()->create(['name' => 'Unknown Artist', 'musicbrainz_id' => null]);
        $this->album = Album::factory()->create(['artist_id' => $this->artist->id, 'musicbrainz_id' => null, 'cover' => null]);
    }

    private function makeSong(array $attrs = []): Song
    {
        return Song::factory()->create(array_merge([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
            'musicbrainz_id' => null,
            'storage_path' => 'music/track.flac',
        ], $attrs));
    }

    #[Test]
    public function it_skips_songs_that_already_have_a_musicbrainz_id(): void
    {
        $song = $this->makeSong(['musicbrainz_id' => 'existing-mbid']);

        $this->musicBrainz->shouldNotReceive('search');
        $this->acoustId->shouldNotReceive('lookup');

        $this->aggregator->enrich($song);
    }

    #[Test]
    public function it_enriches_via_text_search_when_title_is_present(): void
    {
        $song = $this->makeSong(['title' => 'Some Song']);

        $this->musicBrainz->shouldReceive('search')
            ->once()
            ->andReturn([
                'musicbrainz_id' => 'rec-mbid',
                'title' => 'Some Song',
                'artist_mbid' => 'artist-mbid',
                'artist_name' => 'Artist Name',
                'album_mbid' => 'album-mbid',
                'album_name' => 'Album Name',
                'album_cover' => null,
                'artist_bio' => null,
            ]);

        $this->acoustId->shouldNotReceive('lookup');
        $this->coverArt->shouldNotReceive('storeFromUrl');

        $this->aggregator->enrich($song);

        $this->assertSame('rec-mbid', $song->fresh()->musicbrainz_id);
        $this->assertSame('artist-mbid', $this->artist->fresh()->musicbrainz_id);
        $this->assertSame('album-mbid', $this->album->fresh()->musicbrainz_id);
    }

    #[Test]
    public function it_falls_back_to_acoustid_when_text_search_fails(): void
    {
        $song = $this->makeSong(['title' => 'Some Song']);

        $this->musicBrainz->shouldReceive('search')->once()->andReturn(null);

        $this->storage->shouldReceive('download')
            ->once()
            ->with('music/track.flac', Mockery::type('string'))
            ->andReturn(true);

        $this->acoustId->shouldReceive('lookup')
            ->once()
            ->andReturn('rec-mbid-from-fingerprint');

        $this->musicBrainz->shouldReceive('lookupRecording')
            ->once()
            ->with('rec-mbid-from-fingerprint')
            ->andReturn([
                'musicbrainz_id' => 'rec-mbid-from-fingerprint',
                'title' => 'Identified Title',
                'artist_mbid' => 'artist-mbid',
                'artist_name' => null,
                'album_mbid' => 'album-mbid',
                'album_name' => null,
                'album_cover' => null,
                'artist_bio' => null,
            ]);

        $this->coverArt->shouldNotReceive('storeFromUrl');

        $this->aggregator->enrich($song);

        $this->assertSame('rec-mbid-from-fingerprint', $song->fresh()->musicbrainz_id);
    }

    #[Test]
    public function it_uses_acoustid_directly_when_song_has_no_title(): void
    {
        $song = $this->makeSong(['title' => '', 'artist_name' => '']);

        $this->musicBrainz->shouldNotReceive('search');

        $this->storage->shouldReceive('download')->once()->andReturn(true);
        $this->acoustId->shouldReceive('lookup')->once()->andReturn('rec-mbid');
        $this->musicBrainz->shouldReceive('lookupRecording')
            ->once()
            ->andReturn([
                'musicbrainz_id' => 'rec-mbid',
                'title' => 'Found Title',
                'artist_mbid' => null,
                'artist_name' => null,
                'album_mbid' => null,
                'album_name' => null,
                'album_cover' => null,
                'artist_bio' => null,
            ]);

        $this->aggregator->enrich($song);

        $fresh = $song->fresh();
        $this->assertSame('rec-mbid', $fresh->musicbrainz_id);
        $this->assertSame('Found Title', $fresh->title);
    }

    #[Test]
    public function it_downloads_cover_art_when_album_has_none(): void
    {
        $song = $this->makeSong(['title' => '', 'artist_name' => '']);

        $this->storage->shouldReceive('download')->once()->andReturn(true);
        $this->acoustId->shouldReceive('lookup')->once()->andReturn('rec-mbid');
        $this->musicBrainz->shouldReceive('lookupRecording')->once()->andReturn([
            'musicbrainz_id' => 'rec-mbid',
            'title' => '',
            'artist_mbid' => null,
            'artist_name' => null,
            'album_mbid' => 'album-mbid',
            'album_name' => null,
            'album_cover' => 'https://coverartarchive.org/release/abc/front-250',
            'artist_bio' => null,
        ]);

        $this->coverArt->shouldReceive('storeFromUrl')
            ->once()
            ->with(Mockery::type(Album::class), 'https://coverartarchive.org/release/abc/front-250')
            ->andReturn('covers/stored.jpg');

        $this->aggregator->enrich($song);

        $this->assertSame('covers/stored.jpg', $this->album->fresh()->cover);
    }

    #[Test]
    public function it_does_not_overwrite_existing_cover_art(): void
    {
        $this->album->update(['cover' => 'covers/existing.jpg']);
        $song = $this->makeSong(['title' => '', 'artist_name' => '']);

        $this->storage->shouldReceive('download')->once()->andReturn(true);
        $this->acoustId->shouldReceive('lookup')->once()->andReturn('rec-mbid');
        $this->musicBrainz->shouldReceive('lookupRecording')->once()->andReturn([
            'musicbrainz_id' => 'rec-mbid',
            'title' => '',
            'artist_mbid' => null,
            'artist_name' => null,
            'album_mbid' => 'album-mbid',
            'album_name' => null,
            'album_cover' => 'https://coverartarchive.org/release/abc/front-250',
            'artist_bio' => null,
        ]);

        $this->coverArt->shouldNotReceive('storeFromUrl');

        $this->aggregator->enrich($song);

        $this->assertSame('covers/existing.jpg', $this->album->fresh()->cover);
    }

    #[Test]
    public function it_does_nothing_when_acoustid_finds_no_match(): void
    {
        $song = $this->makeSong(['title' => '', 'artist_name' => '']);

        $this->storage->shouldReceive('download')->once()->andReturn(true);
        $this->acoustId->shouldReceive('lookup')->once()->andReturn(null);
        $this->musicBrainz->shouldNotReceive('lookupRecording');

        $this->aggregator->enrich($song);

        $this->assertNull($song->fresh()->musicbrainz_id);
    }

    #[Test]
    public function it_cleans_up_temp_file_even_when_download_fails(): void
    {
        $song = $this->makeSong(['title' => '', 'artist_name' => '']);

        $this->storage->shouldReceive('download')->once()->andReturn(false);
        $this->acoustId->shouldNotReceive('lookup');

        $this->aggregator->enrich($song);

        $this->assertNull($song->fresh()->musicbrainz_id);
    }
}
