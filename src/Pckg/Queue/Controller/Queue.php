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
        ]);
    }

}