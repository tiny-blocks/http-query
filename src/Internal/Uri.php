<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Internal\Rsql\Renderer;
use TinyBlocks\HttpQuery\Pagination;
use TinyBlocks\HttpQuery\Sort;

final class Uri
{
    private function __construct()
    {
    }

    public static function from(Sort $sort, Filter $filter, string $baseUri, Pagination $pagination): string
    {
        $parameters = [];

        if (!$filter->isEmpty()) {
            $template = 'filter=%s';
            $parameters[] = sprintf($template, Uri::encode(value: Renderer::from(filter: $filter)));
        }

        if (!$sort->isEmpty()) {
            $template = 'sort=%s';
            $parameters[] = sprintf($template, Uri::encode(value: $sort->toExpression()));
        }

        $parameters[] = $pagination->toQueryString();
        $template = '%s?%s';

        return sprintf($template, $baseUri, implode('&', $parameters));
    }

    private static function encode(string $value): string
    {
        return strtr(rawurlencode($value), [
            '%3D' => '=',
            '%3B' => ';',
            '%2C' => ',',
            '%28' => '(',
            '%29' => ')',
            '%21' => '!'
        ]);
    }
}
