<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Fluent;
use InvalidArgumentException;

abstract class Compiler
{
    /**
     * Inject dependencies.
     */
    public function __construct(protected Registry $registry) {}

    /**
     * Compile a plugin's virtual table command.
     */
    abstract public function command(SQLiteGrammar $grammar, Blueprint $blueprint, Fluent $command): array;

    /**
     * Resolve the virtual table name.
     *
     * @throws InvalidArgumentException when plugin is not supported
     */
    public function tableName(Blueprint $blueprint, Fluent $command): string
    {
        if ($command->virtualTable !== null) {
            return $command->virtualTable;
        }

        return sprintf(
            '%s_%s',
            $blueprint->getTable(),
            match ($command->plugin) {
                Plugin::FTS5 => 'fulltext',
                Plugin::VEC0 => 'vectors',
                default => throw new InvalidArgumentException(sprintf('Plugin "%s" is not supported.', $command->plugin)),
            }
        );
    }

    /**
     * Validate a SQLite identifier.
     *
     * @throws InvalidArgumentException when value is not valid
     */
    public function validateIdentifier(string $value): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("Invalid identifier [{$value}].");
        }
    }

    /**
     * Validate the column identifier and type.
     *
     * @throws InvalidArgumentException when virtual column type is not valid
     */
    public function validateColumn(array $column): void
    {
        $this->validateIdentifier($column['column']);

        if (! in_array(
            $column['type'],
            ['TEXT', 'INTEGER', 'FLOAT', 'BOOLEAN'],
            true,
        )) {
            throw new InvalidArgumentException("Invalid virtual column type [{$column['type']}].");
        }
    }

    /**
     * Escape a SQLite string literal.
     */
    protected function literal(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
