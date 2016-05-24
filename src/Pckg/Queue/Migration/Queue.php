<?php namespace Pckg\Queue\Migration;

use Pckg\Migration\Migration;

class Queue extends Migration
{

    public function up()
    {
        $this->queueUp();

        $this->save();
    }

    protected function queueUp()
    {
        $queue = $this->table('queue');
        $queue->timeable();
        $queue->datetime('execute_at');
        $queue->datetime('started_at');
        $queue->datetime('finished_at');
        $queue->text('status');
        $queue->text('log');
        $queue->text('command');
        $queue->integer('executions');
        $queue->integer('retries');
        $queue->decimal('progress');

        $queueLog = $this->table('queue_logs');
        $queueLog->integer('queue_id')->references('queue');
        $queueLog->datetime('datetime');
        $queueLog->text('status');
        $queueLog->text('log');
        $queueLog->decimal('progress');
    }

}