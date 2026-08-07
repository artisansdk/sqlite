<?php

declare(strict_types=1);

use ArtisanSdk\SQLite\Vec0\DistanceMetric;
use Tests\Stubs\{CustomDocument, Document};

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

it('applies filters and a custom order to vector searches', function (): void {
    $query = Document::query()
        ->forVectorSimilarTo(
            [1, 2],
            order: 'published_at',
            filters: ['tenant_id' => 7],
        );

    expect($query->toSql())
        ->toContain('"documents_vectors"."tenant_id" = ?')
        ->toContain('order by "published_at" asc')
        ->and($query->getBindings())
        ->toBe(['[1,2]', 10, 0.4, 7]);
});

it('uses a pre-encoded vector without encoding it again', function (): void {
    $query = Document::query()
        ->forVectorSimilarTo('[1,2]');

    expect($query->getBindings()[0])->toBe('[1,2]');
});

it('requires dimensions when using the Hamming metric', function (): void {
    expect(fn () => Document::query()
        ->forVectorSimilarTo([1, 2], metric: DistanceMetric::HAMMING))
        ->toThrow(InvalidArgumentException::class, 'Missing $dimensions argument which is required for distance metric relevance calculation.');
});

it('uses custom Vec0 table and column values from the model', function (): void {
    $query = CustomDocument::query()
        ->forVectorSimilarTo([1, 2]);

    expect($query->toSql())
        ->toContain('inner join "semantic_index" on "semantic_index"."id" = "custom_documents"."id"')
        ->toContain('"semantic_index"."vector" MATCH ?')
        ->toContain('"semantic_index"."distance"');
});
