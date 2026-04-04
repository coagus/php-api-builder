<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Integration and Feature tests use TestCase which provides
| a fresh SQLite in-memory database for each test.
|
*/

pest()->extend(TestCase::class)->in('Integration', 'Feature');

beforeEach(function () {
    if (method_exists(TestCase::class, 'setUpDatabase')) {
        TestCase::setUpDatabase();
    }
});
