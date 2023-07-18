<?php

namespace Neos\Neos\Setup\Infrastructure\ImageHandler;

use Neos\Flow\Annotations as Flow;

/** @Flow\Proxy(false) */
class ImageHandler
{
    public function __construct(
        /** @psalm-readonly */ public string $driverName,
        /** @psalm-readonly */ public string $description
    ) {
    }
}
