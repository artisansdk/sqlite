<?php

declare(strict_types=1);

use ArtisanSdk\SQLite\Vec0\DistanceMetric;
use Tests\Stubs\Document;

it('normalizes vector relevance by distance metric', function (DistanceMetric $metric, string $sql, ?int $dimensions): void {
    $query = Document::query()
        ->forVectorSimilarTo([1, 2], metric: $metric, dimensions: $dimensions);

    expect($query->toSql())
        ->toContain($sql)
        ->and($query->getBindings())
        ->toHaveKey(0)
        ->and($query->getBindings()[0])
        ->toBe($metric === DistanceMetric::HAMMING ? $dimensions : '[1,2]');
})->with([
    [DistanceMetric::COSINE, '1 - "documents_vectors"."distance"', null],
    [DistanceMetric::EUCLIDEAN, '1 / (1 + "documents_vectors"."distance")', null],
    [DistanceMetric::MANHATTAN, '1 / (1 + "documents_vectors"."distance")', null],
    [DistanceMetric::HAMMING, '1 - ("documents_vectors"."distance" / ?)', 256],
]);
