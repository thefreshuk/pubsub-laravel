<?php

namespace TheFresh\PubSub\Console;

use Illuminate\Console\GeneratorCommand;

class MessageMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:message {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new PubSub message';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'PubSub message';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/message.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace.'\PubSub\Messages';
    }
}
