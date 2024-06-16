<?php

declare(strict_types=1);

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class CrHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private ConfigurationManager $configurationManager,
        private ContentRepositoryRegistry $contentRepositoryRegistry
    ) {
    }

    public function getTitle(): string
    {
        return 'Neos content repository';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        // todo add contentRepositoryRegistry::getIds() ?
        $crIdentifiers = array_keys(
            $this->configurationManager
                ->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepositoryRegistry.contentRepositories') ?? []
        );

        if (count($crIdentifiers) === 0) {
            return new Health(
                'No content repository is configured.',
                Status::ERROR(),
            );
        }

        $unSetupContentRepositories = [];
        foreach ($crIdentifiers as $crIdentifier) {
            $cr = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString($crIdentifier));

            $crStatus = $cr->status();
            if (!$crStatus->isOk()) {
                $unSetupContentRepositories[] = $crIdentifier;
            }
        }

        if (count($crIdentifiers) === count($unSetupContentRepositories)) {
            $rest = $unSetupContentRepositories;
            $first = array_shift($rest);
            $additionalNote = count($rest) ? sprintf(' or with %s.', join(' or ', $rest)) : '';

            return new Health(
                sprintf(
                    '<code>{{flowCommand}} cr:status</code> reported a problem. Please run <code>{{flowCommand}} cr:setup%s</code>%s',
                    $environment->isSafeToLeakTechnicalDetails() ? ' --content-repository ' . $first : '',
                    $environment->isSafeToLeakTechnicalDetails() ? $additionalNote : ''
                ),
                Status::ERROR(),
            );
        }

        if (count($unSetupContentRepositories)) {
            $rest = $unSetupContentRepositories;
            $first = array_shift($rest);
            $additionalNote = count($rest) ? sprintf(' or with %s.', join(' or ', $rest)) : '';

            return new Health(
                sprintf(
                    '%s Please run <code>{{flowCommand}} cr:setup%s</code>%s',
                    '<code>{{flowCommand}} cr:status</code> reported a problem.',
                    $environment->isSafeToLeakTechnicalDetails() ? ' --content-repository ' . $first : '',
                    $environment->isSafeToLeakTechnicalDetails() ? $additionalNote : ''
                ),
                Status::WARNING(),
            );
        }

        if (count($crIdentifiers) === 1) {
            return new Health(
                sprintf('Content repository %sis setup.', $environment->isSafeToLeakTechnicalDetails() ? sprintf('"%s" ', $crIdentifiers[0]) : ''),
                Status::OK(),
            );
        }

        $additionalNote = sprintf('(%s) ', join(' and ', $crIdentifiers));
        return new Health(
            sprintf('All content repositories %sare setup.', $environment->isSafeToLeakTechnicalDetails() ? $additionalNote : ''),
            Status::OK(),
        );
    }
}
