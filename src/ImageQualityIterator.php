<?php

namespace App;

class ImageQualityIterator
{
    protected ?float $result = null;

    public function __construct(
        public float $minQuality,
        public float $maxQuality,
    ) {
    }

    public function iterate()
    {

    }

    public function getResult(): ?float
    {
        return $this->result;
    }
}
