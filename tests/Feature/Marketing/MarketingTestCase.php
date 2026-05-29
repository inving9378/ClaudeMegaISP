<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\CreatesApplication;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base TestCase for Marketing tests.
 * Uses DatabaseTransactions instead of migrate:fresh — safe to run against the
 * development DB since every test rolls back its changes automatically.
 */
abstract class MarketingTestCase extends BaseTestCase
{
    use CreatesApplication, DatabaseTransactions;
}
