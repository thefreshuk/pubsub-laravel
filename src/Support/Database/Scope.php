<?php

namespace TheFresh\PubSub\Support\Database;
// original:  Illuminate\Database\Eloquent;

interface Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \TheFresh\PubSub\Support\Database\Builder  $builder
     * @param  \TheFresh\PubSub\Support\Database\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model);
}
