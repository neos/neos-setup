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
        private ConfigurationManager $configurationManager,
        private ImageHandlerService  $imageHandlerService,
    )
    {
    }

    public function getTitle(): string
    {
        return 'Image handling';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        $imageHandlers = $this->imageHandlerService->determineAvailabilityForImageHandlers();

        if ($imageHandlers->readyCount() === 0) {
            $enableGdOnWindowsHelpText = $environment->executionEnvironment->isWindows
                ? ' To enable Gd for basic image driver support during development, uncomment <em>;extension=gd</em> in your php.ini (remove the <em>;</em>).'
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
                No image driver in <em>Neos.Imagine.driver</em> configured. Run <code>{{flowCommand}} setup:imagehandler</code> to configure one.
            MSG, Status::ERROR());
        }

        if ($imageHandlers->isReady($configuredDriver) === false) {
            return new Health(<<<MSG
                Currently, <em>Neos.Imagine.driver={$configuredDriver}</em> is configured, but your system does not meet the prerequisites. Run <code>{{flowCommand}} setup:imagehandler</code> for in-depth diagnostics and fix.
            MSG, Status::ERROR());
        }

        if ($configuredDriver !== $imageHandlers->preferredDriverName()) {
            return new Health(<<<MSG
                Currently, <em>Neos.Imagine.driver={$configuredDriver}</em> is configured, but for your system, <em>{$imageHandlers->preferredDriverName()}</em> might be more optimal. Run <code>{{flowCommand}} setup:imagehandler</code> for configuration.
            MSG, Status::WARNING());
        }

        if ($configuredDriver === 'Gd') {
            return new Health(<<<'MSG'
                Using GD in production environment is not recommended as it has some issues and can easily lead to blank pages due to memory exhaustion. Run <code>{{flowCommand}} setup:imagehandler</code> to reconfigure image handling.
            MSG, Status::WARNING());
        }

        return new Health(<<<MSG
            The image driver "{$configuredDriver}" is correctly configured and is the preferred driver for your system.
        MSG, Status::OK());
    }
}
