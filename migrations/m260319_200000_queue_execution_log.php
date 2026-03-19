<?php

use yii\db\Migration;

class m260319_200000_queue_execution_log extends Migration
{
    public function safeUp()
    {
        $this->createTable('queue_execution_log', [
            'id'               => $this->primaryKey(),
            'queue_id'         => $this->integer()->notNull(),
            'user_id'          => $this->integer()->notNull(),
            'integration_type' => $this->string(255)->notNull(),
            'phase'            => $this->string(20)->notNull(),
            'execution_time'   => $this->float()->notNull(),
            'page'             => $this->integer()->notNull()->defaultValue(0),
            'max_page'         => $this->integer()->notNull()->defaultValue(0),
            'created_at'       => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_qel_created_at', 'queue_execution_log', 'created_at');
        $this->createIndex('idx_qel_type_user',  'queue_execution_log', ['integration_type', 'user_id']);
    }

    public function safeDown()
    {
        $this->dropTable('queue_execution_log');
    }
}
