<?php

declare(strict_types=1);

namespace App\Shared\Data;

use OpenApi\Attributes as OA;
use Spatie\LaravelData\Data;

#[OA\Schema(
    schema: 'ErrorResponse',
    description: 'Standard error envelope returned for 4xx and 5xx responses.',
    required: ['message', 'status'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unknown service'),
        new OA\Property(
            property: 'status',
            description: 'HTTP status code mirroring the response status.',
            type: 'integer',
            example: 404,
        ),
        new OA\Property(
            property: 'errors',
            description: 'Field-level validation errors, present only for 422 responses.',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties,
        ),
    ],
    type: 'object',
)]
class ApiErrorData extends Data
{
    /**
     * @param array<string, array<int, string>>|null $errors
     */
    public function __construct(
        public readonly string $message,
        public readonly int $status,
        public readonly ?array $errors = null,
    ) {}
}
