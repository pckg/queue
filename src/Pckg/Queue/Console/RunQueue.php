<?php

namespace Pckg\Queue\Console;

use Derive\Notification\Service\Notifier;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Record\Queue as QueueRecord;
use Pckg\Queue\Service\Queue;
use Symfony\Component\Process\Process;
use Throwable;

class RunQueue extends Command
{

    protected function configure()
    {
        $this->setName('queue:run')
             ->setDescription('Run waiting queue');
    }

    /**
     * @param Queue $queue
     */
    public function handle(Queue $queueService)
    {
        $waitingQueue = $queueService->getWaiting();

        /**
         * Set queue as started, we'll execute it later.
         */
        $waitingQueue->each(
            function (QueueRecord $queue) {
                $this->output('#' . $queue->id . ': ' . 'started (' . date('Y-m-d H:i:s') . ')');
                $queue->changeStatus('started');
            }
        );

        /**
         * Execute jobs.
         */
        $failed = 0;
        $waitingQueue->each(
            function (QueueRecord $queue) use (&$failed) {
                $this->output('#' . $queue->id . ': ' . 'running (' . date('Y-m-d H:i:s') . ')');
                $queue->changeStatus('running');

                $output = null;
                $error = null;

                $command = $queue->command;
                $exception = null;

                /**
                 * Run process
                 */
                try {
                    $process = new Process($command);
                    $process->setTimeout(5 * 60);
                    $process->mustRun();

                    $output = $process->getOutput();
                    $error = $process->getErrorOutput();
                } catch (Throwable $e) {
                    $failed++;
                    $exception = $e;
                }

                /**
                 * Handle output log.
                 */
                if ($output) {
                    $queue->createLog(['log' => 'Output: ' . $output]);
                }

                /**
                 * Handle error log.
                 */
                if ($error) {
                    $queue->createLog(['log' => 'Error: ' . $error]);
                }

                /**
                 * Handle exception
                 */
                if ($exception) {
                    $this->output('#' . $queue->id . ': ' . 'failed permanently (' . date('Y-m-d H:i:s') . ')');

                    $queue->changeStatus(
                        'failed_permanently',
                        [
                            'log' => exception($exception),
                        ]
                    );
                } else {
                    $this->output('#' . $queue->id . ': ' . 'finished (' . date('Y-m-d H:i:s') . ')');

                    $queue->changeStatus(
                        'finished',
                        [
                            'log' => $output,
                        ]
                    );
                }
            }
        );

        if ($failed) {
            /**
             * @phpstan-ignore-next-line
             */
            (new Notifier())
                ->statuses(1)
                ->message($failed . ' queue(s) failed')
                ->notify();
        }
    }
}
