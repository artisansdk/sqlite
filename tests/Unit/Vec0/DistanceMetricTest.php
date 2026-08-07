<?php

declare(strict_types=1);

use ArtisanSdk\SQLite\Vec0\{DistanceMetric, Quantization};

it('maps distance metrics to their default quantization', function (DistanceMetric $metric, Quantization $quantization): void {
    expect($metric->defaultQuantization())->toBe($quantization);
})->with([
    [DistanceMetric::COSINE, Quantization::Q4B],
    [DistanceMetric::EUCLIDEAN, Quantization::Q4B],
    [DistanceMetric::MANHATTAN, Quantization::Q4B],
    [DistanceMetric::HAMMING, Quantization::QBIT],
]);
