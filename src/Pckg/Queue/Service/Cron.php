<?php namespace Pckg\Queue\Service;

use Pckg\Queue\Service\Cron\Job;

class Cron
{

    /**
     * @return Job
     */
    public static function createJob($command, $data = [])
    {
        $job = new Job($command, $data);

        return $job;
    }

}