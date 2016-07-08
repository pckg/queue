<?php namespace Pckg\Queue\Console;

use Exception;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Record\Queue as QueueRecord;
use Pckg\Queue\Service\Queue;

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
                        if (strpos($command, 'furs:confirm')) {
                            $command = str_replace(
                                '/www/schtr4jh/impero.foobar.si/htdocs/',
                                '/www/schtr4jh/bob.pckg.impero/htdocs/',
                                $command
                            );

                            $connection = ssh2_connect('93.103.155.205', 22);
                            ssh2_auth_password($connection, 'schtr4jh', conf('furs.sshpass'));

                            $stream = ssh2_exec($connection, $command);

                            $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);

                            stream_set_blocking($errorStream, true);
                            stream_set_blocking($stream, true);

                            $errorStreamContent = stream_get_contents($errorStream);
                            $streamContent = stream_get_contents($stream);

                            $output = $errorStreamContent . "\n" . $streamContent;
                            $lastLine = substr($streamContent, -41, 40);

                        } else {
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
                } catch (Exception $e) {
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
