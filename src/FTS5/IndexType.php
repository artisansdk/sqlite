<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite\FTS5;

enum IndexType: string
{
    case FULL = 'full';
    case COLUMN = 'column';
    case NONE = 'none';
}
