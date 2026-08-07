<?php

declare(strict_types=1);

use Tests\Stubs\{CustomDocument, Document};

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

it('applies filters and a custom order to full text searches', function (): void {
    $query = Document::query()
        ->forFullTextSimilarTo(
            'moonflower',
            order: 'published_at',
            filters: ['author_id' => 7],
        );

    expect($query->toSql())
        ->toContain('"documents_fulltext"."author_id" = ?')
        ->toContain('order by "published_at" asc')
        ->and($query->getBindings())
        ->toBe([5, 'moonflower', 7]);
});

it('uses custom FTS5 table and column values from the model', function (): void {
    $query = CustomDocument::query()
        ->forFullTextSimilarTo('moonflower');

    expect($query->toSql())
        ->toContain('inner join "search_index" on "search_index"."rowid" = "custom_documents"."id"')
        ->toContain('"search_index"."body" MATCH ?')
        ->toContain('-bm25("search_index")');
});
