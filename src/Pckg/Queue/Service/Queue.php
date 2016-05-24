<?php namespace Pckg\Queue\Service;

use Pckg\Queue\Entity\Queue as QueueEntity;
use Pckg\Queue\Record\Queue as QueueRecord;

class Queue
{

    /**
     * @var QueueEntity
     */
    protected $queue;

    public function __construct(QueueEntity $queue)
    {
        $this->queue = $queue;
    }

    public function getWaiting()
    {
        return $this->queue->waiting()
            ->status(['created', 'failed'])
            ->all();
    }

    public function create($command)
    {
        $queue = new QueueRecord([
            'execute_at' => date('Y-m-d H:i:s'),
            'status'     => 'created',
            'command'    => $command,
        ]);
        $queue->save();

        return $queue;
    }

}