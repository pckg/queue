<?php namespace Pckg\Queue\Provider;

use Pckg\Framework\Provider;
use Pckg\Generic\Middleware\EncapsulateResponse;
use Pckg\Queue\Console\RunQueue;
use Pckg\Queue\Controller\Queue as QueueController;
use Pckg\Auth\Provider\Config as AuthProvider;
use Pckg\Dynamic\Provider\Config as DynamicProvider;
use Pckg\Framework\Application;
use Pckg\Generic\Provider\Config as GenericProvider;
use Pckg\Manager\Provider\Config as ManagerProvider;

class Queue extends Provider
{

    public function assets()
    {
        return [
            'footer' => [
                'js/index.js',
            ],
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
                '/'     => [
                    'controller' => QueueController::class,
                    'view'       => 'index',
                    'name'       => 'pckg.queue.index',
                ],
                '/jobs' => [
                    'controller' => QueueController::class,
                    'view'       => 'index',
                    'name'       => 'pckg.queue.index',
                ],
                '/ajax/jobs/[type]' => [
                    'controller' => QueueController::class,
                    'view'       => 'ajax',
                    'name'       => 'pckg.queue.ajax.jobs',
                ],
            ],
        ];
    }

    public function afterwares()
    {
        return [
            EncapsulateResponse::class,
        ];
    }

    public function consoles()
    {
        return [
            RunQueue::class,
        ];
    }

}