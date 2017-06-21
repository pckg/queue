<?php namespace Pckg\Queue\Console;

use Pckg\Collection;
use Pckg\Framework\Console\Command;
use Pckg\Manager\Job as JobManager;
use Pckg\Queue\Service\Cron\Job;
use Pckg\Queue\Service\Queue;
use Throwable;

class RunJobs extends Command
{

    protected function configure()
    {
        $this->setName('cron:run')
             ->setDescription('Run cronjobs / jobs');
    }

    /**
     * @param Queue $queue
     */
    public function handle()
    {
        $this->output('Starting cronjob at ' . date('Y-m-d H:i:s'));
        $jobs = new Collection(context(JobManager::class)->all());
        $jobs->each(
            function(Job $job) {
                if ($job->shouldBeRun()) {
                    $this->output('Running ' . $job->getCommand());
                    try {
                        $process = $job->run();
                        /**
                         * @T00D00 - log output and error output
                         */
                        if ($output = $process->getOutput()) {
                            $this->output("Output: " . $output);
                        }

                        if ($errorOutput = $process->getErrorOutput()) {
                            $this->output("Error:" . $errorOutput);
                        }
                    } catch (Throwable $e) {
                        $this->output("Exception: " . exception($e));
                    }
                }
            }
        );
        $this->output('Finished cronjob at ' . date('Y-m-d H:i:s'));
    }

}
