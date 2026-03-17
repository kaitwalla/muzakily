<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Metadata;

use App\Services\Metadata\AcoustIdService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AcoustIdServiceTest extends TestCase
{
    private AcoustIdService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->service = new AcoustIdService(apiKey: 'test-key', minScore: 0.5);
    }

    #[Test]
    public function it_returns_recording_id_on_successful_match(): void
    {
        Process::fake([
            'fpcalc *' => Process::result(
                output: json_encode([
                    'fingerprint' => 'AQADtMmybckm',
                    'duration' => 243.5,
                ]),
                exitCode: 0,
            ),
        ]);

        Http::fake([
            'https://api.acoustid.org/*' => Http::response([
                'status' => 'ok',
                'results' => [
                    [
                        'score' => 0.92,
                        'recordings' => [
                            ['id' => 'mb-recording-uuid-1'],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->service->lookup('/tmp/song.flac');

        $this->assertSame('mb-recording-uuid-1', $result);
    }

    #[Test]
    public function it_returns_null_when_fpcalc_fails(): void
    {
        Process::fake([
            'fpcalc *' => Process::result(exitCode: 1, errorOutput: 'No such file'),
        ]);

        $result = $this->service->lookup('/tmp/missing.flac');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_returns_null_when_fpcalc_output_is_invalid(): void
    {
        Process::fake([
            'fpcalc *' => Process::result(output: 'not json', exitCode: 0),
        ]);

        $result = $this->service->lookup('/tmp/song.flac');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_returns_null_when_score_is_below_threshold(): void
    {
        Process::fake([
            'fpcalc *' => Process::result(
                output: json_encode(['fingerprint' => 'AQADtMmybckm', 'duration' => 200]),
                exitCode: 0,
            ),
        ]);

        Http::fake([
            'https://api.acoustid.org/*' => Http::response([
                'status' => 'ok',
                'results' => [
                    [
                        'score' => 0.3, // below 0.5 threshold
                        'recordings' => [['id' => 'mb-recording-uuid-1']],
                    ],
                ],
            ]),
        ]);

        $result = $this->service->lookup('/tmp/song.flac');

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_when_acoustid_returns_no_results(): void
    {
        Process::fake([
            'fpcalc *' => Process::result(
                output: json_encode(['fingerprint' => 'AQADtMmybckm', 'duration' => 200]),
                exitCode: 0,
            ),
        ]);

        Http::fake([
            'https://api.acoustid.org/*' => Http::response([
                'status' => 'ok',
                'results' => [],
            ]),
        ]);

        $result = $this->service->lookup('/tmp/song.flac');

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_when_acoustid_api_fails(): void
    {
        Process::fake([
            'fpcalc *' => Process::result(
                output: json_encode(['fingerprint' => 'AQADtMmybckm', 'duration' => 200]),
                exitCode: 0,
            ),
        ]);

        Http::fake([
            'https://api.acoustid.org/*' => Http::response([], 503),
        ]);

        $result = $this->service->lookup('/tmp/song.flac');

        $this->assertNull($result);
    }

    #[Test]
    public function it_skips_results_without_recordings_and_returns_next_match(): void
    {
        Process::fake([
            'fpcalc *' => Process::result(
                output: json_encode(['fingerprint' => 'AQADtMmybckm', 'duration' => 200]),
                exitCode: 0,
            ),
        ]);

        Http::fake([
            'https://api.acoustid.org/*' => Http::response([
                'status' => 'ok',
                'results' => [
                    ['score' => 0.95, 'recordings' => []], // no recordings
                    ['score' => 0.90, 'recordings' => [['id' => 'mb-recording-uuid-2']]],
                ],
            ]),
        ]);

        $result = $this->service->lookup('/tmp/song.flac');

        $this->assertSame('mb-recording-uuid-2', $result);
    }
}
