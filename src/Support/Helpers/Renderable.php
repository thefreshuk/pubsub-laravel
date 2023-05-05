<?php

namespace TheFresh\PubSub\Support\Helpers;

interface Renderable
{
    /**
     * Get the evaluated contents of the object.
     *
     * @return string
     */
    public function render();
}
