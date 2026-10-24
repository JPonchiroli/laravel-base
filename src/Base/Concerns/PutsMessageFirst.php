<?php

namespace Jponchiroli\LaravelBase\Base\Concerns;

use Illuminate\Http\JsonResponse;

trait PutsMessageFirst
{
    /**
     * Resources append "message" after "data"; move it to the top of the payload.
     */
    protected function putMessageFirst(JsonResponse $response): void
    {
        $payload = $response->getData();

        if (is_object($payload) && isset($payload->message)) {
            $response->setData((object) ['message' => $payload->message, ...get_object_vars($payload)]);
        }
    }
}
