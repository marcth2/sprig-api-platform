<?php

declare(strict_types=1);

namespace Tests\Fixtures\DtoDrift\Data;

use OpenApi\Attributes as OA;
use Spatie\LaravelData\Data;

#[OA\Schema(
    schema: 'DriftingDto',
    properties: [
        new OA\Property(property: 'phantom_field', type: 'string'),
    ],
)]
class DriftingDto extends Data
{
    public function __construct(
        #[OA\Property(property: 'matched', type: 'string')]
        public readonly string $matched,
        public readonly string $unannotated,
    ) {}
}
