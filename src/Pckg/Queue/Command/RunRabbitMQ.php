<?php namespace Pckg\Queue\Command;

use Grpc\Server;
use Impero\Servers\Service\ServerQueueDispatcher;
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
         * php queue app channel
         */
        $channel = server('argv')[2] ?? null;
        if (!$channel) {
            throw new \Exception('Channel parameter is required');
        }

        (new RunChannel())->executeManually([
                                                '--channel' => $channel,
                                            ]);

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
