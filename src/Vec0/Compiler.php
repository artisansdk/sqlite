<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite\Vec0;

use ArtisanSdk\SQLite\{Compiler as BaseCompiler, Plugin};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Fluent;
use InvalidArgumentException;

class Compiler extends BaseCompiler
{
    /**
     * {@inheritDoc}
     *
     * @example
     * [
     *     'CREATE TABLE IF NOT EXISTS "virtual_table_indexes" (...)',
     *     'CREATE VIRTUAL TABLE "document_chunks_vectors" USING vec0("id" INTEGER, "embedding" FLOAT[1024] DISTANCE_METRIC=cosine)',
     *     'INSERT INTO "virtual_table_indexes" (...)',
     * ]
     *
     * @throws InvalidArgumentException when no embedding is configured or an identifier is invalid
     */
    public function command(SQLiteGrammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $source = $blueprint->getTable();
        $table = $this->tableName($blueprint, $command);
        $embeddings = $command->embeddings ?? [];

        $this->validateIdentifier($source);
        $this->validateIdentifier($table);
        $this->validateIdentifier($command->key);

        if ($embeddings === []) {
            throw new InvalidArgumentException(
                'vec0 requires at least one embedding.'
            );
        }

        $columns = [
            $command->key.' INTEGER',
        ];

        foreach ($embeddings as $embedding) {
            $this->validateIdentifier($embedding['column']);

            $columns[] = sprintf(
                '%s %s[%d] DISTANCE_METRIC=%s',
                $embedding['column'],
                $embedding['quantization']->sqliteType(),
                $embedding['dimensions'],
                $embedding['metric']->value,
            );
        }

        foreach ($command->partitions as $column) {
            $this->validateColumn($column);

            $columns[] = sprintf(
                '%s %s PARTITION KEY',
                $column['column'],
                $column['type'],
            );
        }

        foreach ($command->metadata as $column) {
            $this->validateColumn($column);

            $columns[] = sprintf(
                '%s %s',
                $column['column'],
                $column['type'],
            );
        }

        foreach ($command->auxiliary as $column) {
            $this->validateColumn($column);

            $columns[] = sprintf(
                '+%s %s',
                $column['column'],
                $column['type'],
            );
        }

        return [
            $this->registry->schema(),
            sprintf(
                "CREATE VIRTUAL TABLE %s USING vec0(\n    %s\n)",
                $grammar->wrapTable($table),
                implode(",\n    ", $columns),
            ),
            $this->registry->register(
                table: $table,
                source: $source,
                plugin: Plugin::VEC0,
                config: [
                    'key' => $command->key,
                    'embeddings' => array_map(
                        fn (array $embedding): array => [
                            'column' => $embedding['column'],
                            'dimensions' => $embedding['dimensions'],
                            'metric' => $embedding['metric']->value,
                            'quantization' => $embedding['quantization']->value,
                            'model' => $embedding['model'],
                        ],
                        $embeddings,
                    ),
                    'partitions' => $command->partitions,
                    'metadata' => $command->metadata,
                    'auxiliary' => $command->auxiliary,
                ],
            ),
        ];
    }
}
