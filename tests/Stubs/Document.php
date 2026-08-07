<?php

declare(strict_types=1);

namespace Tests\Stubs;

use ArtisanSdk\SQLite\FTS5\Scopes as FTS5;
use ArtisanSdk\SQLite\Vec0\Scopes as Vec0;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use FTS5, Vec0;

    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'documents';
}
