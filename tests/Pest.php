<?php

declare(strict_types=1);

use ArtisanSdk\SQLite\{Plugin, Provider};
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;

if (! function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $config = Container::getInstance()->make('config');

        if ($key === null) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

pest()
    ->in('Feature')
    ->beforeEach(function (): void {
        $app = new Container;
        $events = new Dispatcher($app);

        Container::setInstance($app);
        Facade::setFacadeApplication($app);

        $app->instance('events', $events);
        $app->instance(Dispatcher::class, $events);
        $app->instance(DispatcherContract::class, $events);

        (new Provider($app))->boot();

        $db = new Manager;
        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $db->setAsGlobal();
        $db->bootEloquent();

        $db->schema()->create('documents', function (Blueprint $table): void {
            $table->id();
            $table->text('content');
            $table->text('embedding')->default('[]');

            $table->virtual(Plugin::FTS5)
                ->column('content')
                ->key();

            $table->virtual(Plugin::VEC0)
                ->embedding('embedding', 1024)
                ->key();
        });
    });

pest()
    ->in('Unit')
    ->beforeEach(function (): void {
        $db = new Manager;
        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $db->setAsGlobal();
        $db->bootEloquent();
    });
