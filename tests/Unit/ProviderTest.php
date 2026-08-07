<?php

declare(strict_types=1);

use ArtisanSdk\SQLite\{Plugin, Provider};
use Illuminate\Container\Container;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Fluent;

beforeEach(function (): void {
    (new Provider(new Container))->boot();
});

it('resolves plugin strings passed to Blueprint virtual', function (): void {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $connection->setSchemaGrammar(new SQLiteGrammar($connection));
    $blueprint = new Blueprint($connection, 'documents');

    expect($blueprint->virtual('FTS5')->plugin)->toBe(Plugin::FTS5)
        ->and($blueprint->virtual('vec0')->plugin)->toBe(Plugin::VEC0)
        ->and($blueprint->dropVirtual('VEC0')->plugin)->toBe(Plugin::VEC0);
});

it('rejects unsupported plugins passed to the virtual table grammar macro', function (): void {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);

    expect(fn (): array => $grammar->compileVirtual(
        new Blueprint($connection, 'documents'),
        new Fluent(['plugin' => 'invalid']),
    ))->toThrow(InvalidArgumentException::class, 'Plugin "invalid" is not supported.');
});

it('rejects unsupported plugins passed to the drop virtual table grammar macro', function (): void {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);

    expect(fn (): array => $grammar->compileDropVirtual(
        new Blueprint($connection, 'documents'),
        new Fluent(['plugin' => 'foo']),
    ))->toThrow(InvalidArgumentException::class, 'Plugin "foo" is not supported.');
});
