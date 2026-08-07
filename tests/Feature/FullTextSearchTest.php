<?php

declare(strict_types=1);

use Faker\Factory as Faker;
use Tests\Stubs\Document;

it('finds known text with accurate FTS5 relevance', function (): void {
    $faker = Faker::create();
    $content = [
        ...array_map(fn (): string => $faker->paragraph(), range(1, 5)),
        'The cobalt moonflower protocol preserves the lighthouse archive.',
        ...array_map(fn (): string => $faker->paragraph(), range(1, 5)),
    ];

    foreach ($content as $paragraph) {
        $document = Document::query()->create([
            'content' => $paragraph,
        ]);

        $document->getConnection()->table('documents_fulltext')->insert([
            'rowid' => $document->getKey(),
            'content' => $document->content,
        ]);
    }

    $document = Document::query()
        ->forFullTextSimilarTo('"cobalt moonflower protocol"', median: 5)
        ->sole();

    expect($document->content)
        ->toBe('The cobalt moonflower protocol preserves the lighthouse archive.')
        ->and(abs($document->content_relevance - ($document->content_score / ($document->content_score + 5))))
        ->toBeLessThan(0.000001)
        ->and($document->content_relevance)
        ->toBeGreaterThan(0)
        ->toBeLessThan(1);
});
