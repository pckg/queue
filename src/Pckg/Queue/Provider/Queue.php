<?php namespace Pckg\Queue\Provider;

use Pckg\Framework\Provider;
use Pckg\Generic\Middleware\EncapsulateResponse;
use Pckg\Queue\Controller\Queue as QueueController;
use Pckg\Auth\Provider\Config as AuthProvider;
use Pckg\Dynamic\Provider\Config as DynamicProvider;
use Pckg\Framework\Application;
use Pckg\Generic\Provider\Config as GenericProvider;
use Pckg\Manager\Provider\Config as ManagerProvider;

class Queue extends Provider
{

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
                '/jobs' => [
                    'controller' => QueueController::class,
                    'view'       => 'index',
                    'name'       => 'pckg.queue.index',
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

}