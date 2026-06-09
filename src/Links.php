<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\Link;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Internal\Uri;
use TinyBlocks\HttpQuery\Internal\WebLink;

/**
 * Navigation of a result, rendered as a JSON:API body links object or an RFC 8288 Link header.
 *
 * <p>It renders uniformly over a {@see Navigation}, building each URI from the given filter and sort
 * plus the pagination of the self link and of every target, so the filter and the sort are
 * preserved in every URI. The self link is built from the page's own current pagination, so a cursor
 * page renders a cursor self and an offset page renders an offset self.</p>
 */
final readonly class Links
{
    /**
     * @param Collection<WebLink> $links
     */
    private function __construct(private WebLink $self, private Collection $links)
    {
    }

    /**
     * Creates a Links from the sort, the page pagination, the filter, the base URI, and the navigation.
     *
     * @param Sort $sort The sort preserved in every URI.
     * @param Pagination $self The page's own current pagination, rendered as the self link.
     * @param Filter $filter The filter preserved in every URI.
     * @param string $baseUri The base URI the navigation URIs are built on.
     * @param Navigation $navigation The navigation the result exposes.
     * @return Links The navigation for the result.
     */
    public static function from(
        Sort $sort,
        Pagination $self,
        Filter $filter,
        string $baseUri,
        Navigation $navigation
    ): Links {
        $uriFor = static fn(Pagination $pagination): string => Uri::from(
            sort: $sort,
            filter: $filter,
            baseUri: $baseUri,
            pagination: $pagination
        );

        $current = new WebLink(uri: $uriFor($self), relation: LinkRelation::SELF);

        /** @var Collection<WebLink> $links */
        $links = $navigation->targets()->map(
            transformations: static fn(NavigationTarget $target): WebLink => new WebLink(
                uri: $uriFor($target->target()),
                relation: $target->relation()
            )
        );

        return new Links(self: $current, links: $links);
    }

    /**
     * Returns the navigation as the JSON:API body links object.
     *
     * @return array<string, string> The present relations mapped to their URIs, self first.
     */
    public function toArray(): array
    {
        return $this->links->reduce(
            accumulator: static fn(array $body, WebLink $link): array => [
                ...$body,
                $link->relation->value => $link->uri
            ],
            initial: [$this->self->relation->value => $this->self->uri]
        );
    }

    /**
     * Returns the navigation as an RFC 8288 Link header.
     *
     * @return Link The Link folding self plus every navigation target.
     */
    public function toHeader(): Link
    {
        return $this->links->reduce(
            accumulator: static fn(Link $carry, WebLink $link): Link => $carry->and(
                uri: $link->uri,
                relation: $link->relation
            ),
            initial: Link::to(uri: $this->self->uri, relation: $this->self->relation)
        );
    }
}
