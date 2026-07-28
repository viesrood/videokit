<?php

declare(strict_types=1);

namespace viesrood\videokit\models;

use craft\base\Model;

/**
 * One page of videos plus a cursor to the next one.
 *
 * The cursor is deliberately opaque: YouTube pages with a token, Vimeo with a
 * number. Callers (including the picker JS) only ever hand it back unchanged.
 */
class VideoResults extends Model
{
    /** @var Video[] */
    public array $videos = [];

    /** Cursor for the next page, or null when this was the last one. */
    public ?string $nextPage = null;

    /** Total number of videos, when the provider reports one. */
    public ?int $total = null;

    /**
     * @param Video[] $videos
     */
    public static function create(array $videos, ?string $nextPage = null, ?int $total = null): self
    {
        $results = new self();
        $results->videos = $videos;
        $results->nextPage = $nextPage;
        $results->total = $total;

        return $results;
    }

    /**
     * @return array{videos: array<int, array<string, mixed>>, nextPage: string|null, total: int|null}
     */
    public function forJs(): array
    {
        return [
            'videos' => array_map(static fn(Video $video): array => $video->forJs(), $this->videos),
            'nextPage' => $this->nextPage,
            'total' => $this->total,
        ];
    }
}
