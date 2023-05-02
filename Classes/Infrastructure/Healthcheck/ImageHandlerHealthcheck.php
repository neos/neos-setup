<?php

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Neos\Setup\Infrastructure\ImageHandler\ImageHandlerService;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class ImageHandlerHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private readonly ConfigurationManager $configurationManager,
        private readonly ImageHandlerService $imageHandlerService,
    ) {
    }

    public static function fromBootstrap(Bootstrap $bootstrap): HealthcheckInterface
    {
        return new self(
            $bootstrap->getObjectManager()->get(ConfigurationManager::class),
            $bootstrap->getObjectManager()->get(ImageHandlerService::class),
        );
    }

    public function getTitle(): string
    {
        return 'Image handling';
    }

    public function execute(): Health
    {
        $configuredDriver = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Imagine.driver'
        );

        if (!$configuredDriver) {
            return new Health(<<<'MSG'
            No image driver in <em>Neos.Imagine.driver</em> configured. For configuration you can use <code>./setup:imagehandler</code>
            MSG, Status::ERROR);
        }

        $preferredImageHandler = $this->imageHandlerService->getPreferredImageHandler();

        if ($configuredDriver !== $preferredImageHandler->driverName) {
            return new Health(<<<'MSG'
            You can use a more optional image driver than in <em>Neos.Imagine.driver</em> configured. For configuration you can use <code>./setup:imagehandler</code>
            MSG, Status::WARNING);
        }

        return new Health(<<<'MSG'
        The image driver is correctly setup
        MSG, Status::OK);
    }
}
