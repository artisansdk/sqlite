<?php

declare(strict_types=1);

use ArtisanSdk\SQLite\Registry;
use ArtisanSdk\SQLite\Vec0\{Compiler, Definition, DistanceMetric, Quantization};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Database\SQLiteConnection;

it('builds a Vec0 definition and compiles it to SQL', function (): void {
    $definition = (new Definition('document_vectors'))
        ->key('document_id')
        ->embedding('semantic', 768, 'cos', 'float32', 'qwen')
        ->dimensions(512)
        ->model('qwen3')
        ->metric('l2', 'int8')
        ->partition('tenant_id', 'integer')
        ->metadata('status', 'text')
        ->auxiliary('payload', 'text');

    expect($definition->toArray())
        ->toMatchArray([
            'virtualTable' => 'document_vectors',
            'key' => 'document_id',
            'embeddings' => [[
                'column' => 'semantic',
                'dimensions' => 512,
                'metric' => DistanceMetric::EUCLIDEAN,
                'quantization' => Quantization::Q1B,
                'model' => 'qwen3',
            ]],
            'partitions' => [['column' => 'tenant_id', 'type' => 'INTEGER']],
            'metadata' => [['column' => 'status', 'type' => 'TEXT']],
            'auxiliary' => [['column' => 'payload', 'type' => 'TEXT']],
        ]);

    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);

    $sql = (new Compiler(new Registry))
        ->command($grammar, new Blueprint($connection, 'documents'), $definition)[1];

    expect($sql)->toBe(<<<'SQL'
CREATE VIRTUAL TABLE "document_vectors" USING vec0(
    document_id INTEGER,
    semantic INT8[512] DISTANCE_METRIC=l2,
    tenant_id INTEGER PARTITION KEY,
    status TEXT,
    +payload TEXT
)
SQL);
});

it('rejects invalid Vec0 identifiers and column types', function (Definition $definition, string $message): void {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);

    expect(fn (): array => (new Compiler(new Registry))
        ->command($grammar, new Blueprint($connection, 'documents'), $definition))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    [(new Definition)->embedding('invalid-column'), 'Invalid identifier [invalid-column].'],
    [(new Definition)->embedding()->metadata('score', 'blob'), 'Invalid virtual column type [BLOB].'],
]);

it('requires at least one embedding when compiling Vec0', function (): void {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $grammar = new SQLiteGrammar($connection);
    $connection->setSchemaGrammar($grammar);

    expect(fn (): array => (new Compiler(new Registry))
        ->command($grammar, new Blueprint($connection, 'documents'), new Definition))
        ->toThrow(InvalidArgumentException::class, 'vec0 requires at least one embedding.');
});

it('updates the active embedding through its fluent helpers', function (): void {
    $definition = (new Definition)
        ->embedding()
        ->column('semantic')
        ->cosine()
        ->q1b()
        ->euclidean()
        ->q4b()
        ->manhattan()
        ->qbit();

    expect($definition->embeddings)->toBe([[
        'column' => 'semantic',
        'dimensions' => 1024,
        'metric' => DistanceMetric::MANHATTAN,
        'quantization' => Quantization::QBIT,
        'model' => null,
    ]]);
});

it('configures hamming embeddings with the required quantization', function (): void {
    $definition = (new Definition)->embedding()->hamming();

    expect($definition->embeddings[0])
        ->metric->toBe(DistanceMetric::HAMMING)
        ->quantization->toBe(Quantization::QBIT);
});

it('resolves metric and quantization aliases', function (): void {
    $definition = (new Definition)
        ->embedding('first', 1, 'l1', 'float')
        ->embedding('second', 4096, 'hamming', 'binary')
        ->embedding('third', 2, DistanceMetric::EUCLIDEAN, Quantization::Q2B)
        ->metric(DistanceMetric::COSINE)
        ->quantization('bool');

    expect($definition->embeddings[0])
        ->metric->toBe(DistanceMetric::MANHATTAN)
        ->quantization->toBe(Quantization::Q4B);
    expect($definition->embeddings[1])
        ->metric->toBe(DistanceMetric::HAMMING)
        ->quantization->toBe(Quantization::QBIT);
    expect($definition->embeddings[2])
        ->metric->toBe(DistanceMetric::COSINE)
        ->quantization->toBe(Quantization::QBIT);
});

it('requires an embedding before changing its column', function (): void {
    expect(fn (): Definition => (new Definition)->column('semantic'))
        ->toThrow(LogicException::class, 'Call embedding() before column().');
});

it('requires an embedding before changing its quantization', function (): void {
    expect(fn (): Definition => (new Definition)->quantization('q4b'))
        ->toThrow(LogicException::class, 'Call embedding() first.');
});

it('rejects unsupported embedding options', function (Closure $call, string $exception, string $message): void {
    expect($call)
        ->toThrow($exception, $message);
})->with([
    [fn (): Definition => (new Definition)->embedding(dimensions: 0), OutOfRangeException::class, 'Dimensions must be greater than zero.'],
    [fn (): Definition => (new Definition)->embedding(dimensions: 4097), OutOfRangeException::class, 'Dimensions must be less than 4096.'],
    [fn (): Definition => (new Definition)->embedding(metric: 'invalid'), InvalidArgumentException::class, 'Invalid distance metric [invalid].'],
    [fn (): Definition => (new Definition)->embedding(quantization: 'invalid'), InvalidArgumentException::class, 'Invalid quantization [invalid].'],
    [fn (): Definition => (new Definition)->embedding()->metric('hamming', 'q4b'), InvalidArgumentException::class, 'Hamming and QBIT must be used together.'],
    [fn (): Definition => (new Definition)->embedding()->hamming()->q1b(), InvalidArgumentException::class, 'Hamming and QBIT must be used together.'],
]);
