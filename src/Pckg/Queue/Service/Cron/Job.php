<?php namespace Pckg\Queue\Service\Cron;

use Pckg\Framework\Console\Command;
use Symfony\Component\Process\Process;
use Throwable;

class Job
{

    /**
     * @var string|Command
     */
    protected $command;

    protected $data = [];

    /**
     * @var null|callable
     */
    protected $when = null;

    protected $days = [];

    protected $minutes = [];

    protected $hours = [];

    protected $times = [];

    protected $long = false;

    protected $name = null;

    protected $async = false;

    protected $background = false;

    protected $timeout = 60;

    protected $maxInstances = 1;

    protected $repeat = false;

    /**
     * @var Process
     */
    protected $process;

    protected $pid = null;

    protected $parameters = [];

    public function __construct($command, $name = null, $parameters = [])
    {
        $this->command = $command;
        $this->name = $name ?? $command;
        $this->parameters = $parameters;
    }

    public function setPid($pid)
    {
        $this->pid = $pid;

        return $this;
    }

    public function getCommandName()
    {
        return $this->getCommand()->getName();
    }

    public function getCommand()
    {
        if (is_string($this->command)) {
            $class = $this->command;
            $this->command = new $class;
        }

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
            ' ' . $this->getCommand()->getName() .
            ($parameters ? ' ' . implode(' ', $parameters) : '')
            . ($this->background ? ' > /dev/null 2>&1 &' : '');

        return $command;
    }

    public function when(callable $when)
    {
        $this->when = $when;

        return $this;
    }

    public function maxInstances($max)
    {
        $this->maxInstances = $max;

        return $this;
    }

    public function repeat($repeat = true)
    {
        $this->repeat = $repeat;

        return $this;
    }

    public function getRepeat()
    {
        return $this->repeat;
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

    public function onMinutes(array $minutes = [])
    {
        $this->minutes = $minutes;

        return $this;
    }

    public function onHours(array $hours = [])
    {
        $this->hours = $hours;

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

    public function async($async = true)
    {
        $this->async = $async;

        return $this;
    }

    public function background($background = true)
    {
        $this->background = $background;

        return $this;
    }

    public function getNumberOfRunningInstances()
    {
        $output = [];
        $returnVar = null;
        $command = $this->getFullCommand();
        $grep = 'ps -wwaux';
        $lastLine = exec($grep, $output, $returnVar);

        $count = 0;
        foreach ($output as $o) {
            if (strpos($o, $this->getCommand()->getName()) !== false && strpos($o, path('root')) !== false) {
                $count++;
            }
        }

        return $count;
    }

    public function shouldBeRun()
    {
        if ($this->maxInstances) {
            $count = $this->getNumberOfRunningInstances();

            if ($count >= $this->maxInstances) {
                return false;
            }
        }

        if ($this->when) {
            $when = $this->when;
            if (!$when()) {
                return false;
            }
        }

        if ($this->days && !in_array(date('N'), $this->days)) {
            return false;
        }

        if ($this->hours && !in_array((int)date('H'), $this->hours)) {
            return false;
        }

        if ($this->minutes && !in_array((int)date('i'), $this->minutes)) {
            return false;
        }

        if ($this->times && !in_array(date('H:i'), $this->times) && !in_array(date('G:i'), $this->times)) {
            return false;
        }

        return true;
    }

    public function timeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function isAsync()
    {
        return $this->async;
    }

    public function isBackground()
    {
        return $this->background;
    }

    public function isLong()
    {
        return $this->isLong();
    }

    public function fork()
    {
        $pid = pcntl_fork();
        if ($pid == -1 || $pid) {
            return $pid;
        }

        /**
         * First, change process name.
         */
        // child
        try {
            $title = $this->getFullCommand();
            if (!cli_set_process_title($title)) {
                /**
                 * This is crucial for counting number of running instances.
                 */
                exit(2);
            }

            /**
             * __wakeup :)
             */
            trigger('forked');

            $this->command->executeManually($this->parameters);

            exit(0);
        } catch (Throwable $e) {
            /**
             * @T00D00 - log error?
             */
            echo exception($e);
            exit(1);
        }
    }

    /**
     * @param bool $mustRun
     *
     * @return $this
     * Run job in different process.
     */
    public function run($mustRun = false)
    {
        $output = null;
        $error = null;

        $command = $this->getFullCommand();
        $this->process = new Process($command);
        $this->process->setTimeout($this->timeout);

        /**
         * Allow parralel execution. Fork process?
         */
        if ($mustRun) {
            $this->process->mustRun();
        } else {
            $this->process->start();
        }

        return $this;
    }

    public function getProcess()
    {
        return $this->process;
    }

    public function getNextExecutionDatetime()
    {
        if (!$this->days && !$this->minutes && !$this->times) {
            // everyMinute()
            return 'in less than a minute';
        }

        if (!$this->days && !$this->minutes) {
            // everyDay()->at()
            $time = end($this->times);
            if (date('H:i') > $time) {
                return 'tommorow at ' . $time;
            }

            return 'today at ' . $time;
        }

        if ($this->days) {
            // onDays()->at()
            $day = end($this->days);
            $time = end($this->times);

            if (date('N') != $day) {
                return 'next ' . date('l', strtotime((7 + $day - date('N')) . 'days')) . ' at ' . $time;
            }

            if (date('H:i') > $time) {
                return 'next ' . date('l') . ' at ' . $time;
            }

            return 'today at ' . $time;
        }
    }

    public function getPreviousExecutionDatetime()
    {
        if (!$this->days && !$this->minutes && !$this->times) {
            // everyMinute()
            return 'in last minute';
        }

        if (!$this->days && !$this->minutes) {
            // everyDay()->at()
            $time = end($this->times);
            if (date('H:i') > $time) {
                return 'today at ' . $time;
            }
            return 'yesterday at ' . $time;

        }
        if ($this->days) {
            // onDays()->at()
            $day = end($this->days);
            $time = end($this->times);

            if (date('N') != $day) {
                return 'previous ' . date('l', strtotime((7 + $day - date('N')) . 'days')) . ' at ' . $time;
            }
            if (date('H:i') > $time) {
                return 'today at ' . $time;
            }
            return 'previous ' . date('l') . ' at ' . $time;

        }
    }

    public function wait()
    {
        if (!$this->process) {
            return false;
        }

        if (!$this->process->isRunning()) {
            return false;
        }

        $this->process->wait();

        return true;
    }

}