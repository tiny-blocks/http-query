<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Models;

use Nyholm\Psr7\ServerRequest;
use TinyBlocks\Http\Server\Decoded\QueryParameters;

final class Query
{
    private function __construct()
    {
    }

    public static function from(array $parameters): QueryParameters
    {
        return QueryParameters::from(request: new ServerRequest(method: 'GET', uri: '/')->withQueryParams($parameters));
    }
}
