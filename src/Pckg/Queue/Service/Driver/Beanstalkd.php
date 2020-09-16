<?php namespace Pckg\Queue\Service\Driver;

use Pckg\Queue\Service\DriverInterface;
use Pheanstalk\Pheanstalk;

class Beanstalkd implements DriverInterface
{

    /**
     * @var Pheanstalk
     */
    protected $connection;

    /**
     * Beanstalkd constructor.
     */
    public function __construct()
    {
        $this->connection = Pheanstalk::create('beanstalkd-server');
    }

    /**
     * @param $channel
     */
    public function makeQueue($channel)
    {
    }

    /**
     * @param $message
     * @param $channel
     * @return \Pheanstalk\Job
     */
    public function queueMessage($message, $channel)
    {
        return $this->connection->useTube($channel)
            ->put(
                is_string($message) && @json_decode($message) ? $message : json_encode($message),
                Pheanstalk::DEFAULT_PRIORITY,
                0
            );
    }


}