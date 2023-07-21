<?php

namespace App;

class ImageQualityIterator
{
    private $imagePath;
    private $targetScore;
    private $minQuality;
    private $maxQuality;
    private $stepSize;

    public function __construct($imagePath, $targetScore, $minQuality = 0, $maxQuality = 100, $stepSize = 10)
    {
        $this->imagePath = $imagePath;
        $this->targetScore = $targetScore;
        $this->minQuality = $minQuality;
        $this->maxQuality = $maxQuality;
        $this->stepSize = $stepSize;
    }

    public function getNextQuality()
    {
        $midQuality = ($this->minQuality + $this->maxQuality) / 2;
        $score = $this->testQuality($midQuality);

        if ($score < $this->targetScore) {
            $this->minQuality = $midQuality;
        } else {
            $this->maxQuality = $midQuality;
        }

        // Use larger steps at first, then smaller steps when narrowing down
        if ($this->maxQuality - $this->minQuality > $this->stepSize) {
            $midQuality += ($score < $this->targetScore ? $this->stepSize : -$this->stepSize);
        } else {
            $midQuality += ($score < $this->targetScore ? 1 : -1);
        }

        return $midQuality;
    }

    private function testQuality($quality)
    {
        // Use butteraugli to test the quality of the image
        // Replace this with your own implementation
        $score = butteraugli($this->imagePath, $quality);

        return $score;
    }
}
