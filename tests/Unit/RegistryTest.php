<?php

declare(strict_types=1);

use ArtisanSdk\SQLite\Registry;

afterEach(function (): void {
    Registry::setTableName('virtual_table_indexes');
});

it('uses the configured registry table name', function (): void {
    Registry::setTableName('foo_indexes');

    expect(Registry::schema())
        ->toStartWith('CREATE TABLE IF NOT EXISTS "foo_indexes"');
});

it('builds the delete statement used to unregister a virtual table', function (): void {
    Registry::setTableName('foo_indexes');

    expect((new Registry)->unregister('foo_vectors'))
        ->toBe("DELETE FROM \"foo_indexes\" WHERE \"table\" = 'foo_vectors'");
});
