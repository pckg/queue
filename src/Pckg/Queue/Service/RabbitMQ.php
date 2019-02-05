<?php namespace Pckg\Queue\Service;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQ
{
    /**
     * @var AMQPStreamConnection|AbstractConnection
     */
    protected $connection;

    protected $channels = [];

    public function __construct($connectionConfig)
    {
        $this->connection = new AMQPStreamConnection($connectionConfig['host'], $connectionConfig['port'],
                                                     $connectionConfig['user'], $connectionConfig['pass']);
    }

    public function queueJob($queue, $data = [])
    {
    }

    /**
     * @return AMQPChannel
     */
    public function getChannel($name = '')
    {
        if (!array_key_exists($name, $this->channels)) {
            $this->channels[$name] = $this->connection->channel();
        }

        return $this->channels[$name];
    }

    public function makeQueue($queueName)
    {
        return $this->getChannel()->queue_declare($queueName, false, true, false, false);
    }

    public function makeExchangeQueue()
    {
        return $this->getChannel()->queue_declare('', false, false, true, false);
    }

    public function makeExchange($exchangeName, $type = 'direct')
    {
        return $this->getChannel()->exchange_declare($exchangeName, $type, false, false, false);
    }

    public function queueMessage($message, $exchange)
    {
        $msg = new AMQPMessage($message, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        return $this->getChannel()->basic_publish($msg, '', $exchange);
    }

    public function exchangeMessage($message, $exchange, $bind)
    {
        $msg = new AMQPMessage($message);

        return $this->getChannel()->basic_publish($msg, $exchange, $bind);
    }

    public function listen($queueName, $exchange, $bind)
    {
        $this->getChannel()->queue_bind($queueName, $exchange, $bind);
    }

    public function concurrency($concurrent = 1)
    {
        /**
         * Feed worker with only 1 message at the time.
         */
        return $this->getChannel()->basic_qos(null, $concurrent, null);
    }

    public function receiveMessage(callable $callback, $queueName, $exchange = '', $a = false)
    {
        $this->getChannel()->basic_consume($queueName, $exchange, false, $a, false, false, $callback);
    }

    public function readCallbacks()
    {
        $channel = $this->getChannel();
        while (count($channel->callbacks)) {
            $channel->wait();
        }
    }

    public function close()
    {
        $this->getChannel()->close();
        $this->connection->close();
    }

}