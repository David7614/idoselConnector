<?php

use yii\db\Migration;
use yii\db\Schema;

/**
 * Class m210118_095847_user_config
 */
class m210118_095847_user_config extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210118_095847_user_config cannot be reverted.\n";

        return false;
    }

    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        $this->createTable('user_config', [
            'id' => Schema::TYPE_PK,
            'id_user' => Schema::TYPE_INTEGER,
            'key' => Schema::TYPE_STRING . ' NOT NULL',
            'value' => Schema::TYPE_STRING,
        ]);

        $this->createIndex('idx-user_config-id_user', 'user_config', 'id_user');

        $this->addForeignKey(
            'fk-user_config-id_user',
            'user_config',
            'id_user',
            'user',
            'id',
            'CASCADE'
        );
    }

    public function down()
    {
        echo "m210118_095847_user_config cannot be reverted.\n";

        return false;
    }
}
