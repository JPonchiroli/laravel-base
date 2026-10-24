<?php

namespace Jponchiroli\LaravelBase\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Jponchiroli\LaravelBase\Base\Concerns\PutsMessageFirst;

class BaseResource extends JsonResource
{
    use PutsMessageFirst;

    protected string $messageKey = 'show';

    public function __construct(mixed $resource, ?string $messageKey = null)
    {
        parent::__construct($resource);

        $this->messageKey = $messageKey ?? $this->messageKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return ['message' => __('laravel-base::messages.success.'.$this->messageKey)];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $this->putMessageFirst($response);
    }

    protected static function newCollection(mixed $resource): AnonymousResourceCollection
    {
        return new BaseCollection($resource, static::class);
    }
}
