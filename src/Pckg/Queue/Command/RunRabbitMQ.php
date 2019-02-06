<?php

namespace Pckg\Queue\Command;

use Pckg\Concept\AbstractChainOfReponsibility;
use Pckg\Framework\Response;
use Pckg\Queue\Service\RabbitMQ;

class RunRabbitMQ extends AbstractChainOfReponsibility
{

    protected $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function execute(callable $next)
    {
        /**
         * Require channel parameter:
         * php queue channel
         */
        $channel = server('argv')[1] ?? null;
        if (!$channel) {
            throw new \Exception('Channel parameter is required');
        }

        /**
         * We need to connect to RabbitMQ provider, listen to commands, and execute them.
         * Each project defines which jobs can be run async and which does not.
         * 127.0.0.1:12345/$project/$identifier/$path
         * Center
         *  - center has for starters single sync queue
         *  - it will soon need multiple queues:
         *    - all impero related stuff (create platform, change domain, redeploy, ...) which runs synchronically
         *    - other sync stuff?
         *    - other async stuff?
         * Impero
         *  - impero has for starters single sync queue
         *  - it will need per impero user (client datacenter?) queue (currently 1?)
         * Mailo
         *  - newsletter mail queue with multiple instances: 127.0.0.1:12345/mailo/mailo/mails/newsletter
         *  - transactional mail queue with multiple instances: 127.0.0.1:12345/mailo/mailo/mails/transactional
         *  - other queue (backlog, failed queues, ...): 127.0.0.1:12345/mailo/mailo/queue
         * Pendo
         *  - queue for furs: 127.0.0.1:12345/pendo/pendo/fiscalization/furs
         *  - queue for purh: 127.0.0.1:12345/pendo/pendo/fiscalization/furs
         *  - other queue (backlog, failed queues, ...): 127.0.0.1:12345/pendo/pendo/queue
         * Comms
         *  - single queue (mails, jobs) executed by one or multiple queue workers: 127.0.0.1:12345/derive/hi/queue
         * Before we do all this we need to:
         *  - dump config
         *  - load balance web workers & dump config
         *  - deploy cron & queue services
         * First task is center.
         * We need to create simple queue for platform related tasks which results in impero api calls.
         * Queue will publish and listen on center/center/platform channel.
         * Listener services will be run as:
         *  - php queue center/center/platform
         * Messages will contain json with keys:
         *  - taskId    queue db task id            1234
         *  - command   command to be executed      php console app foo:bar --foo=bar
         *  - command   -||-                        {"console":"Some/Console/Handler","params":{"--foo":"bar"}}
         */

        $rabbitMQ = $this->getRabbitMQ();

        $rabbitMQ->makeQueue($channel);

        echo " [*] Waiting for messages. To exit press CTRL+C or kill process ID " . getmypid() . "\n";

        $callback = function($msg) use ($channel) {
            $body = $msg->body;
            /**
             * Parse JSON.
             */
            $data = @json_decode($body);
            if (!$data) {
                $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'], false, false);
                echo "not valid json or no data\n";

                return;
            }
            /**
             * Validate keys.
             */
            if (!isset($data->command)) {
                $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'], false, false);
                echo "no task or command\n";

                return;
            }

            /**
             * Print body for debugging.
             */
            echo $body . "\n";

            /**
             * Check if we can execute command.
             */
            if (!$data->command) {
                /**
                 * We should probably report this?
                 */
                echo "do not know how to handle task?\n";
                $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'], false, false);
            }

            try {
                /**
                 * Try to execute task.
                 */
                exec($data->command, $output, $return);
                d($output, $return);
            } catch (\Throwable $e) {
                /**
                 * Execution failed.
                 * We should report this?
                 */
                $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'], false, false);
                echo "\n";
                error_log('EXCEPTION: Queue: ' . exception($e));

                return;
            }

            try {
                /**
                 * Acknowledge.
                 */
                echo "ack\n";
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            } catch (\Throwable $e) {
                /**
                 * Acknowledgment failed.
                 * We should report this?
                 */
                echo 'Acknowledgment failed' . "\n";
            }

            echo "\n";
        };

        //die(microtime(true) . " " . memory_get_usage() / 1000000 . " ".  memory_get_usage(true) / 1000000);
        //dd('microtime');

        /**
         * Process one message per listener.
         */
        $rabbitMQ->concurrency(1);

        /**
         * Listen on chanel with callback.
         */
        $rabbitMQ->receiveMessage($callback, $channel);

        echo ' [-] Reading callbacks', "\n";

        /**
         * Start reading callbacks.
         */
        $rabbitMQ->readCallbacks();

        echo ' [-] Closing connection', "\n";

        $rabbitMQ->close();

        echo ' [-] Closed, shutting ...', "\n";

        return $next();
    }

    /**
     * @return RabbitMQ
     */
    protected function getRabbitMQ()
    {
        return resolve(RabbitMQ::class);
    }

}
