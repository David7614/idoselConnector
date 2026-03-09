<?php

namespace app\services;

use app\models\Queue;
use app\modules\xml_generator\src\XmlFeed;
use Exception;
use yii\console\ExitCode;

class QueueRunnerService
{
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
            echo "nothing to do for type " . $type . PHP_EOL;
            return ExitCode::OK;
        }

        echo '- - - Establish Queue - ID: ' . $queue->id . PHP_EOL;

        $user       = $queue->getCurrentUser();
        $parameters = $queue->additionalParameters;

        if ($queue->integrated === Queue::RUNNING && $config['forceId'] == 0) {
            echo " job still in progress " . PHP_EOL;
            echo "from " . $queue->executed_at . PHP_EOL;
            $date          = new \DateTime($queue->executed_at);
            $date2         = new \DateTime(date('Y-m-d H:i:s'));
            $diffInSeconds = $date2->getTimestamp() - $date->getTimestamp();
            echo "in seconds: " . $diffInSeconds . PHP_EOL;
            if ($diffInSeconds > 3600) {
                echo 'over hour - resetting' . PHP_EOL;
                $queue->setPendingStatus();
            }
            return ExitCode::OK;
        }

        if (!$queue->checkQueueConstraints()) {
            echo " job should not run " . PHP_EOL;
            $queue->setErrorStatus('job disabled');
            return ExitCode::OK;
        }

        $queue->setRunningStatus();

        if (!$user) {
            $queue->delete();
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $xml_generator = new XmlFeed();
        $xml_generator->setType($type);

        if (isset($config['forcePage'])) {
            $queue->page = $config['forcePage'];
        }

        $xml_generator->setQueue($queue);
        $xml_generator->setUser($user);

        try {
            $parameters = $queue->additionalParameters;
            $what       = isset($parameters['objects_done']) ? null : 'objects';

            echo "RUN with what: " . var_export($what, true) . PHP_EOL;

            $generated = $xml_generator->generate($what);

            if (!$generated) {
                $queue->setErrorStatus();
                throw new Exception('Cannot generate ' . $type . ' feed. Cannot save file');
            }

            if (isset($config['forcePage'])) {
                $queue->setErrorStatus();
                echo "page forced - stopping" . PHP_EOL;
                return ExitCode::OK;
            }

            if ($generated === 10) {
                $queue->setExecutedStatus();
                $queue->setCountErrors(0);
                echo "DONE - feed complete." . PHP_EOL;
                return ExitCode::OK;
            }

            $queue->setPendingStatus();
            $queue->setCountErrors(0);

            return ExitCode::OK;

        } catch (Exception $e) {
            echo "ERROR::" . PHP_EOL;
            echo $e->getMessage() . PHP_EOL;

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
