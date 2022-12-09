<?php

namespace Pckg\Queue\Service;

interface DriverInterface
{
    public function makeQueue($channel);

    public function queueMessage($message, $channel, array $options = []);
}
