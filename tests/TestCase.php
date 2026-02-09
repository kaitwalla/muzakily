<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Scout\Builder;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use collection driver for tests by default (no external services needed)
        config(['scout.driver' => 'collection']);
    }
}
