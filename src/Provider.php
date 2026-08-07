<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\{Fluent, ServiceProvider};
use InvalidArgumentException;

class Provider extends ServiceProvider
{
    /**
     * Register the package services.
     */
    public function boot(): void
    {
        $this->registerBlueprintMacros();
        $this->registerGrammarMacros();
    }

    /**
     * Register the Blueprint macros for schema migrations.
     *
     * @example Blueprint::virtual()
     * @example Blueprint::dropVirtual()
     */
    protected function registerBlueprintMacros(): void
    {
        Blueprint::macro('virtual', function (
            Plugin|string $plugin,
            ?string $table = null,
            array $options = [],
        ): Definition {
            $plugin = $plugin instanceof Plugin
                ? $plugin
                : Plugin::from(strtolower($plugin));

            $definition = match ($plugin) {
                Plugin::FTS5 => new FTS5\Definition($table, $options),
                Plugin::VEC0 => new Vec0\Definition($table, $options),
            };

            /** @var Blueprint $this */
            $this->commands[] = $definition;

            return $definition;
        });

        Blueprint::macro('dropVirtual', function (
            Plugin|string $plugin,
            ?string $table = null,
        ): Fluent {
            $plugin = $plugin instanceof Plugin
                ? $plugin
                : Plugin::from(strtolower($plugin));

            $command = new Fluent([
                'name' => 'dropVirtual',
                'plugin' => $plugin,
                'table' => $table,
            ]);

            /** @var Blueprint $this */
            $this->commands[] = $command;

            return $command; /** @phpstan-ignore-line */
        });
    }

    /**
     * Register the Grammar macros for compiling commands into SQL.
     *
     * @example SQLiteGrammar::compileVirtual()
     * @example SQLiteGrammar::compileDropVirtual()
     *
     * @throws InvalidArgumentException when plugin is not supported
     */
    protected function registerGrammarMacros(): void
    {
        SQLiteGrammar::macro(
            'compileVirtual',
            function (
                Blueprint $blueprint,
                Fluent $command,
            ): array {
                $registry = new Registry;

                $compiler = match ($command->plugin) {
                    Plugin::FTS5 => new FTS5\Compiler($registry),
                    Plugin::VEC0 => new Vec0\Compiler($registry),
                    default => throw new InvalidArgumentException(sprintf('Plugin "%s" is not supported.', $command->plugin)),
                };

                /** @var SQLiteGrammar $this */
                return $compiler->command($this, $blueprint, $command);
            },
        );

        SQLiteGrammar::macro(
            'compileDropVirtual',
            function (
                Blueprint $blueprint,
                Fluent $command,
            ): array {
                $registry = new Registry;

                $compiler = match ($command->plugin) {
                    Plugin::FTS5 => new FTS5\Compiler($registry),
                    Plugin::VEC0 => new Vec0\Compiler($registry),
                    default => throw new InvalidArgumentException(sprintf('Plugin "%s" is not supported.', $command->plugin)),
                };

                $table = $compiler->tableName($blueprint, $command);

                $compiler->validateIdentifier($table);

                /** @var SQLiteGrammar $this */
                return [
                    sprintf(
                        'DROP TABLE IF EXISTS %s',
                        $this->wrapTable($table),
                    ),
                    $registry->unregister($table),
                ];
            },
        );
    }
}
