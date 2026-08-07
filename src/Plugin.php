<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite;

enum Plugin: string
{
    case FTS5 = 'fts5';
    case VEC0 = 'vec0';
}
