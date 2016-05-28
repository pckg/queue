<?php namespace Pckg\Queue\Entity;

use Pckg\Database\Entity;
use Pckg\Database\Repository;
use Pckg\Queue\Record\QueueLog;

class QueueLogs extends Entity
{

    protected $record = QueueLog::class;

    protected $repositoryName = Repository::class . '.queue';

    public function queue()
    {
        return $this->belongsTo(Queue::class)
            ->foreignKey('queue_id')
            ->primaryKey('id');
    }

}