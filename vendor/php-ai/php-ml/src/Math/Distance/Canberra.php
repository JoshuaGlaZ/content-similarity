<?php

declare(strict_types=1);

namespace Phpml\Math\Distance;

use Phpml\Exception\InvalidArgumentException;
use Phpml\Math\Distance;

class Canberra implements Distance
{
    /**
     * @throws InvalidArgumentException
     */
    public function distance(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new InvalidArgumentException('Size of given arrays does not match');
        }

        return array_sum(array_map(function ($m, $n) {
        	if(abs($m + $n)==0.0) return abs($m - $n);
            else return (abs($m - $n)/abs($m + $n));
        }, $a, $b));
    }
}
