<?php

use yii\db\Migration;
use yii\db\Schema;

/**
 * Class m210118_095847_user_config
 */
class m210125_090343_accesstokens extends Migration
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
        $this->createTable('accesstokens', [
            'id' => Schema::TYPE_PK,
            'id_user' => Schema::TYPE_INTEGER,
            'access_token' => Schema::TYPE_STRING . ' NOT NULL',
            'refresh_token' => Schema::TYPE_STRING,
            'expiry' => Schema::TYPE_STRING,
            'scope' => Schema::TYPE_STRING,
            'state' => Schema::TYPE_STRING
        ]);

        $this->createIndex('idx-user_config-id_user', 'accesstokens', 'id_user');

        $this->addForeignKey(
            'fk-accesstokens-id_user',
            'accesstokens',
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
