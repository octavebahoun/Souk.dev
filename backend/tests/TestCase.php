<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\ValideLeContrat;

abstract class TestCase extends BaseTestCase
{
    use ValideLeContrat;
}
