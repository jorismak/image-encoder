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
        $score = $this->fakeButterAugliCrf((int) $result);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertLessThanOrEqual(40, $result);

        $this->assertEquals(20.5, $result);

        $this->assertEqualsWithDelta(2.0, $score, 0.1);
    }

    public function fakeButterAugliCrf(float $quality): float
    {
        // $part1 = 0.00385075 * ($quality * $quality);
        // $part2 = 0.102526 * $quality;

        // return $part1 - $part2 + 2.59797;

        return 0.000110559 * pow($quality, 2.75477) + 1.41258;
    }
}
