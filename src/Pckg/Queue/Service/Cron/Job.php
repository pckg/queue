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

    protected $name = null;

    public function __construct($command, $name = null)
    {
        $this->command = $command;
        $this->name = $name ?? $command;
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getName()
    {
        return $this->name;
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

        /**
         * @T00D00 - can we run this in same process, so thing get optimized?
         */
        $command = $this->getFullCommand();
        $process = new Process($command);
        $process->setTimeout(60);
        $process->mustRun();

        return $process;
    }

    public function getNextExecutionDatetime()
    {
        if (!$this->days && !$this->minutes && !$this->times) {
            // everyMinute()
            return 'in less than a minute';
        } elseif (!$this->days && !$this->minutes) {
            // everyDay()->at()
            $time = end($this->times);
            if (date('H:i') > $time) {
                return 'tommorow at ' . $time;
            } else {
                return 'today at ' . $time;
            }
        } else if ($this->days) {
            // onDays()->at()
            $day = end($this->days);
            $time = end($this->times);

            if (date('N') == $day) {
                if (date('H:i') > $time) {
                    return 'next ' . date('l') . ' at ' . $time;
                } else {
                    return 'today at ' . $time;
                }
            } else {
                return 'next ' . date('l', strtotime((7 + $day - date('N')) . 'days')) . ' at ' . $time;
            }
        }
    }

    public function getPreviousExecutionDatetime()
    {
        if (!$this->days && !$this->minutes && !$this->times) {
            // everyMinute()
            return 'in last minute';
        } elseif (!$this->days && !$this->minutes) {
            // everyDay()->at()
            $time = end($this->times);
            if (date('H:i') > $time) {
                return 'today at ' . $time;
            } else {
                return 'yesterday at ' . $time;
            }
        } else if ($this->days) {
            // onDays()->at()
            $day = end($this->days);
            $time = end($this->times);

            if (date('N') == $day) {
                if (date('H:i') > $time) {
                    return 'today at ' . $time;
                } else {
                    return 'previous ' . date('l') . ' at ' . $time;
                }
            } else {
                return 'previous ' . date('l', strtotime((7 + $day - date('N')) . 'days')) . ' at ' . $time;
            }
        }
    }

}