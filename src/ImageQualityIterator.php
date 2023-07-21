<?php

namespace App;

class ImageQualityIterator
{
    protected ?float $result = null;

    public function __construct(
        public float $minQuality,
        public float $maxQuality,
        public int $qualityPrecision,
        public float $butteraugliTarget = 2.0,
    ) {
    }

    public function iterate(callable $tester): bool
    {
        $value = ($this->minQuality + $this->maxQuality) / 2;
        $value = round($value, $this->qualityPrecision);
        $this->result = $value;

        // Rounding goes up. So if we hit the max quality, we can't go any more precise.
        if ($value === $this->maxQuality) {
            // Dependong in preference, return the low or high value
            $this->result = $this->minQuality;

            return true;
        }

        $score = ($tester)($value);
        if ($score < $this->butteraugliTarget) {
            $this->minQuality = $value;
        } elseif ($score > $this->butteraugliTarget) {
            $this->maxQuality = $value;
        }

        printf("Iterate. Value = %.3f, score = %.3f, min = %.3f, max = %.3f\n", $value, $score, $this->minQuality, $this->maxQuality);

        return false;
    }

    public function getResult(): ?float
    {
        return $this->result;
    }
}
