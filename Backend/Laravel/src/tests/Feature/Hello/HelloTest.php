<?php

namespace tests\Feature\Hello;

use Tests\TestCase;

class HelloTest extends TestCase
{

    public function test_hello(): void
    {
        $expected = 'Hello World';

        $response = $this->get('/hello');
        $actual = $response->getContent();

        $this->assertEquals($expected, $actual);
    }
}
