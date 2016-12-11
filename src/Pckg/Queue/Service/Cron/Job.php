<?php namespace Pckg\Queue\Service\Cron;

class Job
{

    protected $command;

    protected $data = [];

    protected $when = [];

    protected $days = [];

    protected $times = [];

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
        $this->days = [];

        return $this;
    }

    public function onDays($days = [])
    {
        $this->days = $days;

        return $this;
    }

    public function everyWorkDay()
    {
        return $this->onDays([1, 2, 3, 4, 5]);
    }

    public function at($time)
    {
        $this->times[] = $time;

        return $this;
    }

    public function shouldBeRun()
    {
        foreach ($this->when as $when) {
            if (!$when()) {
                return false;
            }
        }

        if ($this->days && !in_array(date('N'), $this->days)) {
            return false;
        }

        if ($this->times && !in_array(date('H:i'), $this->times) && !in_array(date('G:i'), $this->times)) {
            return false;
        }

        return true;
    }

}