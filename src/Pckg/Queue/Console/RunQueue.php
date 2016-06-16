<?php namespace Pckg\Queue\Console;

use Exception;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Record\Queue as QueueRecord;
use Pckg\Queue\Service\Queue;

class RunQueue extends Command
{

    protected function configure()
    {
        $this->setName('queue:run')
            ->setDescription('Run waiting queue');
    }

    /**
     * @param Queue $queue
     */
    public function handle(Queue $queueService)
    {
        $waitingQueue = $queueService->getWaiting();

        /**
         * Set queue as started, we'll execute it later.
         */
        $waitingQueue->each(function (QueueRecord $queue) {
            $this->output('#' . $queue->id . ': ' . 'started (' . date('Y-m-d H:i:s') . ')');
            $queue->changeStatus('started');
        }, false);

        /**
         * Execute jobs.
         */
        $waitingQueue->each(function (QueueRecord $queue) {
            $this->output('#' . $queue->id . ': ' . 'running (' . date('Y-m-d H:i:s') . ')');
            $queue->changeStatus('running');

            $this->output('#' . $queue->id . ': ' . $queue->command);
            $output = null;
            try {
                $timeout = strtotime($queue->execute_at) - time();
                if ($timeout > 0) {
                    exec('timeout -k 60 ' . $timeout . ' ' . $queue->command, $output);

                } else {
                    exec($queue->command, $output);

                }
            } catch (Exception $e) {
                $queue->changeStatus('failed', [
                    'log' => exception($e),
                ]);

                return;
            }

            if (!$output) {
                $queue->changeStatus('failed', [
                    'log' => 'No output',
                ]);

                return;
            }

            $this->output('#' . $queue->id . ': ' . 'finished (' . date('Y-m-d H:i:s') . ')');
            $queue->changeStatus('finished', [
                'log' => implode("\n", $output),
            ]);
        }, false);
    }

}