<?php

namespace Jponchiroli\LaravelBase\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Jponchiroli\LaravelBase\Base\Concerns\PutsMessageFirst;

class BaseCollection extends AnonymousResourceCollection
{
    use PutsMessageFirst;

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return ['message' => __('laravel-base::messages.success.index')];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $this->putMessageFirst($response);
    }

    /**
     * Only the paging summary, without the "links" block and the page buttons.
     *
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'to' => $paginated['to'] ?? null,
                'from' => $paginated['from'] ?? null,
                'total' => $paginated['total'] ?? null,
                'per_page' => $paginated['per_page'] ?? null,
                'last_page' => $paginated['last_page'] ?? null,
                'current_page' => $paginated['current_page'] ?? null,
            ],
        ];
    }
}
