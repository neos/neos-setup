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
        $configuredDriver = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Imagine.driver'
        );

        if (!$configuredDriver) {
            return new Health(<<<'MSG'
            No image driver in <em>Neos.Imagine.driver</em> configured. For configuration you can use <code>{{flowCommand}} setup:imagehandler</code>
            MSG, Status::ERROR);
        }

        $preferredImageHandler = $this->imageHandlerService->getPreferredImageHandler();

        if ($configuredDriver !== $preferredImageHandler->driverName) {
            return new Health(<<<'MSG'
            You can use a more optional image driver than in <em>Neos.Imagine.driver</em> configured. For configuration you can use <code>{{flowCommand}} setup:imagehandler</code>
            MSG, Status::WARNING);
        }

        return new Health(<<<'MSG'
        The image driver is correctly setup
        MSG, Status::OK);
    }
}
