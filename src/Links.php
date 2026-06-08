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
 * <p>It renders uniformly over a {@see Navigation}, building each URI from the criteria's filter and
 * sort plus the pagination of the self link and of every target, so the filter and the sort are
 * preserved in every URI.</p>
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
     * Creates a Links from the base URI, the criteria, and the navigation of a result.
     *
     * @param string $baseUri The base URI the navigation URIs are built on.
     * @param Criteria $criteria The criteria that produced the result.
     * @param Navigation $navigation The navigation the result exposes.
     * @return Links The navigation for the result.
     */
    public static function from(string $baseUri, Criteria $criteria, Navigation $navigation): Links
    {
        $uriFor = static fn(Pagination $pagination): string => Uri::from(
            sort: $criteria->sorting(),
            filter: $criteria->filtering(),
            baseUri: $baseUri,
            pagination: $pagination
        );

        $self = new WebLink(uri: $uriFor($criteria->pagination()), relation: LinkRelation::SELF);

        /** @var Collection<WebLink> $links */
        $links = $navigation->targets()->map(
            transformations: static fn(NavigationTarget $target): WebLink => new WebLink(
                uri: $uriFor($target->target()),
                relation: $target->relation()
            )
        );

        return new Links(self: $self, links: $links);
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
