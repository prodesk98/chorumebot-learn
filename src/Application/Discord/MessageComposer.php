<?php

namespace Chorume\Application\Discord;

use Discord\Builders\MessageBuilder;
use Discord\Parts\Embed\Embed;

class MessageComposer
{
    private $discord;

    public function __construct($discord)
    {
        $this->discord = $discord;
    }

    public function embed(string $title, string $message, string $image = null, string $color = null): MessageBuilder
    {
        /**
         * @var Embed $embed
         */
        $embed = $this->discord->factory(Embed::class);
        $embed
            ->setTitle($title)
            ->setDescription($message);

        if ($image) $embed->setImage($image);
        if ($color) $embed->setColor($color);

        return MessageBuilder::new()->addEmbed($embed);
    }
}