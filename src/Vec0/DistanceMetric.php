<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite\Vec0;

enum DistanceMetric: string
{
    case COSINE = 'cosine';
    case EUCLIDEAN = 'l2';
    case MANHATTAN = 'l1';
    case HAMMING = 'hamming';

    /**
     * Get the default quantization for the metric.
     */
    public function defaultQuantization(): Quantization
    {
        return $this === self::HAMMING
            ? Quantization::QBIT
            : Quantization::Q4B;
    }
}
