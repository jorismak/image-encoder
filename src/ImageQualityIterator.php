<?php

namespace App;

enum IteratorDirection
{
    case INCREASE;
    case DECREASE;
}

class ImageQualityIterator
{
    protected ?float $result = null;

    public function __construct(
        // minimum value to allow for 'quality' setting
        public float $minQuality,

        // maximum value to allow for 'quality' setting
        public float $maxQuality,

        // precision used in round() call for rounding of quality
        public int $qualityPrecision,

        // target butteraugli score to find
        public float $butteraugliTarget = 2.0,

        // direction to take the 'quality' to get a shorter distance score
        public IteratorDirection $direction = IteratorDirection::DECREASE
    ) {
    }

    /**
     * @param callable(float): float $tester
     */
    public function iterate(callable $tester): bool
    {
        $value = ($this->minQuality + $this->maxQuality) / 2;
        $value = round($value, $this->qualityPrecision);

        // Rounding goes up. So if we hit the max quality, we can't go any more precise.
        if ($value === $this->maxQuality) {
            // Depending on the direction, return the low or high value
            if ($this->direction === IteratorDirection::DECREASE) {
                $value = $this->minQuality;
            } else {
                $value = $this->maxQuality;
            }

            if ($value !== $this->result) {
                ($tester)($value);
                $this->result = $value;
            }

            return true;
        }

        $score = ($tester)($value);
        $this->result = $value;

        if ($score < $this->butteraugliTarget) {
            if ($this->direction === IteratorDirection::DECREASE) {
                $this->minQuality = $value;
            } else {
                $this->maxQuality = $value;
            }
        } elseif ($score > $this->butteraugliTarget) {
            if ($this->direction === IteratorDirection::DECREASE) {
                $this->maxQuality = $value;
            } else {
                $this->minQuality = $value;
            }
        }

        // printf("Iterate. Value = %.3f, score = %.3f, min = %.3f, max = %.3f\n", $value, $score, $this->minQuality, $this->maxQuality);

        return false;
    }

    public function getResult(): ?float
    {
        return $this->result;
    }
}
