<?php

namespace Jponchiroli\LaravelBase\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BaseStoreResource extends BaseResource
{
    protected string $messageKey = 'store';

    public function withResponse(Request $request, JsonResponse $response): void
    {
        parent::withResponse($request, $response);

        $response->setStatusCode(Response::HTTP_CREATED);
    }
}
