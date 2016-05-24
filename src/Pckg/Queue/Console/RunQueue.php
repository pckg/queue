<?php namespace Pckg\Queue\Console;

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
            $queue->changeStatus('started');
        }, false);

        /**
         * Execute jobs.
         */
        $waitingQueue->each(function (QueueRecord $queue) {
            $queue->changeStatus('running');

            exec($queue->command, $output);

            $queue->changeStatus('finished', [
                'log' => $output,
            ]);
        }, false);
    }

}