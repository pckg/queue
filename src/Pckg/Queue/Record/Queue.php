<?php namespace Pckg\Queue\Record;

use Pckg\Database\Record;
use Pckg\Queue\Entity\Queue as QueueEntity;

class Queue extends Record
{

    protected $entity = QueueEntity::class;

    public function changeStatus($status, $log = [])
    {
        $this->status = $status;

        $datetimes = [
            'running'  => 'started_at',
            'finished' => 'finished_at',
        ];
        if (isset($datetimes[$status])) {
            $this->{$datetimes[$status]} = date('Y-m-d H:i:s');
        }

        $this->save();

        $log = new QueueLog(array_merge($log, [
            'queue_id' => $this->id,
            'datetime' => date('Y-m-d H:i:s'),
            'status'   => $status,
        ]));
        $log->save();
    }

}