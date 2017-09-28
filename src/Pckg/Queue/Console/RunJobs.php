<?php namespace Pckg\Queue\Console;

use Derive\Notification\Service\Notifier;
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
        $failed = 0;
        $ran = new Collection();

        try {
            $ran = $jobs->filter(
                function(Job $job) {
                    /**
                     * Touch file so parent process knows that we're not stuck.
                     */
                    $this->touchPidFile();

                    return $job->shouldBeRun();
                })->each(
                function(Job $job) use (&$failed) {
                    $e = null;
                    $process = null;

                    try {
                        $job->run();
                    } catch (Throwable $e) {
                        $this->output("Exception: " . exception($e));
                        $failed++;
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
            )->each(function(Job $job) {
                /**
                 * Job is asynchronuous.
                 */
                if ($job->isAsync()) {
                    return;
                }

                if (!$job->wait()) {
                    return;
                }

                /**
                 * Waiting for process to finish.
                 */
                $this->output("Waiting for sync " . $job->getCommand());
            });
        } catch (Throwable $e) {
        } finally {
            $ran->each(function(Job $job) {
                /**
                 * Job should be finished already.
                 */
                if (!$job->isAsync()) {
                    return;
                }

                if (!$job->wait()) {
                    return;
                }

                /**
                 * Waiting for process to finish.
                 */
                $this->output("Waiting for async " . $job->getCommand());
            })->each(function(Job $job) {
                if (!$job->getProcess()->isSuccessful()) {
                    $this->output("ERROR: " . $job->getProcess()->getErrorOutput());
                }
            });
            $this->removePidFile();

            /**
             * Notify super admins via dashboard.
             */
            if ($failed) {
                (new Notifier())
                    ->statuses(1)
                    ->message($failed . ' job(s) failed')
                    ->notify();
            }

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
