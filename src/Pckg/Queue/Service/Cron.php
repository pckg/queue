<?php namespace Pckg\Queue\Service;

use Pckg\Queue\Service\Cron\Job;

class Cron
{

    /**
     * @return Job
     */
    public static function createJob($command, $data = [], $name = null)
    {
        $job = new Job($command, $data, $name);

        return $job;
    }

}