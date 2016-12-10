<?php namespace Pckg\Queue\Service\Cron;

class Job
{

    protected $date;

    protected $time;

    protected $when;

    protected $command;

    protected $data = [];

    public function __construct($command, $data = [])
    {
        $this->command = $command;
        $this->data = $data;
    }

    public function when(callable $when)
    {
        $this->when = $when;

        return $this;
    }

    public function everyDay()
    {
        $this->date = '*';

        return $this;
    }

    public function everyHour()
    {

    }

    public function everyMinute()
    {

    }

    public function at($time)
    {
        $this->time = $time;

        return $this;
    }

    public function shouldBeRun()
    {
        if ($this->when && !$this->when()) {
            return false;
        }
    }

}