<?php

declare(strict_types=1);

namespace Tests\Stubs;

class CustomDocument extends Document
{
    protected $table = 'custom_documents';

    protected function fts5Table(): string
    {
        return 'search_index';
    }

    protected function fts5Column(): string
    {
        return 'body';
    }

    protected function vec0Table(): string
    {
        return 'semantic_index';
    }

    protected function vec0Column(): string
    {
        return 'vector';
    }
}
