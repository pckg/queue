<?php namespace Pckg\Queue\Controller;

use Pckg\Framework\Controller;
use Pckg\Queue\Entity\Queue as QueueEntity;

class Queue extends Controller
{

    public function getIndexAction(QueueEntity $queues)
    {
        return view('queue/index', [
            'nextQueue'    => $queues->future()->all(),
            'currentQueue' => $queues->current()->all(),
            'prevQueue'    => $queues->past()->all(),
            'stat'         => [
                'successful24h'        => $queues->past()
                    ->status('finished')
                    ->inLast('1 day')
                    ->all()
                    ->count(),
                'failedPermanently24h' => $queues->past()
                    ->status('failed_permanently')
                    ->inLast('1 day')
                    ->all()
                    ->count(),
                'currentlyRunning'     => $queues->status('running')
                    ->all()
                    ->count(),
            ],
        ]);
    }

    public function getAjaxAction(QueueEntity $queues, $type)
    {
        return [
            'queues' => $queues->{$type}()->all(),
        ];
    }

}