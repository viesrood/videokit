<?php

declare(strict_types=1);

namespace viesrood\videokit\models;

use craft\base\Model;

/**
 * A browsable group of videos in the picker sidebar: a channel's uploads, a
 * playlist, a Vimeo showcase or folder.
 *
 * The type is a fixed vocabulary rather than a method name, so a request can
 * never name the PHP method it wants a provider to run.
 */
class Collection extends Model
{
    public const TYPE_UPLOADS = 'uploads';

    public const TYPE_PLAYLIST = 'playlist';

    public const TYPE_SHOWCASE = 'showcase';

    public const TYPE_FOLDER = 'folder';

    /** @var string[] */
    public const TYPES = [
        self::TYPE_UPLOADS,
        self::TYPE_PLAYLIST,
        self::TYPE_SHOWCASE,
        self::TYPE_FOLDER,
    ];

    public string $type = self::TYPE_PLAYLIST;

    /** Provider-side id; empty for collections that need no id (uploads). */
    public string $id = '';

    public string $name = '';

    /** Number of videos, when the provider reports one. */
    public ?int $count = null;

    public static function isValidType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    /**
     * @return array{type: string, id: string, name: string, count: int|null}
     */
    public function forJs(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'name' => $this->name,
            'count' => $this->count,
        ];
    }
}
