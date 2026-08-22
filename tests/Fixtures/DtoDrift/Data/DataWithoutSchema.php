<?php

declare(strict_types=1);

namespace Tests\Fixtures\DtoDrift\Data;

use Spatie\LaravelData\Data;

class DataWithoutSchema extends Data
{
    public function __construct(public readonly string $value) {}
}
