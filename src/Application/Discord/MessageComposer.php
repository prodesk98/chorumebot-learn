<?php

namespace Chorume\Application\Discord;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;

class MessageComposer
{
    public function __construct(private Discord $discord)
    {
    }

    public function embed(string $title, string $message, string $image = null, string $color = null, string $file = null): MessageBuilder
    {
        $embed = new Embed($this->discord);
        $embed
            ->setTitle($title)
            ->setDescription($message);

        if ($image) {
            $embed->setImage($image);
        }
        if ($color) {
            $embed->setColor($color);
        }

        $messageBuilded = MessageBuilder::new()->addEmbed($embed);

        if ($file) $messageBuilded->addFile($file);

        return $messageBuilded;
    }
}
