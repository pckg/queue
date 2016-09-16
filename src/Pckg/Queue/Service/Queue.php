<?php namespace Pckg\Queue\Service;

use Pckg\Database\Query\Raw;
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
        return $this->queue->status($status)
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
        return $this->queue->status(['finished', 'failed_permanently', 'skipped_unique'])->withLogs()->orderBy(
            'execute_at DESC'
        )->count()->limit(10)->all();
    }

    public function getStarted()
    {
        return $this->queue->status(['started'])->withLogs()->count()->all();
    }

    public function getWaiting()
    {
        return $this->queue->where('execute_at', date('Y-m-d H:i:s'), '<')
                           ->where(
                               Raw::raw(
                                   'waiting_id IS NULL OR waiting_id IN (SELECT id FROM queue WHERE status = \'finished\')'
                               )
                           )
                           ->status(['created', 'failed'])
                           ->all();
    }

    /**
     * @param       $command
     * @param array $data
     *
     * @return QueueRecord
     */
    public function create($command, $data = [])
    {
        $platformName = context()->getOrDefault('platformName');

        $queue = new QueueRecord(
            [
                'execute_at' => date('Y-m-d H:i:s'),
                'status'     => 'created',
                'command'    => 'php ' . path('root') . 'console ' . lcfirst(get_class(app())) . ' ' .
                                ($platformName ? $platformName . ' ' . '') .
                                $command . ($data ? ' --data=\'' . json_encode($data) . '\'' : ''),
            ]
        );
        $queue->save();

        return $queue;
    }

}