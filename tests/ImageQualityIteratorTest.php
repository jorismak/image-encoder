<?php

namespace App\Tests;

use App\ImageQualityIterator;
use PHPUnit\Framework\TestCase;

class ImageQualityIteratorTest extends TestCase
{
    public function testIterate(): void
    {
        $iterator = new ImageQualityIterator(1, 40);
        $iterator->iterate(fn (float $value) => $this->fakeButterAugliCrf((int) $value));

        $result = $iterator->getResult();
        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertLessThanOrEqual(40, $result);
        $this->assertEquals(20.5, $result);
    }

    public function testIterateLoop(): void
    {
        $iterator = new ImageQualityIterator(1, 40);

        $i = 1;
        while (!$iterator->iterate(fn (float $value) => $this->fakeButterAugliCrf((int) $value))) {
            printf("Iterating loop %d\n", $i + 1);
        }

        $result = $iterator->getResult();
        $score = $this->fakeButterAugliCrf((int) $result);
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
