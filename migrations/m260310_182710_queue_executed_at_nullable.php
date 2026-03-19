<?php

use yii\db\Migration;

class m260310_182710_queue_executed_at_nullable extends Migration
{
    public function up()
    {
        $this->alterColumn('xml_feed_queue', 'executed_at', $this->dateTime()->null()->defaultValue(null));
    }

    public function down()
    {
        $this->alterColumn('xml_feed_queue', 'executed_at', $this->dateTime()->notNull()->defaultValue('1970-01-01 00:00:00'));
    }
}
