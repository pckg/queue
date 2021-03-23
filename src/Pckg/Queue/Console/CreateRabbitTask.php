<?php

namespace Pckg\Queue\Console;

use Derive\Notification\Service\Notifier;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\Queue;
use Pckg\Queue\Service\RabbitMQ;
use Symfony\Component\Console\Input\InputOption;

class CreateRabbitTask extends Command
{

    protected function configure()
    {
        $this->setName('queue:rabbit:create-task')->setDescription('Create task in RabbitMQ')->addOptions(
            [
                'exchange' => 'Exchannge',
            ],
            InputOption::VALUE_REQUIRED
        );
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
        $exchange = $this->option('exchange');
        $bind = $this->option('bind');

        $i = 0;
        $jobs = 1000000;
        $times = 10;
        $this->outputDated('Starting queueing, ' . $jobs . ' jobs, 40x' . $times . 'B');
        $start = microtime(true);
        while ($i < $jobs) {
            $i++;
            queue($exchange, date('Y-m-d H:i:s') . microtime() . str_repeat(sha1(microtime()), $times));
            if ($i%10000 === 0) { // check every 10 seconds for example. try to schedule this somehow.
                $this->outputDated('Checking heartbeat');
                $rabbitMQ->getConnection()->checkHeartBeat();
            }
        }
        $total = microtime(true) - $start;
        $this->outputDated('Done queueing: ' . round($total, 3));

        $rabbitMQ->close();
        $this->outputDated('Done');
    }
}
