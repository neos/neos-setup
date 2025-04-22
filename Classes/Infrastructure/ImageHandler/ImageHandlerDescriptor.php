<?php
declare(strict_types=1);

namespace Neos\Neos\Setup\Infrastructure\ImageHandler;

use Neos\Flow\Annotations as Flow;

/**
 * Value object for configuration entries in Neos.Neos.supportedImageHandlersByPreference
 * @Flow\Proxy(false)
 * @internal only for Neos.Neos.Setup, not to be used externally.
 */
readonly class ImageHandlerDescriptor
{
    public function __construct(
        public string $driverName,
        public string $description,
        public string $requiredPhpExtension,
        /** @var string[] PHP configuration in [key => expected value] notation */
        public array $requiredPhpConfiguration,
    ) {
    }
}
