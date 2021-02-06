<?php

namespace Pckg\Queue\Service;

use Pckg\Queue\Service\Cron\Job;

class Cron
{

    /**
     * @return Job
     */
    public static function createJob($command, $name = null, $parameters = [])
    {
        $job = new Job($command, $name, $parameters);

        return $job;
    }
}
