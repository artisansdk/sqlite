<?php

declare(strict_types=1);

use ArtisanSdk\SQLite\FTS5\Definition;
use ArtisanSdk\SQLite\{Plugin, Provider};
use Illuminate\Container\Container;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Database\SQLiteConnection;

beforeEach(function (): void {
    (new Provider(new Container))->boot();
});

it('compiles FTS5 virtual table statements through SQLiteGrammar', function (): void {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);
    $definition = (new Definition)
        ->column('content')
        ->key();

    $statements = $grammar->compileVirtual(
        new Blueprint($connection, 'documents'),
        $definition,
    );

    expect($definition->plugin)->toBe(Plugin::FTS5)
        ->and($statements)
        ->toHaveCount(3)
        ->and($statements[0])
        ->toStartWith('CREATE TABLE IF NOT EXISTS "virtual_table_indexes"')
        ->and($statements[1])
        ->toBe(<<<'SQL'
CREATE VIRTUAL TABLE "documents_fulltext" USING fts5(
    "content",
    content='documents',
    content_rowid='id',
    detail=full,
    columnsize=1
)
SQL)
        ->and($statements[2])
        ->toStartWith('INSERT INTO "virtual_table_indexes"');
});

it('compiles FTS5 virtual table drop statements through SQLiteGrammar', function (): void {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);
    $blueprint = new Blueprint($connection, 'documents');
    $command = $blueprint->dropVirtual(Plugin::FTS5);

    expect($grammar->compileDropVirtual($blueprint, $command))->toBe([
        'DROP TABLE IF EXISTS "documents_fulltext"',
        'DELETE FROM "virtual_table_indexes" WHERE "table" = \'documents_fulltext\'',
    ]);
});
