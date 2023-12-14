<?php

namespace Chorume\Application\Events;

use Discord\Parts\Channel\Message;

abstract class Event
{
    public function __invoke(Message $message): void
    {
        $this->handle($message);
    }

    abstract public function handle(Message $message): void;
}