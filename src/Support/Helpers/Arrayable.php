<?php

namespace TheFresh\PubSub\Support\Helpers;
// Original: Illuminate\Contracts\Support\Arrayable;


/**
 * @template TKey of array-key
 * @template TValue
 */
interface Arrayable
{
    /**
     * Get the instance as an array.
     *
     * @return array<TKey, TValue>
     */
    public function toArray();
}
