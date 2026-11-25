<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        gc_collect_cycles();
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }
    }
}
