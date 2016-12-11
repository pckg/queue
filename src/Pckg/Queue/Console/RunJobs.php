<?php namespace Pckg\Queue\Console;

use Exception;
use Pckg\Collection;
use Pckg\Framework\Console\Command;
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
        $jobs = new Collection();
        $jobs->each(
            function(Job $job) {
                if ($job->shouldBeRun()) {
                    $command = $job->getFullCommand();
                    $this->output(
                        date('Y-m-d H:i:s') . ' - Running ' . substr(str_replace(path('root'), '', $command), 0, 60)
                    );
                    $sha1Id = sha1(microtime());
                    $command .= ' && echo ' . $sha1Id;
                    $output = [];
                    try {
                        exec($command, $output);
                        $lastLine = end($output);
                    } catch (Throwable $e) {
                        throw new Exception('Error executing cronjob!');
                    } finally {
                        if ($lastLine != $sha1Id) {
                            throw new Exception('Error executing cronjob, sha1 mismatch!');
                        }
                        d($output);
                    }
                }
            }
        );
        $this->output('Finished cronjob at ' . date('Y-m-d H:i:s'));
    }

}