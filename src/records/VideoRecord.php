<?php

declare(strict_types=1);

namespace viesrood\videokit\records;

use craft\db\ActiveRecord;

/**
 * Cached video metadata.
 *
 * This table is a cache, not a source of truth: the element itself stores the
 * provider reference, so every row here can be thrown away and rebuilt.
 *
 * @property int $id
 * @property string $provider
 * @property string $videoId
 * @property string $url
 * @property string|null $data
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class VideoRecord extends ActiveRecord
{
    public const TABLE = '{{%videokit_videos}}';

    public static function tableName(): string
    {
        return self::TABLE;
    }
}
