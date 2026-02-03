<?php

use yii\db\Migration;
use yii\db\Schema;

/**
 * Class m210118_095832_user
 */
class m210118_095832_user extends Migration
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
        echo "m210118_095832_user cannot be reverted.\n";

        return false;
    }

    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        $this->createTable('user', [
            'id' => Schema::TYPE_PK,
            'username' => Schema::TYPE_STRING . ' NOT NULL',
            'email' => Schema::TYPE_STRING . ' NOT NULL',
            'password' => Schema::TYPE_STRING . ' NOT NULL',
            'register_date' => Schema::TYPE_DATETIME . ' NOT NULL',
            'active' => Schema::TYPE_TINYINT,
            'registerToken' => Schema::TYPE_STRING . ' NOT NULL',
            'client_id' => Schema::TYPE_STRING . ' NOT NULL',
            'client_secret' => Schema::TYPE_STRING . ' NOT NULL'
        ]);
    }

    public function down()
    {
        echo "m210118_095832_user cannot be reverted.\n";

        return false;
    }
}
