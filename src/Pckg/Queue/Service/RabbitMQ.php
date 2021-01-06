<?php namespace Pckg\Queue\Service;

use Defuse\Crypto\Key;
use GuzzleHttp\Client;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQ implements DriverInterface
{
    /**
     * @var AMQPStreamConnection|AbstractConnection
     */
    protected $connection;

    protected $channels = [];

    protected $connectionConfig = [];

    public function __construct($connectionConfig)
    {
        $context = stream_context_create();
        $this->connection = new AMQPStreamConnection($connectionConfig['host'], $connectionConfig['port'],
                                                     $connectionConfig['user'], $connectionConfig['pass'], '/', false,
                                                     'AMQPLAIN', null, 'en_US', 3.0, 3.0, $context, true, 120);
        //$connectionConfig['pass'] = encryptBlob($connectionConfig['pass']);
        $this->connectionConfig = $connectionConfig;
    }

    /**
     * @return AbstractConnection|AMQPStreamConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return array|mixed
     */
    public function getQueues()
    {
        try {
            $client = new Client();
            $connectionConfig = $this->connectionConfig;
            $response = $client->get('http://' . $connectionConfig['host'] . ':15672/api/queues', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($connectionConfig['user'] . ':' . $connectionConfig['pass']),
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            error_log(exception($e));
            return [];
        }
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

    public function fullQueue($queue, callable $callback, array $options = [])
    {
        /**
         * Listen to typed queue.
         */
        $this->makeQueue($queue);

        /**
         * Number of concurrent send mail jobs.
         */
        $this->concurrency($options['concurrent'] ?? 1);

        /**
         * Start listening.
         */
        $this->receiveMessage(function($msg) use ($callback) {
            $ack = function($multiple = false) use ($msg) {
                try {
                    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag'], $multiple);
                } catch (\Throwable $e) {

                }
            };

            $nack = function($multiple = false, $requeue = true) use ($msg) {
                try {
                    $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'], $multiple,
                                                               $requeue);
                } catch (\Throwable $e) {

                }
            };

            $ok = $callback($msg, $ack, $nack);
        }, $queue);
    }

    public function makeQueue($queueName)
    {
        return $this->getChannel()->queue_declare($queueName, false, true, false, false);
    }

    public function makeExchangeQueue()
    {
        return $this->getChannel()->queue_declare('', false, false, true, false);
    }

    public function makeShoutQueue($channelName)
    {

        list($queue, ,) = $this->makeExchangeQueue();

        $this->getChannel()->queue_bind($queue, $channelName);

        return $queue;
    }

    public function makeExchange($exchangeName, $type = 'direct')
    {
        return $this->getChannel()->exchange_declare($exchangeName, $type, false, false, false);
    }

    public function makeShoutExchange($exchangeName, $type = 'fanout')
    {
        return $this->getChannel()->exchange_declare($exchangeName, $type, false, false, false);
    }

    public function queueMessage($message, $exchange, array $options = [])
    {
        $messageOptions = ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT];
        if (isset($options['priority'])) {
            $messageOptions['priority'] = $options['priority'];
        }
        $msg = new AMQPMessage($message, $messageOptions);

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
        $this->getChannel()->basic_qos(null, $concurrent, true);

        return $this->getChannel()->basic_qos(null, 1, false);
    }

    public function receiveMessage(callable $callback, $queueName, $exchange = '', $a = false)
    {
        $this->getChannel()->basic_consume($queueName, $exchange, false, $a, false, false, $callback);
    }

    public function receiveShoutMessage($queue, $callback)
    {
        $this->getChannel()->basic_consume($queue, '', false, true, false, false, $callback);
    }

    public function readCallbacks($blocking = true, $timeout = 0)
    {
        $channel = $this->getChannel();
        while (count($channel->callbacks)) {
            $channel->wait(null, !$blocking, $timeout);
        }
    }

    public function sleepCallbacks(callable $break)
    {
        $channel = $this->getChannel();
        while (count($channel->callbacks)) {
            $channel->wait(null, true);

            if ($break()) {
                break;
            }

            /**
             * We want to process messages as fast as possible when they are in queue.
             */
            if (!count($channel->callbacks)) {
                sleep(1);
            }
        }
    }

    public function close()
    {
        $this->getChannel()->close();
        $this->connection->close();
    }

    public function listenToShout(string $channelName, callable $listener, callable $condition)
    {
        $queue = $this->makeShoutQueue($channelName);

        $this->receiveShoutMessage($queue, $listener);

        $this->sleepCallbacks($condition);
    }

    public function prepareToListenShouted(string $channelName, callable $listener)
    {
        return $this->listenToShout($channelName, $listener, function() {
            return true;
        });
    }

    public function shout($channel, $message)
    {
        $msg = new AMQPMessage(is_array($message) || is_object($message) ? json_encode($message) : $message);

        $this->getChannel()->basic_publish($msg, $channel);
    }

}