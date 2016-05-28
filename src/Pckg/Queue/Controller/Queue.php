<?php namespace Pckg\Queue\Controller;

use Pckg\Framework\Controller;
use Pckg\Queue\Entity\Queue as QueueEntity;

class Queue extends Controller
{

    public function getIndexAction(QueueEntity $queue)
    {
        return view('queue/index', [
            'nextQueue'    => $queue->future()->withLogs()->all(),
            'currentQueue' => $queue->current()->withLogs()->all(),
            'prevQueue'    => $queue->past()->withLogs()->all(),
            'stat'         => [
                'successful24h'        => $queue->past()
                    ->status('finished')
                    ->inLast('1 day')
                    ->all()
                    ->count(),
                'failedPermanently24h' => $queue->past()
                    ->status('failed_permanently')
                    ->inLast('1 day')
                    ->all()
                    ->count(),
                'currentlyRunning'     => $queue->status('running')
                    ->all()
                    ->count(),
            ],
        ]);
    }

    public function getAjaxAction(QueueEntity $queues, $type)
    {
        return [
            'queues' => $queues->{$type}()->withLogs()->all(),
        ];
    }

}