<?php namespace Pckg\Queue\Console;

use Derive\Notification\Service\Notifier;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\Queue;
use Pckg\Queue\Service\RabbitMQ;
use Symfony\Component\Console\Input\InputOption;

class RunRabbitWorker extends Command
{

    protected function configure()
    {
        $this->setName('queue:rabbit:run-worker')->setDescription('Run RabbitHQ worker')->addOptions([
                                                                                                         'queue'    => 'Queue name',
                                                                                                         'exchange' => 'Exchannge',
                                                                                                         'bind'     => 'Exchannge bind',
                                                                                                     ],
                                                                                                     InputOption::VALUE_REQUIRED);
    }

    /**
     * @param Queue $queue
     */
    public function handle(RabbitMQ $rabbitMQ)
    {
        /**
         * Get channel.
         */
        $channel = $rabbitMQ->getChannel();
        $queueName = $this->option('queue');
        $exchange = $this->option('exchange');
        $bind = $this->option('bind');

        if (!$bind) {
            $queueName = $exchange;
            $rabbitMQ->makeQueue($queueName);

            echo " [*] Waiting for messages. To exit press CTRL+C\n";

            $callback = function($msg) use ($queueName, $exchange) {
                echo \microtime(true) . ' [x] Received ', $msg->body, ' on ', $queueName, ' and ', $exchange, "\n";
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            };

            $rabbitMQ->concurrency(5);
            $rabbitMQ->receiveMessage($callback, $exchange);
        } else {
            /**
             * Bind to temp queue.
             */
            list($queue_name, ,) = $rabbitMQ->makeExchangeQueue();

            $rabbitMQ->listen($queue_name, $exchange, $bind);

            echo " [*] Waiting for logs. To exit press CTRL+C\n";

            $callback = function($msg) {
                echo ' [x] ', $msg->delivery_info['routing_key'], " ", $msg->body, "\n";
                //$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            };

            $rabbitMQ->receiveMessage($callback, $queue_name, '', true);
        }

        echo ' [-] Reading callbacks', "\n";

        $rabbitMQ->readCallbacks();

        $rabbitMQ->close();
    }

}
