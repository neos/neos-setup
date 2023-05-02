<?php

namespace Neos\Neos\Setup\Infrastructure\ImageHandler;

use Neos\Flow\Annotations as Flow;

/** @Flow\Proxy(false) */
class ImageHandler
{
    public function __construct(
        public readonly string $driverName,
        public readonly string $description
    ) {
    }
}
