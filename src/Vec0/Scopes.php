<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite\Vec0;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\{Builder, Model};
use InvalidArgumentException;
use JsonException;

trait Scopes
{
    /**
     * Filter models using the related Vec0 virtual table.
     *
     * @throws JsonException when the vector cannot be encoded
     * @throws InvalidArgumentException when using hamming without passing dimensions
     * @throws InvalidArgumentException when distance metric is not supported
     */
    public function scopeForVectorSimilarTo(
        Builder $query,
        array|Arrayable|string $vector,
        ?string $column = null,
        string|bool|null $order = true,
        array $filters = [],
        ?string $table = null,
        float $similarity = 0.6,
        int $limit = 10,
        DistanceMetric $metric = DistanceMetric::COSINE,
        ?int $dimensions = null,
    ): Builder {
        if ($metric === DistanceMetric::HAMMING && ! $dimensions) {
            throw new InvalidArgumentException('Missing $dimensions argument which is required for distance metric relevance calculation.');
        }

        /** @var Model $this */
        $source = $this->getTable();
        $table ??= $this->vec0Table();
        $column ??= $this->vec0Column();
        $key = $this->getKeyName();
        $vector = $this->wrapVector($vector);

        $grammar = $query->getQuery()->getGrammar();
        $score = $column.'_score';
        $relevance = $column.'_relevance';
        $column = $grammar->wrap($table).'.'.$grammar->wrap($column);
        $distance = $grammar->wrap($table).'."distance"';

        $query
            ->join($table, $table.'.'.$key, '=', $source.'.'.$key)
            ->select($source.'.*')
            ->selectRaw($distance.' as '.$grammar->wrap($score))
            ->selectRaw(
                match ($metric) {
                    DistanceMetric::COSINE => '1 - '.$distance,
                    DistanceMetric::EUCLIDEAN, DistanceMetric::MANHATTAN => '1 / (1 + '.$distance.')',
                    DistanceMetric::HAMMING => '1 - ('.$distance.' / ?)',
                    default => throw new InvalidArgumentException("Invalid distance metric [{$metric}]."),
                }.' as '.$grammar->wrap($relevance),
                $metric === DistanceMetric::HAMMING ? [$dimensions] : [],
            )
            ->whereRaw(
                "{$column} MATCH ? AND {$grammar->wrap($table)}.\"k\" = ? AND {$grammar->wrap($table)}.\"distance\" <= ?",
                [$vector, $limit, 1 - $similarity],
            );

        foreach ($filters as $name => $value) {
            $query->where($table.'.'.$name, $value);
        }

        if (! empty($order)) {
            $query->orderBy(
                is_string($order)
                    ? $grammar->wrap($order)
                    : $grammar->wrap($metric->value),
            );
        }

        return $query;
    }

    /**
     * Get the default Vec0 virtual table name.
     */
    protected function vec0Table(): string
    {
        /** @var Model $this */
        return $this->getTable().'_vectors';
    }

    /**
     * Get the default Vec0 virtual table column name.
     */
    protected function vec0Column(): string
    {
        return 'embedding';
    }

    /**
     * Wrap a vector embedding into JSON array.
     *
     * @throws JsonException when the vector cannot be encoded
     */
    protected function wrapVector(array|Arrayable|string $vector): string
    {
        if (is_string($vector)) {
            return $vector;
        }

        return json_encode(
            $vector instanceof Arrayable ? $vector->toArray() : $vector,
            JSON_THROW_ON_ERROR,
        );
    }
}
