<?php

declare(strict_types=1);

namespace viesrood\videokit\migrations;

use craft\db\Migration;
use viesrood\videokit\records\VideoRecord;

/**
 * Creates the metadata cache table.
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists(VideoRecord::TABLE)) {
            return true;
        }

        $this->createTable(VideoRecord::TABLE, [
            'id' => $this->primaryKey(),
            'provider' => $this->string(32)->notNull(),
            'videoId' => $this->string(64)->notNull(),
            'url' => $this->string(255)->notNull(),
            'data' => $this->mediumText(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // The unique key is the provider reference, not the URL: one video has
        // several URL forms (youtu.be/X and watch?v=X are the same video).
        $this->createIndex(null, VideoRecord::TABLE, ['provider', 'videoId'], true);
        $this->createIndex(null, VideoRecord::TABLE, ['url'], false);
        $this->createIndex(null, VideoRecord::TABLE, ['dateUpdated'], false);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(VideoRecord::TABLE);

        return true;
    }
}
