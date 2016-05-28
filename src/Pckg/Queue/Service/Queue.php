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
        return $this->queue->where('execute_at', date('Y-m-d H:i:s'), '<')
            ->status(['created', 'failed'])
            ->all();
    }

    public function create($command, $data = [])
    {
        $queue = new QueueRecord([
            'execute_at' => date('Y-m-d H:i:s'),
            'status'     => 'created',
            'command'    => 'php ' . path('root') . 'console ' . lcfirst(get_class(app())) . ' ' . $command . ($data ? ' --data=\'' . json_encode($data) . '\'' : ''),
        ]);
        $queue->save();

        return $queue;
    }

}