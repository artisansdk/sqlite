<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite;

use JsonException;

class Registry
{
    /**
     * Table name for the registry.
     */
    public protected(set) static string $tableName = 'virtual_table_indexes';

    /**
     * Set the registry table name.
     */
    public static function setTableName(string $table): void
    {
        static::$tableName = $table;
    }

    /**
     * Get the SQL schema for the virtual table index registry.
     */
    public static function schema(): string
    {
        return sprintf(<<<'SQL'
CREATE TABLE IF NOT EXISTS "%s" (
    "table" TEXT PRIMARY KEY,
    "source" TEXT NOT NULL,
    "plugin" TEXT NOT NULL,
    "config" TEXT NOT NULL,
    "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL,
            static::$tableName,
        );
    }

    /**
     * Get the SQL statement to register a virtual table index.
     *
     * @throws JsonException when config cannot be encoded.
     */
    public function register(string $table, string $source, Plugin $plugin, array $config): string
    {
        $json = $this->literal(json_encode(
            $config,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE,
        ));

        return sprintf(
            <<<'SQL'
INSERT INTO "%s" (
    "table",
    "source",
    "plugin",
    "config"
) VALUES ('%s', '%s', '%s', '%s')
ON CONFLICT ("table") DO UPDATE SET
    "source" = excluded."source",
    "plugin" = excluded."plugin",
    "config" = excluded."config",
    "updated_at" = CURRENT_TIMESTAMP
SQL,
            static::$tableName,
            $this->literal($table),
            $this->literal($source),
            $plugin->value,
            $json,
        );
    }

    /**
     * Get the SQL statement to unregister a virtual table index.
     */
    public function unregister(string $table): string
    {
        return sprintf(
            'DELETE FROM "%s" WHERE "table" = \'%s\'',
            static::$tableName,
            $this->literal($table),
        );
    }

    /**
     * Escape a SQLite string literal.
     */
    protected function literal(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
