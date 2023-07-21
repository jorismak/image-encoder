<?php

namespace App\Tests;

use App\ImageQualityIterator;
use App\IteratorDirection;
use PHPUnit\Framework\TestCase;

class ImageQualityIteratorTest extends TestCase
{
    public function testIterate(): void
    {
        $iterator = new ImageQualityIterator(1, 40, 0);
        $iterator->iterate(fn (float $value) => $this->fakeButterAugliCrf((int) $value));

        $result = $iterator->getResult();
        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertLessThanOrEqual(40, $result);
        $this->assertEquals(21, $result);
    }

    public function testIterateLoop(): void
    {
        $iterator = new ImageQualityIterator(1, 80, 0);

        $i = 0;
        while (!$iterator->iterate(fn (float $value) => $this->fakeButterAugliCrf((int) $value))) {
            $i++;
        }
        $this->assertEquals(7, $i);

        $result = $iterator->getResult();
        $score = $this->fakeButterAugliCrf((int) $result);
        $this->assertEquals(22, (int) $result);
        $this->assertEqualsWithDelta(2.0, $score, 0.1);
    }

    public function testIterateLoop2_8(): void
    {
        $iterator = new ImageQualityIterator(1, 80, 0, 2.8);

        $i = 0;
        while (!$iterator->iterate(fn (float $value) => $this->fakeButterAugliCrf((int) $value))) {
            $i++;
        }
        $this->assertEquals(6, $i);

        $result = $iterator->getResult();
        $score = $this->fakeButterAugliCrf((int) $result);
        $this->assertEquals(30, (int) $result);
        $this->assertEqualsWithDelta(2.8, $score, 0.1);
    }

    public function testIterateLoopPrecise(): void
    {
        $iterator = new ImageQualityIterator(1, 80, 2);

        $i = 0;
        while (!$iterator->iterate(fn (float $value) => $this->fakeButterAugliCrf($value))) {
            $i++;
        }
        $this->assertEquals(13, $i);

        $result = $iterator->getResult();
        $score = $this->fakeButterAugliCrf((int) $result);
        $this->assertEquals(22.50, $result);
        $this->assertEqualsWithDelta(2.0, $score, 0.1);
    }

    public function testIterateLoopPrecise2_8(): void
    {
        $iterator = new ImageQualityIterator(1, 80, 2, 2.8);

        $i = 0;
        while (!$iterator->iterate(fn (float $value) => $this->fakeButterAugliCrf($value))) {
            $i++;
        }
        $this->assertEquals(13, $i);

        $result = $iterator->getResult();
        $score = $this->fakeButterAugliCrf((int) $result);
        $this->assertEquals(30, (int) $result);
        $this->assertEqualsWithDelta(2.8, $score, 0.1);
    }

    public function testIterateLoopJpeg(): void
    {
        $iterator = new ImageQualityIterator(1, 100, 0, 2.0, IteratorDirection::INCREASE);

        $i = 0;
        while (!$iterator->iterate(fn (float $value) => $this->fakeButteraugliQuality($value))) {
            $i++;
        }
        $this->assertEquals(7, $i);


        $result = $iterator->getResult();
        $score = $this->fakeButteraugliQuality((int) $result);
        $this->assertEquals(83, (int) $result);
        $this->assertEqualsWithDelta(2.0, $score, 0.1);
    }

    public function fakeButterAugliCrf(float $quality): float
    {
        // $part1 = 0.00385075 * ($quality * $quality);
        // $part2 = 0.102526 * $quality;

        // return $part1 - $part2 + 2.59797;

        return 0.000110559 * pow($quality, 2.75477) + 1.41258;
    }

    public function fakeButteraugliQuality(float $quality): float
    {
        return 9.48925 - 0.199277 * pow($quality, 0.822);
    }
}
