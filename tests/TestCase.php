<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    /**
     * El `migrate:fresh --seed` que este setUp() duplicaba también sembraba con
     * DatabaseSeeder; RefreshDatabase sola no lo hace salvo que se declare esto.
     */
    protected $seed = true;
}
