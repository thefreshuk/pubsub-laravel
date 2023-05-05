<?php

namespace TheFresh\PubSub\Support\Database\Relations;
// original: Illuminate\Database\Eloquent\Relations;

use TheFresh\PubSub\Support\Database\Model;
use TheFresh\PubSub\Support\Database\Relations\Concerns\AsPivot;

class Pivot extends Model
{
    use AsPivot;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
