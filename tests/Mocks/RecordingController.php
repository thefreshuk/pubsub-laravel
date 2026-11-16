<?php

namespace Tests\Mocks;

use Illuminate\Http\Request;

class RecordingController
{
    public static array $received = [];

    public function onTestType(Request $request)
    {
        static::$received[] = $request->message;

        return response('controller response', 202);
    }
}
