<?php

namespace app\models;

use Yii;

/**
 * @property int    $id
 * @property int    $queue_id
 * @property int    $user_id
 * @property string $integration_type
 * @property string $phase
 * @property float  $execution_time
 * @property int    $page
 * @property int    $max_page
 * @property string $created_at
 */
class QueueExecutionLog extends \yii\db\ActiveRecord
{
    const MAX_RECORDS = 500;

    public static function tableName()
    {
        return 'queue_execution_log';
    }

    public static function record(int $queueId, int $userId, string $type, string $phase, float $time, int $page, int $maxPage): void
    {
        $log                   = new self();
        $log->queue_id         = $queueId;
        $log->user_id          = $userId;
        $log->integration_type = $type;
        $log->phase            = $phase;
        $log->execution_time   = round($time, 3);
        $log->page             = $page;
        $log->max_page         = $maxPage;
        $log->created_at       = date('Y-m-d H:i:s');
        $log->save(false);

        // Zachowaj tylko ostatnie MAX_RECORDS rekordów
        $count = self::find()->count();
        if ($count > self::MAX_RECORDS) {
            $oldest = self::find()
                ->orderBy(['id' => SORT_ASC])
                ->limit($count - self::MAX_RECORDS)
                ->all();
            foreach ($oldest as $old) {
                $old->delete();
            }
        }
    }

    public static function getRecentStats(int $limit = 100): array
    {
        return self::find()
            ->orderBy(['created_at' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    public static function getSummaryByType(): array
    {
        return Yii::$app->db->createCommand('
            SELECT
                integration_type,
                phase,
                COUNT(*)            AS cnt,
                AVG(execution_time) AS avg_time,
                MIN(execution_time) AS min_time,
                MAX(execution_time) AS max_time,
                AVG(max_page)       AS avg_pages
            FROM queue_execution_log
            GROUP BY integration_type, phase
            ORDER BY integration_type, phase
        ')->queryAll();
    }
}
