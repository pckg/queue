<?php namespace Pckg\Queue\Record;

use Pckg\Database\Record;
use Pckg\Queue\Entity\Queue as QueueEntity;

class Queue extends Record
{

    protected $entity = QueueEntity::class;

    protected $toArray = [
        'shortCommand',
    ];

    public function getShortCommand()
    {
        return implode(' ', array_map(function ($string) {
            if (strlen($string) > 13 && (!strpos($string, ':') || strpos($string, '{'))) {
                return substr($string, 0, 5) . '...' . substr($string, -5);
            }

            return $string;
        }, explode(' ', $this->command)));
    }

    public function changeStatus($status, $log = [])
    {
        $this->status = $status;

        $this->setDatetimeByStatus();
        $this->setPercentageByStatus();
        $this->createLog($log);

        $this->save();
    }

    public function createLog($log = [])
    {
        $log = new QueueLog(array_merge($log, [
            'queue_id' => $this->id,
            'datetime' => date('Y-m-d H:i:s'),
            'status'   => $this->status,
        ]));
        $log->save();
    }

    public function setDatetimeByStatus($status = null)
    {
        if (!$status) {
            $status = $this->status;
        }

        $datetimes = [
            'running'  => 'started_at',
            'finished' => 'finished_at',
        ];

        if (isset($datetimes[$status])) {
            $this->{$datetimes[$status]} = date('Y-m-d H:i:s');
        }
    }

    public function setPercentageByStatus($status = null)
    {
        if (!$status) {
            $status = $this->status;
        }

        $percentages = [
            'running'  => 0,
            'finished' => 100,
        ];

        if (isset($percentages[$status])) {
            $this->percentage = $percentages[$status];
        }
    }

}