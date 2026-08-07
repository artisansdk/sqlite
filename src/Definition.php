<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite;

use Illuminate\Support\Fluent;

/**
 * @property string $virtualTable
 * @property string $key
 */
abstract class Definition extends Fluent
{
    /**
     * Create a virtual table definition.
     */
    public function __construct(Plugin $plugin, ?string $table = null, array $attributes = [])
    {
        parent::__construct([
            'name' => 'virtual',
            'plugin' => $plugin,
            'virtualTable' => $table,
            ...$attributes,
        ]);
    }

    /**
     * Set the virtual table name.
     */
    public function table(string $table): static
    {
        $this->virtualTable = $table;

        return $this;
    }

    /**
     * Set the primary key column name.
     */
    public function key(string $column = 'id'): self
    {
        $this->key = $column;

        return $this;
    }
}
