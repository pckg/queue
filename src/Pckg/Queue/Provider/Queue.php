<?php

namespace Pckg\Queue\Provider;

use Pckg\Framework\Provider;
use Pckg\Queue\Console\CreateRabbitTask;
use Pckg\Queue\Console\RunQueue;
use Pckg\Queue\Console\RunRabbitTask;
use Pckg\Queue\Console\RunRabbitWorker;
use Pckg\Queue\Console\RunScheduler;
use Pckg\Queue\Service\RabbitMQ;

class Queue extends Provider
{

    public function consoles()
    {
        return [
            RunScheduler::class,
            RunQueue::class,
            RunRabbitTask::class,
            RunRabbitWorker::class,
            CreateRabbitTask::class,
        ];
    }

    public function services()
    {
        return [
            RabbitMQ::class => function () {
                $config = config('pckg.queue.provider.rabbitmq.connection', []);

                return new RabbitMQ($config);
            },
        ];
    }
}
