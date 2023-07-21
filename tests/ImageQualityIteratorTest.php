<?php

namespace App\Tests;

use App\ImageQualityIterator;
use PHPUnit\Framework\TestCase;

class ImageQualityIteratorTest extends TestCase
{
    public function testSingleLoop(): void
    {
        $iterator = new ImageQualityIterator(1, 40);
        $iterator->iterate();

        $result = $iterator->getResult();
    
        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertLessThanOrEqual(40, $result);
    }
}
