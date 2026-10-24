<?php

namespace Jponchiroli\LaravelBase\Base;

use Illuminate\Http\Request;

class BaseMessageResource extends BaseResource
{
    public static $wrap = null;

    protected string $messageKey = 'destroy';

    public function __construct(?string $messageKey = null)
    {
        parent::__construct(null, $messageKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['message' => __('laravel-base::messages.success.'.$this->messageKey)];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [];
    }
}
