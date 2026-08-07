<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Fluent;
use Laravel\Ai\{AiServiceProvider, Embeddings};
use Tests\Stubs\Document;

beforeEach(function (): void {
    $app = Container::getInstance();
    $model = env('OPENAI_MODEL');

    expect($model)->toBeString()->not->toBeEmpty();

    $app->instance('config', new Fluent([
        'ai' => [
            'default' => 'openai',
            'default_for_embeddings' => 'openai',
            'providers' => [
                'openai' => [
                    'driver' => 'openai',
                    'key' => null,
                    'url' => env('OPENAI_SERVER_URL', 'http://host.docker.internal:1234/v1'),
                    'models' => [
                        'embeddings' => [
                            'default' => $model,
                            'dimensions' => 1024,
                        ],
                    ],
                ],
            ],
        ],
    ]));
    $app->instance(Http::class, new Http($app->make(Illuminate\Contracts\Events\Dispatcher::class)));

    (new AiServiceProvider($app))->register();
});

it('finds semantically similar text with vector relevance', function (): void {
    $content = [
        'The lighthouse archive preserves the cobalt moonflower protocol.',
        'Fresh pasta needs flour, eggs, and a little patience.',
        'A bicycle chain should be cleaned before applying lubricant.',
        'Solar panels produce electricity from sunlight.',
    ];

    $query = 'Where is the moonflower archive stored?';

    $embeddings = Embeddings::for([...$content, $query])
        ->dimensions(1024)
        ->timeout(30)
        ->generate();

    foreach ($content as $index => $paragraph) {
        $document = Document::query()->create([
            'content' => $paragraph,
            'embedding' => json_encode($embeddings->embeddings[$index], JSON_THROW_ON_ERROR), // only the content embeddings
        ]);

        $document->getConnection()->table('documents_vectors')->insert([
            'id' => $document->getKey(),
            'embedding' => $document->embedding,
        ]);
    }

    $document = Document::query()
        ->forVectorSimilarTo(
            last($embeddings->embeddings), // query embedding
            order: 'embedding_score',
            similarity: 0.5,
            limit: 1,
        )
        ->sole();

    expect($document->content)
        ->toBe($content[0])
        ->and($document->embedding_relevance)
        ->toBeGreaterThan(0)
        ->toBeLessThanOrEqual(1);
});
