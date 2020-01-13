<?php

// tests/MathTest.php
namespace App\Tests;


use App\Math;
use PHPUnit\Framework\TestCase;


class MathTest extends TestCase
{

    public function testDouble()
    {
        $calculator = new Math();
        $result = $calculator::double(30);

        // assert that your calculator doubled the number correctly!
        $this->assertEquals(60, $result);
    }


}