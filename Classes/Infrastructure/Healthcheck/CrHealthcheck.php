<?php

declare(strict_types=1);

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class CrHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private Connection $dbalConnection,
        private ConfigurationManager $configurationManager
    ) {
    }

    public function getTitle(): string
    {
        return 'Neos content repository';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        // TODO: Implement execute() method.

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

        $schemaManager = $this->dbalConnection->getSchemaManager();
        if (!$schemaManager instanceof AbstractSchemaManager) {
            throw new \RuntimeException('Failed to retrieve Schema Manager', 1691250062732);
        }

        $existingTableNames = $schemaManager->listTableNames();

        $unSetupContentRepositories = [];
        foreach ($crIdentifiers as $crIdentifier) {
            $eventTableName = sprintf('cr_%s_events', $crIdentifier);

            $isCrSetup = in_array($eventTableName, $existingTableNames, true);
            if (!$isCrSetup) {
                $unSetupContentRepositories[] = $crIdentifier;
            }
        }

        if (count($crIdentifiers) === count($unSetupContentRepositories)) {
            $rest = $unSetupContentRepositories;
            $first = array_shift($rest);
            $additionalNote = sprintf(' or setup %s.', join(' or ', $rest));

            return new Health(
                sprintf(
                    'No content repository is setup. Please run <code>{{flowCommand}} cr:setup%s</code>%s',
                    $environment->isSafeToLeakTechnicalDetails() ? ' --content-repository ' . $first : '',
                    $environment->isSafeToLeakTechnicalDetails() && count($rest) ? $additionalNote : ''
                ),
                Status::ERROR(),
            );
        }

        if (count($unSetupContentRepositories)) {
            $rest = $unSetupContentRepositories;
            $first = array_shift($rest);
            $additionalNote = sprintf(' or setup %s.', join(' or ', $rest));

            return new Health(
                sprintf(
                    '%s Please run <code>{{flowCommand}} cr:setup%s</code>%s',
                    count($unSetupContentRepositories) > 1 ? 'Some content repositories are not setup.' : 'A content repository is not setup.',
                    $environment->isSafeToLeakTechnicalDetails() ? ' --content-repository ' . $first : '',
                    $environment->isSafeToLeakTechnicalDetails() && count($rest) ? $additionalNote : ''
                ),
                Status::WARNING(),
            );
        }

        // TODO check if `cr:setup` needs to be rerun, to "migrate" projections?

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
