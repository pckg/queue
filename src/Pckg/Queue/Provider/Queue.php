<?php namespace Pckg\Queue\Provider;

use Pckg\Auth\Provider\Auth as AuthProvider;
use Pckg\Dynamic\Provider\Dynamic as DynamicProvider;
use Pckg\Framework\Provider;
use Pckg\Generic\Provider\Generic as GenericProvider;
use Pckg\Manager\Provider\Manager as ManagerProvider;
use Pckg\Queue\Console\RunQueue;
use Pckg\Queue\Console\RunRabbitTask;
use Pckg\Queue\Console\RunRabbitWorker;
use Pckg\Queue\Controller\Queue as QueueController;
use Pckg\Queue\Service\RabbitMQ;

class Queue extends Provider
{

    public function assets()
    {
        return [
            '/node_modules/chart.js/dist/Chart.min.js',
            //'footer' => [
            'js/index.js',
            //],
        ];
    }

    public function providers()
    {
        return [
            ManagerProvider::class,
            DynamicProvider::class,
            AuthProvider::class,
            GenericProvider::class,
        ];
    }

    public function routes()
    {
        return [
            'url' => [
                '/'                 => [
                    'controller' => QueueController::class,
                    'view'       => 'index',
                    'name'       => 'pckg.queue.index',
                    'tags'       => ['group:admin'],
                ],
                '/jobs'             => [
                    'controller' => QueueController::class,
                    'view'       => 'index',
                    'name'       => 'pckg.queue.index',
                    'tags'       => ['group:admin'],
                ],
                '/ajax/jobs/[type]' => [
                    'controller' => QueueController::class,
                    'view'       => 'ajax',
                    'name'       => 'pckg.queue.ajax.jobs',
                    'tags'       => ['group:admin'],
                ],
            ],
        ];
    }

    public function consoles()
    {
        return [
            RunQueue::class,
            RunRabbitTask::class,
            RunRabbitWorker::class,
        ];
    }

    public function services()
    {
        return [
            RabbitMQ::class => function() {
                $config = config('pckg.queue.provider.rabbitmq.connection', []);

                return new RabbitMQ($config);
            },
        ];
    }

}