<?php namespace Pckg\Queue\Entity;

use Pckg\Database\Entity;
use Pckg\Queue\Record\QueueLog;

class QueueLogs extends Entity
{

    protected $record = QueueLog::class;

    public function queue()
    {
        return $this->belongsTo(Queue::class)
            ->foreignKey('queue_id')
            ->primaryKey('id');
    }

}