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

    protected $startedAt;

    protected function configure()
    {
        $this->setName('cron:run')
             ->setDescription('Run cronjobs / jobs')
             ->addOptions([
                              'pid-file' => 'Process ID file for updates',
                          ], InputOption::VALUE_OPTIONAL)
             ->addOptions([
                              'debug' => 'Print messages',
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

        $this->startedAt = time();

        /**
         * Find jobs that need to be executed.
         */
        $ran = $this->filterJobs($jobs);
        if ($this->option('debug')) {
            $this->output(date('His') . ' - Initial run ' . $ran->count() . '/' . $jobs->count());
        }

        /**
         * Run those jobs.
         */
        $stats = $this->runJobs($ran);
        if ($this->option('debug')) {
            $this->output(date('His') . ' - Check repeating');
        }

        /**
         * Check for repeating jobs.
         */
        $this->checkRepeating($ran);

        $this->removePidFile();

        /**
         * Notify super admins via dashboard.
         */
        if ($stats['failed']) {
            (new Notifier())
                ->statuses(1)
                ->message($stats['failed'] . ' job(s) failed')
                ->notify();
        }

        if ($stats['e']) {
            throw $stats['e'];
        }
    }

    protected function checkRepeating(Collection $jobs)
    {
        /**
         * Check for repeating jobs.
         */
        $repeats = new Collection();
        $jobs->each(function(Job $job) use ($repeats) {
            if (!($repeat = $job->getRepeat())) {
                return;
            }

            $repeats->push($job);
        });

        if (!$repeats->count()) {
            return;
        }

        if ($this->option('debug')) {
            $this->output('Repeats ' . $repeats->count());
        }

        while (time() < $this->startedAt + 45) {
            $repeats->each(function(Job $job) {
                if ($job->getProcess()->isRunning()) {
                    if ($this->option('debug')) {
                        $this->output('Wait for finish');
                    }
                    // wait for process to finish
                } else {
                    if ($job->getProcess()->isSuccessful()) {
                        $collection = new Collection([$job]);
                        $filtered = $this->filterJobs($collection);
                        if ($this->option('debug')) {
                            $this->output('Running filtered ' . $filtered->count());
                        }
                        $this->runJobs($filtered);
                    } else {
                        if ($this->option('debug')) {
                            $this->output('Job not successful');
                        }
                    }
                }
            });
            sleep(2);
        }
    }

    protected function filterJobs(Collection $jobs)
    {
        return $jobs->filter(
            function(Job $job) {
                /**
                 * Touch file so parent process knows that we're not stuck.
                 */
                $this->touchPidFile();

                return $job->shouldBeRun();
            });
    }

    protected function runJobs(Collection $jobs)
    {
        $e = null;
        $failed = 0;

        if (!$jobs->count()) {
            return;
        }

        if ($this->option('debug')) {
            $this->output(date('His') . ' - running ' . $jobs->count());
        }

        $this->touchPidFile();
        try {
            /**
             * Try to execute job.
             */
            $jobs->each(
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
            );
            /**
             * Check sync job statuses.
             */
            $jobs->each(function(Job $job) {
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
                if ($this->option('debug')) {
                    $this->output(date('His') . " - Waiting for sync " . $job->getCommand());
                }
            });
        } catch (Throwable $e) {
            $this->output(date('His') . ' - EXCEPTION: ' . exception($e));
        } finally {
            /**
             * Check async job statuses.
             */
            $jobs->each(function(Job $job) {
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
                if ($this->option('debug')) {
                    $this->output(date('His') . " - Waiting for async " . $job->getCommand());
                }
            })->each(function(Job $job) {
                if (!$job->getProcess()->isSuccessful()) {
                    $this->output("ERROR: " . $job->getProcess()->getErrorOutput());
                }
            });

            return [
                'failed' => $failed,
                'e'      => $e,
            ];
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
