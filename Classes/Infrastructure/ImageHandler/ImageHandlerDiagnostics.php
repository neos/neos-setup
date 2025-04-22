<?php
declare(strict_types=1);

namespace Neos\Neos\Setup\Infrastructure\ImageHandler;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 * @internal only for Neos.Neos.Setup, not to be used externally.
 */
readonly class ImageHandlerDiagnostics
{
    public function __construct(
        public ImageHandlerDescriptor $descriptor,
        public bool                   $isReady,
        /** @var string[] $statusDetails */
        public array                  $statusDetails,
    )
    {
    }
}
