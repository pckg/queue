<?php namespace Pckg\Queue\Entity;

use Pckg\Database\Entity;
use Pckg\Database\Repository;
use Pckg\Queue\Record\Queue as QueueRecord;

class Queue extends Entity
{

    protected $record = QueueRecord::class;

    protected $repositoryName = Repository::class . '.queue';

    public function logs()
    {
        return $this->hasMany(QueueLogs::class)
                    ->foreignKey('queue_id');
    }

    public function future()
    {
        return $this->thatWillBeExecuted();
    }

    public function current()
    {
        return $this->status('running');
    }

    public function past()
    {
        $this->thatWontBeExecuted();

        return $this->where('execute_at', date('Y-m-d H:i:s'), '<');
    }

    public function thatWontBeExecuted()
    {
        return $this->where('status', ['finished', 'failed_permanently', 'skipped_unique']);
    }

    public function thatWillBeExecuted()
    {
        return $this->where('status', ['created', 'failed']);
    }

    public function waiting()
    {
        return $this->where('execute_at', date('Y-m-d H:i:s'), '<')
                    ->where('started_at', null);
    }

    /**
     * @param $status
     *
     * @return $this
     *
     * Statuses:
     *  - manual - queue was added, waiting for nanual execution
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

    public function inLast($time)
    {
        return $this->where('finished_at', date('Y-m-d H:i:s', strtotime('-' . $time)), '>');
    }

}