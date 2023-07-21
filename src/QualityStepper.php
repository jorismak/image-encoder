<?php

declare(strict_types=1);

namespace App;

class QualityStepper
{
    public const INCREASE = 1;
    public const DECREASE = -1;

    public const BIG_STEP = 0;
    public const SMALL_STEP = 1;

    public float $value;
    public float $result;
    public float $maxValueReached;
    public float $minValueReached;
    public float $delta;

    public float $maxValue;
    public float $minValue;

    public float $bigStep;
    public float $smallStep;

    public int $qualityTooHigh;

    public int $mode;

    public float $butterAugliTarget = 2.0;

    public function __construct(float $bigStep, float $smallStep, float $maxValue, float $minValue, int $qualityTooHigh)
    {
        $this->bigStep = $bigStep;
        $this->smallStep = $smallStep;
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
        $this->qualityTooHigh = $qualityTooHigh;

        if ($qualityTooHigh === self::INCREASE) {
            $this->value = $maxValue;
        } else {
            $this->value = $minValue;
        }

        $this->maxValueReached = $maxValue;
        $this->minValueReached = $minValue;

        $this->mode = self::BIG_STEP;
    }

    /**
     * @param callable(float): float $encoder
     */
    public function iterate(callable $encoder): bool
    {
        $this->value = min($this->maxValue, max($this->minValue, $this->value));
        printf("QualityStepper iterating with value %f\n", $this->value);

        $butterAugliScore = ($encoder)($this->value);
        printf("QualityStepper received score of %f\n", $butterAugliScore);

        if ($butterAugliScore >= $this->butterAugliTarget) {
            // quality to low
            if ($this->qualityTooHigh === self::INCREASE) {
                // quality to low, need to decrease value
                if ($this->mode === self::BIG_STEP) {
                    $this->delta = -$this->bigStep;
                    $this->maxValueReached = $this->value;
                } else {
                    $this->delta = -$this->smallStep;
                    $this->maxValueReached = $this->value;
                }
            } else {
                // quality to low, need to increase value
                if ($this->mode === self::BIG_STEP) {
                    $this->delta = $this->bigStep;
                    $this->minValueReached = $this->value;
                } else {
                    $this->delta = $this->smallStep;
                    $this->minValueReached = $this->value;
                }
            }
        } else {
            $this->result = $this->value;

            // quality to high
            if ($this->qualityTooHigh === self::INCREASE) {
                // quality to high, need to increase value
                if ($this->mode === self::BIG_STEP) {
                    $this->delta = $this->bigStep;
                    $this->minValueReached = $this->value;
                } else {
                    $this->delta = $this->smallStep;
                    $this->minValueReached = $this->value;
                }
            } else {
                // quality to high, need to decrease value
                if ($this->mode === self::BIG_STEP) {
                    $this->delta = -$this->bigStep;
                    $this->maxValueReached = $this->value;
                } else {
                    $this->delta = -$this->smallStep;
                    $this->maxValueReached = $this->value;
                }
            }
        }

        $this->value += $this->delta;

        if ($this->delta >= 0) {
            // value just increased
            if ($this->value >= $this->maxValueReached) {
                // We already tried this
                if ($this->mode === self::BIG_STEP) {
                    // reverse direction, switch to smaller steps
                    $this->delta = -$this->smallStep;
                    $this->value -= $this->smallStep;
                    $this->mode = self::SMALL_STEP;
                } else {
                    // already tried smaller steps, we're done

                    return false;
                }
            }
        } else {
            // value just decreased
            if ($this->value <= $this->minValueReached) {
                // We already tried this
                if ($this->mode === self::BIG_STEP) {
                    // reverse direction, switch to smaller steps
                    $this->delta = $this->smallStep;
                    $this->value += $this->smallStep;
                    $this->mode = self::SMALL_STEP;
                } else {
                    // already tried smaller steps, we're done

                    return false;
                }
            }
        }

        return true;
    }
}
