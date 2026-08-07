<?php

declare(strict_types=1);

use ArtisanSdk\SQLite\{Plugin, Provider};
use ArtisanSdk\SQLite\Vec0\Definition;
use Illuminate\Container\Container;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Database\SQLiteConnection;

beforeEach(function (): void {
    (new Provider(new Container))->boot();
});

it('compiles Vec0 virtual table statements through SQLiteGrammar', function (): void {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);
    $definition = (new Definition)
        ->embedding('embedding', 3)
        ->metadata('status')
        ->key();

    $statements = $grammar->compileVirtual(
        new Blueprint($connection, 'documents'),
        $definition,
    );

    expect($definition->plugin)->toBe(Plugin::VEC0)
        ->and($statements)
        ->toHaveCount(3)
        ->and($statements[0])
        ->toStartWith('CREATE TABLE IF NOT EXISTS "virtual_table_indexes"')
        ->and($statements[1])
        ->toBe(<<<'SQL'
CREATE VIRTUAL TABLE "documents_vectors" USING vec0(
    id INTEGER,
    embedding FLOAT[3] DISTANCE_METRIC=cosine,
    status TEXT
)
SQL)
        ->and($statements[2])
        ->toStartWith('INSERT INTO "virtual_table_indexes"');
});

it('compiles Vec0 virtual table drop statements through SQLiteGrammar', function (): void {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);
    $blueprint = new Blueprint($connection, 'documents');
    $command = $blueprint->dropVirtual(Plugin::VEC0);

    expect($grammar->compileDropVirtual($blueprint, $command))->toBe([
        'DROP TABLE IF EXISTS "documents_vectors"',
        'DELETE FROM "virtual_table_indexes" WHERE "table" = \'documents_vectors\'',
    ]);
});
