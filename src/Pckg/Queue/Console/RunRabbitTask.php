<?php namespace Pckg\Queue\Console;

use Derive\Notification\Service\Notifier;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\Queue;
use Pckg\Queue\Service\RabbitMQ;
use Symfony\Component\Console\Input\InputOption;

class RunRabbitTask extends Command
{

    protected function configure()
    {
        $this->setName('queue:rabbit:run-task')->setDescription('Run RabbitHQ task')->addOptions([
                                                                                                     'queue'    => 'Queue name',
                                                                                                     'exchange' => 'Exchange name',
                                                                                                     'bind'     => 'Exchange bind',
                                                                                                 ],
                                                                                                 InputOption::VALUE_REQUIRED);
    }

    /**
     * @param Queue $queue
     */
    public function handle(RabbitMQ $rabbitMQ)
    {
        /**
         * Get RabbitMQ channel
         */
        $channel = $rabbitMQ->getChannel();
        $queueName = $this->option('queue');
        $exchange = $this->option('exchange');
        $bind = $this->option('bind');

        if (!$bind) {
            $queueName = $exchange;
            /**
             * In single mode we send task to single worker.
             * We will round-robin balance message to workers listening to $queueName on $route.
             */

            /**
             * Connect to $queueName.
             */
            $rabbitMQ->makeQueue($queueName);

            /**
             * Send message to connected queue ($queueName) on $route.
             */
            for ($i = 0; $i < 1; $i++) {
                $message = json_encode([
                                           'command' => 'echo 1',
                                           'taskId'  => rand(0, 100),
                                           'message' => 'Test queue message ' . $i,
                                       ]);
                $rabbitMQ->queueMessage($message, $exchange);

                echo ' [x] Sent ', substr($message, 0, 10), ' to ', $queueName, ' on ', $exchange, "\n";
            }
        } else {
            $rabbitMQ->makeExchange($exchange);

            $message = 'Test exchange message ' . rand(1, 100);
            for ($i = 0; $i < 1000; $i++) {
                $message = 'Test exchange message ' . $i;
                $rabbitMQ->exchangeMessage($message, $exchange, $bind);

                echo ' [x] Sent ', substr($message, 0, 10), ' to ', $queueName, ' on ', $exchange, "\n";
            }

        }

        $rabbitMQ->close();

        echo microtime(true);

        return;
    }

}
