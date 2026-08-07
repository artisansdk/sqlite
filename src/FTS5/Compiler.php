<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite\FTS5;

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
     *     'CREATE VIRTUAL TABLE "document_chunks_fulltext" USING fts5("name", content=\'document_chunks\', content_rowid=\'id\', detail=full, columnsize=1)',
     *     'INSERT INTO "virtual_table_indexes" (...)',
     * ]
     *
     * @throws InvalidArgumentException when no columns are configured or an identifier is invalid
     */
    public function command(SQLiteGrammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $source = $blueprint->getTable();
        $table = $this->tableName($blueprint, $command);

        $this->validateIdentifier($source);
        $this->validateIdentifier($table);
        $this->validateIdentifier($command->key);

        if (($command->columns ?? []) === []) {
            throw new InvalidArgumentException('FTS5 requires at least one column.');
        }

        $arguments = [];

        foreach ($command->columns as $column) {
            $this->validateIdentifier($column['name']);

            $arguments[] = $grammar->wrap($column['name'])
                .($column['indexed'] ? '' : ' UNINDEXED');
        }

        $arguments[] = sprintf(
            "content='%s'",
            $this->literal($source),
        );

        $arguments[] = sprintf(
            "content_rowid='%s'",
            $this->literal($command->key),
        );

        if ($command->tokenizer !== null) {
            $arguments[] = sprintf(
                "tokenize='%s'",
                $this->literal($command->tokenizer),
            );
        }

        if ($command->prefixes !== []) {
            $arguments[] = sprintf(
                "prefix='%s'",
                implode(' ', $command->prefixes),
            );
        }

        $arguments[] = 'detail='.$command->detail->value;
        $arguments[] = 'columnsize='.(int) $command->columnSize;

        return [
            $this->registry->schema(),
            sprintf(
                "CREATE VIRTUAL TABLE %s USING fts5(\n    %s\n)",
                $grammar->wrapTable($table),
                implode(",\n    ", $arguments),
            ),
            $this->registry->register(
                source: $source,
                table: $table,
                plugin: Plugin::FTS5,
                config: [
                    'columns' => $command->columns,
                    'key' => $command->key,
                    'tokenizer' => $command->tokenizer,
                    'prefixes' => $command->prefixes,
                    'index' => $command->detail->value,
                    'compact' => $command->columnSize === 0,
                ],
            ),
        ];
    }
}
