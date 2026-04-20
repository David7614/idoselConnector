<?php

namespace app\services;

use app\models\AppConfig;
use app\models\Queue;
use app\models\QueueExecutionLog;
use app\modules\xml_generator\src\XmlFeed;
use Exception;
use yii\console\ExitCode;
use app\services\FeedDisabledException;

class QueueRunnerService
{
    const QUEUE_EMPTY = 2;
    public function runById(int $queueId): int
    {
        $queue = Queue::findOne($queueId);

        if (!$queue) {
            echo "Queue #$queueId not found." . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return $this->run($queue->integration_type, ['forceId' => $queueId]);
    }

    public function run(string $type, array $config = []): int
    {
        if (!isset($config['forceId'])) {
            $config['forceId'] = 0;
        }

        $queue = $this->determineQueue($type, $config);

        if ($queue === null) {
            echo "[{$type}] No queue found" . PHP_EOL;
            return self::QUEUE_EMPTY;
        }

        echo "[{$type}] Queue #{$queue->id} status={$queue->integrated} page={$queue->page}/{$queue->max_page}" . PHP_EOL;

        $user       = $queue->getCurrentUser();
        $parameters = $queue->additionalParameters;

        if ($queue->integrated === Queue::RUNNING && $config['forceId'] === 0) {
            $currentDate = new DateTime(date('Y-m-d H:i:s'));
            $excutedDate = new DateTime($queue->executed_at);
            $diffInSeconds = $currentDate->getTimestamp() - $excutedDate->getTimestamp();

            echo "[{$type}] Already RUNNING for {$diffInSeconds}s — skipping" . PHP_EOL;

            if ($diffInSeconds > 3600) {
                echo "[{$type}] Stale RUNNING (>1h), resetting to PENDING" . PHP_EOL;
                $queue->setPendingStatus();
            }

            return ExitCode::OK;
        }

        if (!$queue->checkQueueConstraints()) {
            echo "[{$type}] checkQueueConstraints failed — job disabled" . PHP_EOL;
            $queue->setErrorStatus('job disabled');
            return ExitCode::OK;
        }

        if (!$user) {
            $queue->delete();
            echo "[{$type}] User not found for queue #{$queue->id} — deleting queue" . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (AppConfig::isTypeStopped($type)) {
            echo "[{$type}] is globally stopped — skipping." . PHP_EOL;
            return ExitCode::OK;
        }

        $queue->setRunningStatus();

        $xml_generator = new XmlFeed();
        $xml_generator->setType($type);

        if (isset($config['forcePage'])) {
            $queue->page = $config['forcePage'];
        }

        $xml_generator->setQueue($queue);
        $xml_generator->setUser($user);

        try {
            $parameters = $queue->additionalParameters;
            $processType = isset($parameters['objects_done']) ? null : 'objects';

            echo "[{$type}] processType={$processType}" . PHP_EOL;

            $timeStart = microtime(true);
            $generated = $xml_generator->generate($processType);
            echo "[{$type}] generate() returned: {$generated}" . PHP_EOL;
            $timeEnd   = microtime(true);
            $elapsed   = $timeEnd - $timeStart;
            echo sprintf("--- TIME: %.3fs ---", $elapsed) . PHP_EOL;
            QueueExecutionLog::record(
                $queue->id,
                $user->id,
                $type,
                $what ?? 'xml',
                $elapsed,
                (int) $queue->page,
                (int) $queue->max_page
            );

            if (!$generated) {
                throw new Exception('Cannot generate ' . $type . ' feed. Cannot save file');
            }

            if (isset($config['forcePage'])) {
                echo "page forced - stopping" . PHP_EOL;
                return ExitCode::OK;
            }

            if ($generated === 10) {
                echo "[{$type}] FINISHED — setting executed status" . PHP_EOL;
                $queue->setExecutedStatus();
                $queue->setCountErrors(0);
                return ExitCode::OK;
            }
            echo "[{$type}] Partial — setting pending for next run" . PHP_EOL;
            $queue->setPendingStatus();
            $queue->setCountErrors(0);

            return ExitCode::OK;

        } catch (FeedDisabledException $e) {
            echo "[{$type}] FEED DISABLED — cancelling queue and removing future entries." . PHP_EOL;
            $queue->setDisabledStatus($e->getMessage());
            Queue::deleteFutureQueuesForUser($queue->current_integrate_user, $type);
            return ExitCode::OK;

        } catch (Exception $e) {
            echo "[{$type}] EXCEPTION: " . $e->getMessage() . PHP_EOL;

            $queue->raiseCountErrors();
            if ($queue->getCountErrors() < 30) {
                $queue->setPendingStatus();
            } else {
                $queue->setErrorStatus($e->getMessage());
            }

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    public function determineQueue(string $type, array $config = []): ?Queue
    {
        if ($config['forceId'] != 0) {
            return Queue::findOne($config['forceId']);
        }

        if (isset($config['pararel_processing']) && $config['pararel_processing']) {
            return Queue::findPararelForType($type, $config['offset']);
        }

        if (isset($config['shop_type'])) {
            return Queue::findLastForTypeAndShop($type, $config['shop_type']);
        }

        return Queue::findLastForType($type);
    }
}
