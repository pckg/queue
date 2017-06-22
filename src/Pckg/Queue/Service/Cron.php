<?php namespace Pckg\Queue\Service;

use Pckg\Queue\Service\Cron\Job;

class Cron
{

    /**
     * @return Job
     */
    public static function createJob($command, $name = null)
    {
        $job = new Job($command, $name);

        return $job;
    }

}