<?php

namespace Pckg\Queue\Command;

use Impero\Servers\Service\ServerQueueDispatcher;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\RabbitMQ;
use Symfony\Component\Console\Input\InputOption;

class RunChannel extends Command
{
    protected function configure()
    {
        $this->setName('queue:run-channel')->setDescription('Run single channel queue')->addOptions([
                                                                                                        'channel'     => 'Queue channel',
                                                                                                        'concurrency' => 'Default 1',
                                                                                                    ], InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        $channel = $this->option('channel');

        $rabbitMQ = $this->getRabbitMQ();
        $rabbitMQ->makeQueue($channel);

        /**
         * Each message callback.
         */
        $callback = function ($msg) {
            /**
             * We expect json to be sent as event body.
             */
            $body = $msg->body;

            /**
             * Parse JSON.
             */
            $data = @json_decode($body);
            if (!$data) {
                $this->nacknowledgeMessage($msg, 'Missing data or malformed JSON');

                return;
            }
            /**
             * Validate keys.
             */
            if (!isset($data->command)) {
                $this->nacknowledgeMessage($msg, 'No command in JSON');

                return;
            }

            /**
             * Print body for debugging.
             */
            $this->outputDated($body);

            /**
             * Check if we can execute command.
             */
            if (!$data->command) {
                /**
                 * We should probably report this?
                 */
                $this->nacknowledgeMessage($msg, 'Do not know how to handle task');

                return;
            }

            try {
                /**
                 * Try to execute task.
                 * Note: RabbitMQ uses a heartbeat (120s).
                 * If this script runs for more than defined heartbeat (119s) it'll report a broken connection
                 * during message acknowledgement.
                 */
                exec($data->command, $output, $return);

                /**
                 * Dump output and code.
                 */
                if ($return) {
                    $this->outputDated('CODE: ' . $return);
                }
                if ($output) {
                    $this->outputDated('OUTPUT: ' . json_encode($output));
                }
            } catch (\Throwable $e) {
                /**
                 * Execution failed.
                 * We should report this?
                 */
                $this->nacknowledgeMessage($msg, 'EXCEPTION: Queue: ' . exception($e));

                return;
            }

            $this->acknowledgeMessage($msg);
        };

        /**
         * Process concurrent messages.
         */
        $concurrency = $this->option('concurrency', 1);
        $rabbitMQ->concurrency($concurrency);

        /**
         * Listen on chanel with callback.
         */
        $rabbitMQ->receiveMessage($callback, $channel);

        $this->outputDated('Reading callbacks on ' . $channel);

        /**
         * Start reading callbacks.
         */
        $rabbitMQ->readCallbacks();

        $this->outputDated('Closing connection');

        $rabbitMQ->close();

        $this->outputDated('Connection closed');
    }

    /**
     * @return RabbitMQ
     */
    protected function getRabbitMQ()
    {
        return resolve(RabbitMQ::class);
    }

    protected function acknowledgeMessage($msg)
    {
        try {
            $this->outputDated('ACK');
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            $this->outputDated('ACK-ed');
        } catch (\Throwable $e) {
            $this->outputDated('ACK FAILED: ' . exception($e));
        }
    }

    protected function nacknowledgeMessage($msg, $reason = null)
    {
        try {
            $this->outputDated('NACK: ' . $reason);
            $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'], false, false);
            $this->outputDated('NACK-ed');
        } catch (\Throwable $e) {
            $this->outputDated('NACK FAILED: ' . exception($e));
        }
    }
}
