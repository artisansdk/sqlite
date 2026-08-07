<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite\Vec0;

use ArtisanSdk\SQLite\{Definition as BaseDefinition, Plugin};
use InvalidArgumentException;
use LogicException;
use OutOfRangeException;

/**
 * @property array $embeddings
 * @property array $partitions
 * @property array $metadata
 * @property array $auxiliary
 */
class Definition extends BaseDefinition
{
    /**
     * Current embedding cursor.
     */
    protected ?int $cursor = null;

    /**
     * Create a Vec0 virtual table definition.
     */
    public function __construct(?string $table = null, array $attributes = [])
    {
        parent::__construct(
            Plugin::VEC0,
            $table,
            [
                'key' => 'id',
                'embeddings' => [],
                'partitions' => [],
                'metadata' => [],
                'auxiliary' => [],
                ...$attributes,
            ],
        );
    }

    /**
     * Add an embedding column.
     */
    public function embedding(
        string $column = 'embedding',
        int $dimensions = 1024,
        DistanceMetric|string $metric = DistanceMetric::COSINE,
        Quantization|string|null $quantization = null,
        ?string $model = null
    ): self {
        $this->validateDimensions($dimensions);

        $metric = $this->resolveMetric($metric);

        $quantization = $quantization === null
            ? $metric->defaultQuantization()
            : $this->resolveQuantization($quantization);

        $this->validateMetricQuantization($metric, $quantization);

        $this->embeddings = [
            ...$this->embeddings,
            compact(
                'column',
                'dimensions',
                'metric',
                'quantization',
                'model',
            ),
        ];
        $this->cursor = array_key_last($this->embeddings);

        return $this;
    }

    /**
     * Set the active embedding column.
     */
    public function column(string $column): self
    {
        return $this->setEmbeddingValue('column', $column);
    }

    /**
     * Set the dimensions on the embedding column.
     */
    public function dimensions(int $dimensions): self
    {
        $this->validateDimensions($dimensions);

        return $this->setEmbeddingValue('dimensions', $dimensions);
    }

    /**
     * Set the model metadata on the embedding column.
     */
    public function model(?string $model): self
    {
        return $this->setEmbeddingValue('model', $model);
    }

    /**
     * Set the distance metric on the embedding column.
     */
    public function metric(
        DistanceMetric|string $metric,
        Quantization|string|null $quantization = null
    ): self {
        $metric = $this->resolveMetric($metric);

        $quantization = $quantization === null
            ? $metric->defaultQuantization()
            : $this->resolveQuantization($quantization);

        return $this->setMetric($metric, $quantization);
    }

    /**
     * Set the quantization on the embedding column.
     */
    public function quantization(Quantization|string $quantization): self
    {
        $quantization = $this->resolveQuantization($quantization);
        $current = $this->getEmbedding();

        $this->validateMetricQuantization(
            $current['metric'],
            $quantization,
        );

        return $this->setEmbeddingValue(
            'quantization',
            $quantization,
        );
    }

    /**
     * Set the distance metric to cosine on the embedding column.
     */
    public function cosine(Quantization $quantization = Quantization::Q4B): self
    {
        return $this->setMetric(DistanceMetric::COSINE, $quantization);
    }

    /**
     * Set the distance metric to Euclidean on the embedding column.
     */
    public function euclidean(Quantization $quantization = Quantization::Q4B): self
    {
        return $this->setMetric(DistanceMetric::EUCLIDEAN, $quantization);
    }

    /**
     * Set the distance metric to Manhattan on the embedding column.
     */
    public function manhattan(Quantization $quantization = Quantization::Q4B): self
    {
        return $this->setMetric(DistanceMetric::MANHATTAN, $quantization);
    }

    /**
     * Set the distance metric to Hamming on the embedding column.
     */
    public function hamming(): self
    {
        return $this->setMetric(DistanceMetric::HAMMING, Quantization::QBIT);
    }

    /**
     * Set the quantization to 4-Bytes on the embedding column.
     */
    public function q4b(): self
    {
        return $this->quantization(Quantization::Q4B);
    }

    /**
     * Set the quantization to 1-Byte on the embedding column.
     */
    public function q1b(): self
    {
        return $this->quantization(Quantization::Q1B);
    }

    /**
     * Set the quantization to 1-Bit on the embedding column.
     */
    public function qbit(): self
    {
        return $this->quantization(Quantization::QBIT);
    }

    /**
     * Add a partition key column.
     */
    public function partition(string $column, string $type = 'TEXT'): self
    {
        return $this->appendColumn('partitions', $column, $type);
    }

    /**
     * Add a metadata column.
     */
    public function metadata(string $column, string $type = 'TEXT'): self
    {
        return $this->appendColumn('metadata', $column, $type);
    }

    /**
     * Add an auxiliary column.
     */
    public function auxiliary(string $column = 'metadata', string $type = 'TEXT'): self
    {
        return $this->appendColumn('auxiliary', $column, $type);
    }

    /**
     * Append a column to the definition.
     */
    protected function appendColumn(string $group, string $column, string $type): self
    {
        $this->{$group} = [
            ...$this->{$group},
            [
                'column' => $column,
                'type' => strtoupper($type),
            ],
        ];

        return $this;
    }

    /**
     * Set the metric and quantization on the embedding column.
     */
    protected function setMetric(DistanceMetric $metric, Quantization $quantization): self
    {
        $this->validateMetricQuantization($metric, $quantization);

        $this->setEmbeddingValue('metric', $metric);
        $this->setEmbeddingValue('quantization', $quantization);

        return $this;
    }

    /**
     * Set the value on the embedding column.
     *
     * @throws LogicException when no embedding has been configured
     */
    protected function setEmbeddingValue(string $attribute, mixed $value): self
    {
        if ($this->cursor === null) {
            throw new LogicException(
                "Call embedding() before {$attribute}()."
            );
        }

        $this->embeddings[$this->cursor][$attribute] = $value;

        return $this;
    }

    /**
     * Get the current embedding column.
     *
     * @throws LogicException when no embedding has been configured
     */
    protected function getEmbedding(): array
    {
        if ($this->cursor === null) {
            throw new LogicException('Call embedding() first.');
        }

        return $this->embeddings[$this->cursor] ?? [];
    }

    /**
     * Resolve aliases for distance metrics to standardized value.
     *
     * @throws InvalidArgumentException when the metric alias is not supported
     */
    protected function resolveMetric(DistanceMetric|string $metric): DistanceMetric
    {
        if ($metric instanceof DistanceMetric) {
            return $metric;
        }

        return match (strtolower($metric)) {
            'cosine', 'cos' => DistanceMetric::COSINE,
            'l2', 'euclidean' => DistanceMetric::EUCLIDEAN,
            'l1', 'manhattan' => DistanceMetric::MANHATTAN,
            'hamming' => DistanceMetric::HAMMING,
            default => throw new InvalidArgumentException("Invalid distance metric [{$metric}]."),
        };
    }

    /**
     * Resolve aliases for quantization to standardized value.
     *
     * @throws InvalidArgumentException when the quantization alias is not supported
     */
    protected function resolveQuantization(Quantization|string $quantization): Quantization
    {
        if ($quantization instanceof Quantization) {
            return $quantization;
        }

        return match (strtolower($quantization)) {
            'q4b', 'float', 'float32' => Quantization::Q4B,
            'q1b', 'int', 'int8' => Quantization::Q1B,
            'qbit', 'bit', 'binary', 'boolean', 'bool' => Quantization::QBIT,
            default => throw new InvalidArgumentException("Invalid quantization [{$quantization}]."),
        };
    }

    /**
     * Validate the combination of metric and quantization.
     *
     * @throws InvalidArgumentException when quantization does not match the metric
     */
    protected function validateMetricQuantization(DistanceMetric $metric, Quantization $quantization): void
    {
        if (
            $metric === DistanceMetric::HAMMING
            && $quantization !== Quantization::QBIT
        ) {
            throw new InvalidArgumentException('Hamming and QBIT must be used together.');
        }
    }

    /**
     * Validate the dimension is within range.
     *
     * @throws OutOfRangeException when dimensions is not between 1 and 4096
     */
    protected function validateDimensions(int $dimensions): void
    {
        if ($dimensions < 1) {
            throw new OutOfRangeException(
                'Dimensions must be greater than zero.'
            );
        }

        if ($dimensions > 4096) {
            throw new OutOfRangeException(
                'Dimensions must be less than 4096.'
            );
        }
    }
}
