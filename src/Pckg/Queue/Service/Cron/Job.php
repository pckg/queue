<?php namespace Pckg\Queue\Service\Cron;

use Symfony\Component\Process\Process;

class Job
{

    protected $command;

    protected $data = [];

    protected $when = [];

    protected $days = [];

    protected $minutes = [];

    protected $times = [];

    protected $long = false;

    public function __construct($command, $data = [])
    {
        $this->command = $command;
        $this->data = $data;
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getFullCommand()
    {
        $appName = config('pckg.queue.app', lcfirst(get_class(app())));
        $path = path('root') . 'console';
        $parameters = [];
        $command = 'php ' . $path .
                   ($appName ? ' ' . $appName : '') .
                   ' ' . $this->command .
                   ($parameters ? ' ' . implode(' ', $parameters) : '');

        return $command;
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

    public function everyMinute()
    {
        $this->minutes = [];

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

    public function long($long = true)
    {
        $this->long = $long;

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

        if ($this->minutes && !in_array(date('i'), $this->minutes)) {
            return false;
        }

        if ($this->times && !in_array(date('H:i'), $this->times) && !in_array(date('G:i'), $this->times)) {
            return false;
        }

        return true;
    }

    public function run()
    {
        $output = null;
        $error = null;

        $command = $this->getFullCommand();
        $process = new Process($command);
        $process->setTimeout(60);
        $process->mustRun();

        return $process;
    }

}