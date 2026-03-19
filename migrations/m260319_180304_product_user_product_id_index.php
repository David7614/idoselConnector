<?php

use yii\db\Migration;

class m260319_180304_product_user_product_id_index extends Migration
{
    public function safeUp()
    {
        $this->createIndex(
            'idx_product_user_product_id',
            'product',
            ['user_id', 'PRODUCT_ID']
        );
    }

    public function safeDown()
    {
        $this->dropIndex('idx_product_user_product_id', 'product');
    }
}
