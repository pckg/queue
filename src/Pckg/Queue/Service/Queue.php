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

    public function getTotalByStatusAndTime($status, $time)
    {
        return $this->queue->past()
            ->status($status)
            ->inLast($time)
            ->total();
    }

    public function getNext()
    {
        return $this->queue->future()->withLogs()->orderBy('execute_at ASC')->count()->all();
    }

    public function getCurrent()
    {
        return $this->queue->current()->withLogs()->orderBy('started_at DESC')->count()->all();;
    }

    public function getPrev()
    {
        return $this->queue->past()->withLogs()->orderBy('finished_at DESC')->count()->limit(10)->all();
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
            'status' => 'created',
            'command' => 'php ' . path('root') . 'console ' . lcfirst(get_class(app())) . ' ' . $command . ($data ? ' --data=\'' . json_encode($data) . '\'' : ''),
        ]);
        $queue->save();

        return $queue;
    }

}