<?php

namespace Pckg\Queue\Service\Driver;

use Pckg\Queue\Service\DriverInterface;

class Mock implements DriverInterface
{
    protected $messages = [];

    public function __construct()
    {
    }

    public function makeQueue($channel)
    {
        return $this;
    }

    public function queueMessage($message, $channel, array $options = [])
    {
        $this->messages[$channel][] = $message;

        return $this;
    }

    public function getMessages()
    {
        return $this->messages;
    }
}
