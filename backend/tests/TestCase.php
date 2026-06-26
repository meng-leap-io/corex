<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    protected bool $seedDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->seedDatabase) {
            Artisan::call('db:seed', ['--class' => 'DatabaseSeeder']);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
