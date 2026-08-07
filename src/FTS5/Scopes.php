<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite\FTS5;

use Illuminate\Database\Eloquent\{Builder, Model};

trait Scopes
{
    /**
     * Filter models using the related FTS5 virtual table.
     */
    public function scopeForFullTextSimilarTo(
        Builder $query,
        string $term,
        ?string $column = null,
        string|bool|null $order = true,
        array $filters = [],
        ?string $table = null,
        int $median = 5
    ): Builder {
        /** @var Model $this */
        $source = $this->getTable();
        $table ??= $this->fts5Table();
        $column ??= $this->fts5Column();
        $key = $this->getKeyName();

        $grammar = $query->getQuery()->getGrammar();
        $bm25 = '-bm25('.$grammar->wrap($table).')';
        $score = $column.'_score';
        $relevance = $column.'_relevance';
        $column = $grammar->wrap($table).'.'.$grammar->wrap($column);

        $query
            ->join($table, $table.'.rowid', '=', $source.'.'.$key)
            ->select($source.'.*')
            ->selectRaw($bm25.' as '.$grammar->wrap($score))
            ->selectRaw($bm25.' / ('.$bm25.' + ?) as '.$grammar->wrap($relevance), [$median])
            ->whereRaw("{$column} MATCH ?", [$term]);

        foreach ($filters as $name => $value) {
            $query->where($table.'.'.$name, $value);
        }

        if (! empty($order)) {
            $query->orderBy(
                is_string($order)
                    ? $order
                    : $score,
            );
        }

        return $query;
    }

    /**
     * Get the default FTS5 virtual table name.
     */
    protected function fts5Table(): string
    {
        /** @var Model $this */
        return $this->getTable().'_fulltext';
    }

    /**
     * Get the default FTS5 virtual table column name.
     */
    protected function fts5Column(): string
    {
        return 'content';
    }
}
