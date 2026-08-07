<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite\FTS5;

use ArtisanSdk\SQLite\{Definition as BaseDefinition, Plugin};
use OutOfRangeException;

/**
 * @property string[] $columns
 * @property string $tokenizer
 * @property int[] $prefixes
 * @property IndexType $detail
 * @property int $columnSize
 */
class Definition extends BaseDefinition
{
    /**
     * Create an FTS5 virtual table definition.
     */
    public function __construct(?string $table = null, array $attributes = [])
    {
        parent::__construct(
            Plugin::FTS5,
            $table,
            [
                'columns' => [],
                'key' => 'id',
                'tokenizer' => null,
                'prefixes' => [],
                'detail' => IndexType::FULL,
                'columnSize' => 1,
                ...$attributes,
            ],
        );
    }

    /**
     * Add an indexed column to the virtual table.
     */
    public function column(string $column, bool $indexed = true): self
    {
        $this->columns = [
            ...$this->columns,
            [
                'name' => $column,
                'indexed' => $indexed,
            ],
        ];

        return $this;
    }

    /**
     * Add an unindexed column to the virtual table.
     */
    public function unindexed(string $column): self
    {
        return $this->column($column, indexed: false);
    }

    /**
     * Set the tokenizer.
     */
    public function tokenizer(string $tokenizer): self
    {
        $this->tokenizer = $tokenizer;

        return $this;
    }

    /**
     * Use the Unicode61 tokenizer with the given options.
     */
    public function unicode(string ...$options): self
    {
        return $this->tokenizer(
            trim('unicode61 '.implode(' ', $options))
        );
    }

    /**
     * Use the Porter tokenizer.
     */
    public function porter(): self
    {
        return $this->tokenizer('porter unicode61');
    }

    /**
     * Use the trigram tokenizer.
     */
    public function trigram(): self
    {
        return $this->tokenizer('trigram');
    }

    /**
     * Add token prefix sizes.
     *
     * @throws OutOfRangeException when a prefix size is less than one
     */
    public function prefix(int ...$sizes): self
    {
        foreach ($sizes as $size) {
            if ($size < 1) {
                throw new OutOfRangeException('FTS5 prefix sizes must be positive integers.');
            }
        }

        $this->prefixes = array_values(
            array_unique([
                ...$this->prefixes,
                ...$sizes,
            ])
        );

        return $this;
    }

    /**
     * Set the indexing detail level.
     */
    public function index(IndexType $index = IndexType::FULL): self
    {
        $this->detail = $index;

        return $this;
    }

    /**
     * Set whether column sizes are stored.
     *
     * If the column size is stored (default behavior) then the index will be larger.
     * If the column size is not stored then the index will be compact.
     *
     * @example compact() or compact(true) makes the index smaller but at the
     *          expense of recalculating column sizes when needed
     */
    public function compact(bool $enabled = true): self
    {
        $this->columnSize = $enabled ? 0 : 1;

        return $this;
    }
}
