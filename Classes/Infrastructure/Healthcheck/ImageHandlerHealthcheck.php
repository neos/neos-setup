<?php

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Neos\Setup\Infrastructure\ImageHandler\ImageHandlerService;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class ImageHandlerHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private readonly ConfigurationManager $configurationManager,
        private readonly ImageHandlerService $imageHandlerService,
    ) {
    }

    public function getTitle(): string
    {
        return 'Image handling';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        $availableImageHandlers = $this->imageHandlerService->getAvailableImageHandlers();

        if (count($availableImageHandlers) === 0) {
            $enableGdOnWindowsHelpText = $environment->executionEnvironment->isWindows
                ? ' To enabled Gd for basic image driver support during development, uncomment (remove the <em>;</em>) <em>;extension=gd</em> in your php.ini.'
                : '';

            return new Health(sprintf('No supported image handler found.%s', $enableGdOnWindowsHelpText), Status::ERROR());
        }

        $configuredDriver = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Imagine.driver'
        );

        if (!$configuredDriver) {
            // should never happen, as it defaults to GD
            return new Health(<<<'MSG'
            No image driver in <em>Neos.Imagine.driver</em> configured. For configuration you can use <code>{{flowCommand}} setup:imagehandler</code>
            MSG, Status::ERROR());
        }

        if ($this->imageHandlerService->isDriverEnabledInConfiguration($configuredDriver) === false) {
            return new Health(sprintf('The "driver" %s for Imagine must be enabled by settings, check Neos.Imagine.enabledDrivers. Or use <code>{{flowCommand}} setup:imagehandler</code>', $configuredDriver), Status::ERROR());
        }

        $preferredImageHandler = $this->imageHandlerService->getPreferredImageHandler();

        if ($configuredDriver !== $preferredImageHandler->driverName) {
            return new Health(<<<'MSG'
            You can use a more optional image driver than in <em>Neos.Imagine.driver</em> configured. For configuration you can use <code>{{flowCommand}} setup:imagehandler</code>
            MSG, Status::WARNING());
        }

        if ($configuredDriver === 'Gd') {
            return new Health(<<<'MSG'
            Using GD in production environment is not recommended as it has some issues and can easily lead to blank pages due to memory exhaustion.
            MSG, Status::WARNING());
        }

        return new Health(<<<'MSG'
        The image driver is correctly setup
        MSG, Status::OK());
    }
}
