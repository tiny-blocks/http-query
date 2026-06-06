<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\Http\LinkRelation;

final readonly class WebLink
{
    public function __construct(public string $uri, public LinkRelation $relation)
    {
    }
}
