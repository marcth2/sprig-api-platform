<?php

declare(strict_types=1);

namespace App\Shared\Data;

use OpenApi\Attributes as OA;
use Spatie\LaravelData\Data;

#[OA\Schema(
    schema: 'ErrorResponse',
    description: 'Standard error envelope returned for 4xx and 5xx responses.',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unknown service'),
    ],
    type: 'object',
)]
class ApiErrorData extends Data
{
    public function __construct(public readonly string $message) {}
}
