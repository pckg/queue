<?php namespace Pckg\Queue\Service\Cron;

class JobCollection
{

    protected $jobs = [];

    public function add($job)
    {
        $this->jobs[] = $job;

        return $this;
    }

    public function all()
    {
        return $this->jobs;
    }

}