<?php

namespace TheFresh\PubSub\Support\Container\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    //
}
