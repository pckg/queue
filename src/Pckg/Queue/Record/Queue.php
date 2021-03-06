<?php

namespace Pckg\Queue\Record;

use Pckg\Database\Record;
use Pckg\Queue\Entity\Queue as QueueEntity;

/**
 * Class Queue
 * @package Pckg\Queue\Record
 * @property string $command
 * @property string $log
 * @property string $status
 * @property float $progress
 * @property float $percentage
 * @property string $finished_at
 * @property string $started_at
 * @property string $execute_at
 * @property int|null $waiting_id
 */
class Queue extends Record
{

    protected $entity = QueueEntity::class;

    protected $toArray = [
        'shortCommand',
        'shortLog',
        'logs',
    ];

    public function getShortCommandAttribute()
    {
        return implode(
            ' ',
            array_map(
                function ($string) {
                    if (strlen($string) > 13 && (!strpos($string, ':') || strpos($string, '{'))) {
                        return substr($string, 0, 5) . '...' . substr($string, -5);
                    }

                    return $string;
                },
                explode(' ', $this->command)
            )
        );
    }

    public function getShortLogAttribute()
    {
        return substr($this->log, 0, 32) . (strlen($this->log) > 32 ? '...' : '');
    }

    public function changeStatus($status, $log = [])
    {
        $this->status = $status;

        if (isset($log['log'])) {
            $this->log = $log['log'] . "\n\n" . $this->log;
        }

        if (isset($log['progress'])) {
            $this->progress = $log['progress'];
        }

        $this->setDatetimeByStatus();
        $this->setPercentageByStatus();

        $this->save();

        $this->createLog($log);
    }

    public function createLog($log = [])
    {
        $log = new QueueLog(
            array_merge(
                $log,
                [
                    'queue_id' => $this->id,
                    'datetime' => date('Y-m-d H:i:s'),
                    'status' => $this->status,
                ]
            )
        );
        $log->save();
    }

    public function setDatetimeByStatus($status = null)
    {
        if (!$status) {
            $status = $this->status;
        }

        $datetimes = [
            'running' => 'started_at',
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
            'running' => 0,
            'finished' => 100,
        ];

        if (isset($percentages[$status])) {
            $this->percentage = $percentages[$status];
        }
    }

    public function makeUniqueInFuture()
    {
        (new QueueEntity())->status('created')
            ->where('id', $this->id, '!=')
            ->where('command', $this->command)
            ->all()
            ->each(
                function (Queue $record) {
                    $record->changeStatus('skipped_unique');
                }
            );
    }

    /**
     * @param $command
     * @param $timeout
     *
     * @return $this
     */
    public function makeTimeoutAfterLast($command, $timeout)
    {
        $last = (new QueueEntity())->status('created')
            ->where('id', $this->id, '!=')
            ->where('command', '%' . $command . '%', 'LIKE')
            ->orderBy('execute_at DESC')
            ->one();

        if ($last) {
            $this->execute_at = date('Y-m-d H:i:s', strtotime($timeout, strtotime($last->execute_at)));
            $this->save();
        }

        return $this;
    }

    public function then($next)
    {
        $nextQueue = $next();
        $nextQueue->waiting_id = $this->id;
        $nextQueue->save();

        return $this;
    }

    public function after($prev = null)
    {
        if (!$prev) {
            return $this;
        }

        $this->waiting_id = $prev->id;
        $this->save();

        return $this;
    }

    public function delay($delay)
    {
        $this->execute_at = date('Y-m-d H:i:s', strtotime($delay));
        $this->save();

        return $this;
    }
}
