<?php

use yii\db\Migration;

class m260328_160000_utf8mb4_full_database extends Migration
{
    public function safeUp()
    {
        $db = \Yii::$app->db;
        $dbName = $db->createCommand('SELECT DATABASE()')->queryScalar();

        $originalMode = $db->createCommand('SELECT @@sql_mode')->queryScalar();
        $this->execute("SET sql_mode = ''");

        $this->execute("ALTER DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $tables = $db->createCommand("SHOW TABLES")->queryColumn();
        foreach ($tables as $table) {
            $this->execute("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        $this->execute("SET sql_mode = '{$originalMode}'");
    }

    public function safeDown()
    {
        echo "safeDown not supported for full charset migration.\n";
        return false;
    }
}
