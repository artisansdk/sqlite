# SQLite Virtual Tables

A virtual table extension for Laravel Eloquent models to enable FTS5 and Vec0 search indexes.

## Table of Contents

- [SQLite Virtual Tables](#sqlite-virtual-tables)
  - [Table of Contents](#table-of-contents)
  - [Installation](#installation)
  - [Usage Guide](#usage-guide)
    - [Add Virtual Tables](#add-virtual-tables)
    - [Drop Virtual Tables](#drop-virtual-tables)
    - [Query Virtual Tables](#query-virtual-tables)
    - [Eloquent Scopes](#eloquent-scopes)
      - [Customizing Tables and Columns](#customizing-tables-and-columns)
      - [Filtering Results](#filtering-results)
      - [Full Text Parameters](#full-text-parameters)
      - [Vector Parameters](#vector-parameters)
    - [Customize Virtual Table Names](#customize-virtual-table-names)
    - [Customize the Registry Table](#customize-the-registry-table)
  - [Docker Development](#docker-development)
    - [Running the Tests](#running-the-tests)
  - [Licensing](#licensing)

## Installation

The package installs into a PHP application like any other PHP package:

```bash
composer require artisansdk/sqlite
```

## Usage Guide

### Add Virtual Tables

You can use this as an example for a migration that sets up FTS5 and Vec0
search indexes on a table of chunked document content. The `document_chunks`
table structure is immaterial but you can see how to setup a fluent virtual
table using one of the supported plugins.

```php
use ArtisanSdk\SQLite\FTS5\IndexType;
use ArtisanSdk\SQLite\Vec0\Quantization;
use ArtisanSdk\SQLite\Plugin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('document_chunks', function (Blueprint $table) {
    $table->id('chunk_id');
    $table->uuid('tenant_id')->index();
    $table->uuid('document_id')->index();
    $table->text('content');
    $table->json('metadata')->nullable();

    // Create a virtual table for FTS5
    $table->virtual(Plugin::FTS5)
        ->column('title')
        ->column('content')
        ->key('chunk_id')
        ->unicode('remove_diacritics', '2')
        ->prefix(2, 3, 4)
        ->index(IndexType::FULL)
        ->compact(false);

    // Create a virtual table for Vec0
    $table->virtual(Plugin::VEC0)
        ->embedding('embedding_binary')
            ->dimensions(1024)
            ->model('BAAI/bge-m3')
            ->hamming()

        ->embedding('embedding_float')
            ->dimensions(1024)
            ->model('BAAI/bge-m3')
            ->cosine(Quantization::Q4B)

        ->key('chunk_id')
        ->partition('tenant_id')
        ->metadata('document_id')
        ->auxiliary('metadata');
});
```

### Drop Virtual Tables

When dropping the source table, you should first drop the virtual tables.
This automatically cleans up the registry entry at the same time.

```php
Schema::table('document_chunks', function (Blueprint $table) {
    $table->dropVirtual(Plugin::FTS5);
    $table->dropVirtual(Plugin::VEC0);
});

Schema::dropIfExists('document_chunks');
```

### Query Virtual Tables

FTS5 and Vec0 virtual tables are created alongside their source table.
By default, an FTS5 table is named `<source>_fulltext` and a Vec0 table is named
`<source>_vectors`. Query them with SQLite's normal query builder methods:

```php
$matches = DB::table('document_chunks')
    ->select('document_chunks.*')
    ->selectRaw('bm25(document_chunks_fulltext) as content_similarity')
    ->join('document_chunks_fulltext', 'document_chunks.chunk_id', '=','document_chunks_fulltext.rowid')
    ->whereRaw('document_chunks_fulltext.content MATCH ?', [$term])
    ->orderBy('content_similarity')
    ->get();

$nearest = DB::table('document_chunks')
    ->select('document_chunks.*')
    ->addSelect('matches.distance as embedding_distance')
    ->joinSub(
        DB::table('document_chunks_vectors')
            ->select(['chunk_id', 'distance'])
            ->whereRaw('embedding MATCH ?', [json_encode($embedding, JSON_THROW_ON_ERROR)])
            ->where('k', 20),
        'matches',
        'document_chunks.chunk_id', '=', 'matches.chunk_id',
    )
    ->orderBy('embedding_distance')
    ->get();
```

### Eloquent Scopes

Add the query scopes to an Eloquent model with the plugin-specific traits:

```php
use ArtisanSdk\SQLite\FTS5\Scopes as FTS5;
use ArtisanSdk\SQLite\Vec0\Scopes as Vec0;

class DocumentChunk extends Model
{
    use FTS5, Vec0;
}

DocumentChunk::query()
    ->forFullTextSimilarTo($term)
    ->get();

DocumentChunk::query()
    ->forVectorSimilarTo($embedding)
    ->get();
```

#### Customizing Tables and Columns

The scopes join the source model table to `<source>_fulltext` or
`<source>_vectors`, return the model columns, and include the matching metric
as `<column>_score` and `<column>_relevance`.

Override `fts5Table()` or `vec0Table()` on the model when you use custom
virtual-table names, or pass the table name to the scope.

You can also customize the `fts5Column()` and `vec0Column()` to change from
`content` and `embedding` respectively as the default column names.

Alternatively you can pass in a column name as a named argument:

```php
DocumentChunk::query()
    ->forFullTextSimilarTo($term, colummn: 'data')
    ->get();

DocumentChunk::query()
    ->forVectorSimilarTo($embedding, column: 'vector')
    ->get();
```

This will result in `data_score`, `data_relevance`, `vector_score`, and
`vector_relevance` being returned as metrics in the results.

#### Filtering Results

The `filters` are applied to the filterable columns of the virtual table:

```php
DocumentChunk::query()
    ->forFullTextSimilarTo($term, filters: ['tenant' => $tenant])
    ->get();

DocumentChunk::query()
    ->forVectorSimilarTo($embedding, filters: ['tenant' => $tenant])
    ->get();
```

#### Full Text Parameters

Additional saturation `median` tuning parameters are available for
full text searches:

```php
DocumentChunk::query()
    ->forFullTextSimilarTo(
        term: $term,
        median: 5,
    )
    ->get();
```

#### Vector Parameters

Additional `similarity` threshold, KNN `limit`, `metric`, and optional Hamming
`dimensions` tuning parameters are available for vector searches:

Pass the embedding dimension when using `DistanceMetric::HAMMING` (it is not
used by the other metrics).

```php
DocumentChunk::query()
    ->forVectorSimilarTo(
        vector: $embedding,
        similarity: 0.6,
        limit: 10,
        metric: DistanceMetric::COSINE,
        dimensions: 1024,
    )
    ->get();
```

### Customize Virtual Table Names

Pass a table name as the second argument to `virtual()`. The same name must
be passed to `dropVirtual()` later:

```php
Schema::create('document_chunks', function (Blueprint $table) {
    $table->id('chunk_id');
    $table->text('content');

    $table->virtual(Plugin::FTS5, 'document_chunks_fts5')
        ->column('content');

    $table->virtual(Plugin::VEC0, 'document_chunks_vec0')
        ->embedding('embedding');
});

Schema::table('document_chunks', function (Blueprint $table) {
    $table->dropVirtual(Plugin::FTS5, 'document_chunks_fts5');
    $table->dropVirtual(Plugin::VEC0, 'document_chunks_vec0');
});
```

### Customize the Registry Table

The package records each virtual table in `virtual_table_indexes`. Set a
different registry table name before running migrations when the default name
is not suitable:

```php
use ArtisanSdk\SQLite\Registry;

Registry::setTableName('registry');
```

The registry stores the source table, plugin, and serialized configuration.
Dropping a virtual table removes its registry entry automatically.

## Docker Development

```bash
docker build -t artisansdk-sqlite .
docker run --rm --env-file .env -v "$PWD:/app" artisansdk-sqlite composer test
```

The image includes SQLite FTS5 and sqlite-vec. Run LM Studio on the host and
load the embedding model before testing. `OPENAI_MODEL` in `.env` is the
model identifier exposed by LM Studio, and `OPENAI_URL` is its endpoint.
Docker Desktop reaches the host service at `http://host.docker.internal:1234/v1`.

### Running the Tests

The package is unit tested with 100% line coverage and path coverage. You can
run the tests by simply cloning the source, installing the dependencies, and then
running `./vendor/bin/pest`. Additionally included in the developer dependencies
are some Composer scripts which can assist with Code Styling and coverage reporting:

```bash
composer check
composer coverage
composer insights
composer fix
composer test
composer retry
```

See the `composer.json` for more details on their execution and reporting output.

## Licensing

Copyright (c) 2026 [Artisan Made, Co.](http://artisanmade.io)

This package is released under the MIT license. Please see the LICENSE file
distributed with every copy of the code for commercial licensing terms.
