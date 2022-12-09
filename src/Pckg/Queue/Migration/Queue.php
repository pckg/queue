<?php

namespace Pckg\Queue\Migration;

use Pckg\Migration\Migration;

class Queue extends Migration
{
    public function up()
    {
        $this->queueUp();

        $this->save();

        return $this;
    }

    protected function queueUp()
    {
        $queue = $this->table('queue');
        $queue->timeable();
        $queue->datetime('execute_at');
        $queue->datetime('started_at');
        $queue->datetime('finished_at');
        $queue->varchar('status', 32);
        $queue->longtext('log');
        $queue->longtext('command');
        $queue->integer('executions');
        $queue->integer('retries');
        $queue->decimal('progress');
        $queue->integer('waiting_id')->references('queue');

        $queueLog = $this->table('queue_logs');
        $queueLog->integer('queue_id')->references('queue');
        $queueLog->datetime('datetime');
        $queueLog->varchar('status', 32);
        $queueLog->longtext('log');
        $queueLog->decimal('progress');
    }
}
