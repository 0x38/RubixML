<?php

namespace Rubix\ML\Metrics\Distance;

use InvalidArgumentException;

class Minkowski implements Distance
{
    /**
     * @var float
     */
    protected $lambda;

    /**
     * @param  float  $lambda
     * @throws \InvalidArgumentException
     * @return void
     */
    public function __construct(float $lambda = 3.0)
    {
        if ($lambda < 1) {
            throw new InvalidArgumentException('Lambda must be 1 or greater');
        }

        $this->lambda = $lambda;
    }

    /**
     * Compute the distance given two coordinate vectors.
     *
     * @param  array  $a
     * @param  array  $b
     * @return float
     */
    public function compute(array $a, array $b) : float
    {
        $distance = 0.0;

        foreach ($a as $i => $coordinate) {
            $distance += pow(abs($coordinate - $b[$i]), $this->lambda);
        }

        return pow($distance, 1.0 / $this->lambda);
    }
}
