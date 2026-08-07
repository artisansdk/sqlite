<?php

declare(strict_types=1);

use Tests\Stubs\Document;

it('normalizes BM25 score to relevance', function (): void {
    $query = Document::query()
        ->forFullTextSimilarTo('moonflower', median: 5);

    expect($query->toSql())
        ->toContain('-bm25("documents_fulltext") / (-bm25("documents_fulltext") + ?) as "content_relevance"')
        ->and($query->getBindings())
        ->toHaveKey(0)
        ->and($query->getBindings()[0])
        ->toBe(5);
});
