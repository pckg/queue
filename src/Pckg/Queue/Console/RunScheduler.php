<?php

namespace Pckg\Queue\Console;

use Derive\Notification\Service\Notifier;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Record\Queue as QueueRecord;
use Pckg\Queue\Service\Queue;
use Pckg\Queue\Service\RabbitMQ;
use Symfony\Component\Process\Process;
use Throwable;

class RunScheduler extends Command
{

    protected function configure()
    {
        $this->setName('scheduler:run')
            ->setDescription('Run scheduler');
    }

    /**
     * @param Queue $queue
     */
    public function handle(RabbitMQ $rabbitMQ)
    {
        $rabbitMQ->makeQueue('someexchange');
        $ok = $rabbitMQ->queueMessage('somemessage', 'someexchange');
        $queues = $rabbitMQ->getQueues();

        /**
         * Get next channel with messages in the past.
         */
        $nextChannel = collect($queues)->keyBy('name')->map(function ($queue) {
            return $queue['message_stats']['publish'];
        })->removeEmpty(true)->filter(function ($queue, $name) {
            return strpos($name, 'scheduler:datetime:') === 0
                && strtotime(substr($name, strlen('scheduler:datetime:'))) <= time();
        })->sortBy(function ($queue, $name) {
            return strtotime(substr($name, strlen('scheduler:datetime:')));
        })->first();

        if (!$nextChannel) {
            $this->outputDated('Past queue is empty, exiting');
            return;
        }

        $this->outputDated('Running with go? ' . $nextChannel);
        /*(new RunRabbitWorker())->executeManually([
            'queue'    => $nextChannel,
            'exchange' => $nextChannel,
            'bind'     => $nextChannel,
        ]);*/
    }
}
