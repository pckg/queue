<?php namespace Pckg\Queue\Console;

use Exception;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Record\Queue as QueueRecord;
use Pckg\Queue\Service\Queue;
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
            function(QueueRecord $queue) {
                $this->output('#' . $queue->id . ': ' . 'started (' . date('Y-m-d H:i:s') . ')');
                $queue->changeStatus('started');
            },
            false
        );

        /**
         * Execute jobs.
         */
        $waitingQueue->each(
            function(QueueRecord $queue) {
                $this->output('#' . $queue->id . ': ' . 'running (' . date('Y-m-d H:i:s') . ')');
                $queue->changeStatus('running');

                $this->output('#' . $queue->id . ': ' . $queue->command);
                $output = null;
                $sha1Id = sha1($queue->id);
                try {
                    $timeout = strtotime($queue->execute_at) - time();
                    $command = $queue->command . ' && echo ' . $sha1Id;
                    $lastLine = null;
                    if (false && $timeout > 0) {
                        exec('timeout -k 60 ' . $timeout . ' ' . $command, $output);

                    } else {
                        if (strpos($command, 'furs:')) {
                            $command = str_replace(
                                [
                                    '/www/schtr4jh/derive.foobar.si/htdocs/',
                                    '/www/schtr4jh/beta.derive.foobar.si/htdocs/',
                                ],
                                '/www/schtr4jh/bob.pckg.derive/htdocs/',
                                $command
                            );

                            $connection = ssh2_connect(config('furs.sship'), 22);
                            ssh2_auth_password($connection, config('furs.sshuser'), config('furs.sshpass'));

                            $stream = ssh2_exec($connection, $command);

                            $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);

                            stream_set_blocking($errorStream, true);
                            stream_set_blocking($stream, true);

                            $errorStreamContent = stream_get_contents($errorStream);
                            $streamContent = stream_get_contents($stream);

                            $output = $errorStreamContent . "\n" . $streamContent;
                            $lastLine = substr($streamContent, -41, 40);

                        } else {
                            /**
                             * @T00D00 - execute in current scope if possible, this will optimize things a little bit.
                             */
                            exec($command, $output);
                            $lastLine = end($output);

                        }

                    }

                    if ($lastLine != $sha1Id) {
                        $queue->changeStatus(
                            'failed_permanently',
                            [
                                'log' => 'FAILED: ' . (is_string($output) ? $output : implode("\n", $output)),
                            ]
                        );

                        return;

                        throw new Exception('Job failed');
                    }
                } catch (Throwable $e) {
                    $queue->changeStatus(
                        'failed_permanently',
                        [
                            'log' => exception($e),
                        ]
                    );

                    return;
                }

                if (!$output) {
                    $queue->changeStatus(
                        'failed_permanently',
                        [
                            'log' => 'No output',
                        ]
                    );

                    return;
                }

                $this->output('#' . $queue->id . ': ' . 'finished (' . date('Y-m-d H:i:s') . ')');
                $queue->changeStatus(
                    'finished',
                    [
                        'log' => is_string($output) ? $output : implode("\n", $output),
                    ]
                );
            },
            false
        );
    }

}
