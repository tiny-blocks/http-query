<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Models;

use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

final class Query
{
    private function __construct()
    {
    }

    public static function from(array $parameters): ServerRequestInterface
    {
        return new ServerRequest(uri: '/', method: 'GET')->withQueryParams($parameters);
    }
}
