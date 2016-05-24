<?php namespace Pckg\Queue\Entity;

use Pckg\Database\Entity;
use Pckg\Queue\Record\Queue as QueueRecord;

class Queue extends Entity
{

    protected $record = QueueRecord::class;

    public function logs()
    {
        return $this->hasMany(QueueLogs::class)
            ->foreignKey('queue_id')
            ->primaryKey('id');
    }

    public function future()
    {
        return $this->where('execute_at', date('Y-m-d H:i:s'), '>');
    }

    public function current()
    {
        return $this->where('execute_at', date('Y-m-d H:i:s'));
    }

    public function past()
    {
        return $this->where('execute_at', date('Y-m-d H:i:s'), '<');
    }

    public function waiting()
    {
        return $this->where('execute_at', date('Y-m-d H:i:s'), '<')
            ->where('started_at', null);
    }

    /**
     * @param $status
     * @return $this
     *
     * Statuses:
     *  - created - queue was added, waiting for execution in future
     *  - started - queue was started, waiting for execution
     *  - running - queue is running, waiting for result
     *  - failed - queue failed, waiting for retry
     *  - failed_permanently - queue failed
     *  - finished - queue was successfully finished
     */
    public function status($status)
    {
        return $this->where('status', $status);
    }

}