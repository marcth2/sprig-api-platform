<?php

declare(strict_types=1);

namespace Tests\Fixtures\DtoDrift\Data;

class PlainNonDataClass
{
    public function __construct(public readonly string $value) {}
}
