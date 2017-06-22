<?php namespace Pckg\Queue\Console;

use Pckg\Collection;
use Pckg\Framework\Console\Command;
use Pckg\Manager\Job as JobManager;
use Pckg\Queue\Service\Cron\Job;
use Pckg\Queue\Service\Queue;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class RunJobs extends Command
{

    protected $pidFile;

    protected function configure()
    {
        $this->setName('cron:run')
             ->setDescription('Run cronjobs / jobs')
             ->addOptions([
                              'pid-file' => 'Process ID file for updates',
                          ], InputOption::VALUE_OPTIONAL);
    }

    /**
     * @param Queue $queue
     */
    public function handle()
    {
        $this->pidFile = $this->option('pid-file');
        $this->touchPidFile();

        $jobs = new Collection(context(JobManager::class)->all());
        $e = null;

        try {
            $jobs->each(
                function(Job $job) {
                    /**
                     * Touch file so parent process knows that we're not stuck.
                     */
                    $this->touchPidFile();

                    /**
                     * Check if it's correct time of day to be run.
                     */
                    if ($job->shouldBeRun()) {
                        $e = null;
                        $process = null;

                        try {
                            $process = $job->run();
                        } catch (Throwable $e) {
                            $this->output("Exception: " . exception($e));
                        } finally {
                            /**
                             * @T00D00 - log output and error output
                             */
                            if ($process && $output = $process->getOutput()) {
                                $this->output("Output: " . $output);
                            }

                            if ($process && $errorOutput = $process->getErrorOutput()) {
                                $this->output("Error:" . $errorOutput);
                            }
                        }
                    }
                }
            );
        } catch (Throwable $e) {
        } finally {
            $this->removePidFile();

            if ($e) {
                throw $e;
            }
        }
    }

    protected function touchPidFile()
    {
        if ($this->pidFile) {
            file_put_contents($this->pidFile, "running");
        }
    }

    protected function removePidFile()
    {
        if ($this->pidFile) {
            file_put_contents($this->pidFile, "ok");
        }
    }

}
