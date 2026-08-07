<?php

declare(strict_types=1);

use ArtisanSdk\SQLite\FTS5\{Compiler, Definition, IndexType};
use ArtisanSdk\SQLite\Registry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Database\SQLiteConnection;

it('builds an FTS5 definition and compiles it to SQL', function (): void {
    $definition = (new Definition('document_search'))
        ->key('document_id')
        ->column('title')
        ->unindexed('author')
        ->unicode('remove_diacritics', '2')
        ->prefix(2, 3, 2)
        ->index(IndexType::COLUMN)
        ->compact();

    expect($definition->toArray())
        ->toMatchArray([
            'virtualTable' => 'document_search',
            'key' => 'document_id',
            'columns' => [
                ['name' => 'title', 'indexed' => true],
                ['name' => 'author', 'indexed' => false],
            ],
            'tokenizer' => 'unicode61 remove_diacritics 2',
            'prefixes' => [2, 3],
            'detail' => IndexType::COLUMN,
            'columnSize' => 0,
        ]);

    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);

    $sql = (new Compiler(new Registry))
        ->command($grammar, new Blueprint($connection, 'documents'), $definition)[1];

    expect($sql)->toBe(<<<'SQL'
CREATE VIRTUAL TABLE "document_search" USING fts5(
    "title",
    "author" UNINDEXED,
    content='documents',
    content_rowid='document_id',
    tokenize='unicode61 remove_diacritics 2',
    prefix='2 3',
    detail=column,
    columnsize=0
)
SQL);
});

it('uses a renamed FTS5 virtual table when compiling', function (): void {
    $definition = (new Definition)
        ->table('article_search')
        ->column('content');
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);

    $sql = (new Compiler(new Registry))
        ->command($grammar, new Blueprint($connection, 'articles'), $definition)[1];

    expect($sql)->toStartWith('CREATE VIRTUAL TABLE "article_search" USING fts5(');
});

it('rejects invalid FTS5 column identifiers', function (): void {
    $definition = (new Definition)
        ->column('invalid-column');
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);

    expect(fn (): array => (new Compiler(new Registry))
        ->command($grammar, new Blueprint($connection, 'articles'), $definition))
        ->toThrow(InvalidArgumentException::class, 'Invalid identifier [invalid-column].');
});

it('requires at least one column when compiling FTS5', function (): void {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);

    expect(fn (): array => (new Compiler(new Registry))
        ->command($grammar, new Blueprint($connection, 'articles'), new Definition))
        ->toThrow(InvalidArgumentException::class, 'FTS5 requires at least one column.');
});

it('configures tokenizer shortcuts', function (Closure $configure, string $tokenizer): void {
    expect($configure(new Definition)->tokenizer)->toBe($tokenizer);
})->with([
    [fn (Definition $definition): Definition => $definition->porter(), 'porter unicode61'],
    [fn (Definition $definition): Definition => $definition->trigram(), 'trigram'],
]);

it('requires positive FTS5 prefix sizes', function (int $size): void {
    expect(fn (): Definition => (new Definition)->prefix($size))
        ->toThrow(OutOfRangeException::class, 'FTS5 prefix sizes must be positive integers.');
})->with([0, -1]);
