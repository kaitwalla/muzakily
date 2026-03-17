<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Metadata;

use App\Services\Metadata\MusicBrainzService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MusicBrainzServiceTest extends TestCase
{
    private MusicBrainzService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->service = new MusicBrainzService();
    }

    // -------------------------------------------------------------------------
    // search()
    // -------------------------------------------------------------------------

    #[Test]
    public function search_returns_full_result_on_match(): void
    {
        $this->fakeSearch([
            'recordings' => [
                [
                    'id' => 'mb-rec-uuid',
                    'title' => 'Bohemian Rhapsody',
                    'artist-credit' => [
                        ['artist' => ['id' => 'mb-artist-uuid', 'name' => 'Queen']],
                    ],
                    'releases' => [
                        ['id' => 'mb-release-uuid', 'title' => 'A Night at the Opera'],
                    ],
                ],
            ],
        ]);

        $this->fakeCoverArt('mb-release-uuid', found: true);

        $result = $this->service->search('Bohemian Rhapsody', 'Queen', 'A Night at the Opera');

        $this->assertNotNull($result);
        $this->assertSame('mb-rec-uuid', $result['musicbrainz_id']);
        $this->assertSame('Bohemian Rhapsody', $result['title']);
        $this->assertSame('Queen', $result['artist_name']);
        $this->assertSame('mb-artist-uuid', $result['artist_mbid']);
        $this->assertSame('A Night at the Opera', $result['album_name']);
        $this->assertSame('mb-release-uuid', $result['album_mbid']);
        $this->assertStringContainsString('mb-release-uuid', $result['album_cover'] ?? '');
        $this->assertNull($result['artist_bio']);
    }

    #[Test]
    public function search_returns_null_when_no_recordings(): void
    {
        $this->fakeSearch(['recordings' => []]);

        $result = $this->service->search('Unknown Song');

        $this->assertNull($result);
    }

    #[Test]
    public function search_returns_null_on_api_failure(): void
    {
        Http::fake([
            'musicbrainz.org/*' => Http::response([], 503),
        ]);

        $result = $this->service->search('Some Song');

        $this->assertNull($result);
    }

    #[Test]
    public function search_handles_missing_artist_and_release_gracefully(): void
    {
        $this->fakeSearch([
            'recordings' => [
                ['id' => 'mb-rec-uuid', 'title' => 'Instrumental'],
            ],
        ]);

        $result = $this->service->search('Instrumental');

        $this->assertNotNull($result);
        $this->assertSame('mb-rec-uuid', $result['musicbrainz_id']);
        $this->assertSame('Instrumental', $result['title']);
        $this->assertNull($result['artist_name']);
        $this->assertNull($result['artist_mbid']);
        $this->assertNull($result['album_name']);
        $this->assertNull($result['album_mbid']);
        $this->assertNull($result['album_cover']);
    }

    #[Test]
    public function search_returns_null_cover_when_cover_art_archive_has_none(): void
    {
        $this->fakeSearch([
            'recordings' => [
                [
                    'id' => 'mb-rec-uuid',
                    'title' => 'Some Song',
                    'releases' => [
                        ['id' => 'mb-release-uuid', 'title' => 'Some Album'],
                    ],
                ],
            ],
        ]);

        $this->fakeCoverArt('mb-release-uuid', found: false);

        $result = $this->service->search('Some Song');

        $this->assertNotNull($result);
        $this->assertNull($result['album_cover']);
    }

    // -------------------------------------------------------------------------
    // lookupRecording()
    // -------------------------------------------------------------------------

    #[Test]
    public function lookup_recording_returns_full_result(): void
    {
        $this->fakeLookup('mb-rec-uuid', [
            'id' => 'mb-rec-uuid',
            'title' => 'Stairway to Heaven',
            'artist-credit' => [
                ['artist' => ['id' => 'mb-artist-uuid', 'name' => 'Led Zeppelin']],
            ],
            'releases' => [
                ['id' => 'mb-release-uuid', 'title' => 'Led Zeppelin IV'],
            ],
        ]);

        $this->fakeCoverArt('mb-release-uuid', found: true);

        $result = $this->service->lookupRecording('mb-rec-uuid');

        $this->assertNotNull($result);
        $this->assertSame('mb-rec-uuid', $result['musicbrainz_id']);
        $this->assertSame('Stairway to Heaven', $result['title']);
        $this->assertSame('Led Zeppelin', $result['artist_name']);
        $this->assertSame('mb-artist-uuid', $result['artist_mbid']);
        $this->assertSame('Led Zeppelin IV', $result['album_name']);
        $this->assertSame('mb-release-uuid', $result['album_mbid']);
        $this->assertStringContainsString('mb-release-uuid', $result['album_cover'] ?? '');
        $this->assertNull($result['artist_bio']);
    }

    #[Test]
    public function lookup_recording_returns_null_for_unknown_id(): void
    {
        Http::fake([
            'musicbrainz.org/ws/2/recording/*' => Http::response([], 404),
            'coverartarchive.org/*' => Http::response([], 404),
        ]);

        $result = $this->service->lookupRecording('nonexistent-uuid');

        $this->assertNull($result);
    }

    #[Test]
    public function lookup_recording_returns_null_on_api_failure(): void
    {
        Http::fake([
            'musicbrainz.org/*' => Http::response([], 503),
        ]);

        $result = $this->service->lookupRecording('mb-rec-uuid');

        $this->assertNull($result);
    }

    #[Test]
    public function lookup_recording_handles_missing_artist_and_release(): void
    {
        $this->fakeLookup('mb-rec-uuid', [
            'id' => 'mb-rec-uuid',
            'title' => 'Mystery Track',
        ]);

        $result = $this->service->lookupRecording('mb-rec-uuid');

        $this->assertNotNull($result);
        $this->assertSame('mb-rec-uuid', $result['musicbrainz_id']);
        $this->assertSame('Mystery Track', $result['title']);
        $this->assertNull($result['artist_name']);
        $this->assertNull($result['artist_mbid']);
        $this->assertNull($result['album_name']);
        $this->assertNull($result['album_mbid']);
        $this->assertNull($result['album_cover']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $body
     */
    private function fakeSearch(array $body): void
    {
        Http::fake([
            'musicbrainz.org/ws/2/recording*' => Http::response($body),
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function fakeLookup(string $recordingId, array $body): void
    {
        Http::fake([
            "musicbrainz.org/ws/2/recording/{$recordingId}*" => Http::response($body),
        ]);
    }

    private function fakeCoverArt(string $releaseId, bool $found): void
    {
        Http::fake([
            "coverartarchive.org/release/{$releaseId}/*" => $found
                ? Http::response('', 200)
                : Http::response('', 404),
        ]);
    }
}
