<?php

namespace App;

class ImageQualityIterator
{
    protected ?float $result = null;

    public function __construct(
        public float $minQuality,
        public float $maxQuality,
        public float $butteraugliTarget = 2.0,
    ) {
    }

    public function iterate()
    {
        $value = ($this->minQuality + $this->maxQuality) / 2;

        $this->result = $value;
    }

    public function getResult(): ?float
    {
        return $this->result;
    }
}
