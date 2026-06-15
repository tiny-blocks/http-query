<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\Server\Response;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Links;
use TinyBlocks\HttpQuery\Navigation;
use TinyBlocks\HttpQuery\Pagination;
use TinyBlocks\HttpQuery\Sort;

final class Rendering
{
    private function __construct()
    {
    }

    public static function of(
        Pagination $self,
        Sort $sort,
        Collection $items,
        Filter $filter,
        string $baseUri,
        array $metadata,
        Navigation $navigation
    ): ResponseInterface {
        $links = Links::from(self: $self, sort: $sort, filter: $filter, baseUri: $baseUri, navigation: $navigation);

        return Response::ok([
            'data'  => $items->toArray(),
            'meta'  => $metadata,
            'links' => $links->toArray()
        ], $links->toHeader());
    }
}
