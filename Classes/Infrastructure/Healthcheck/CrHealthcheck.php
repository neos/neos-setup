<?php

declare(strict_types=1);

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Neos\ContentRepository\Core\Projection\ProjectionStatusType;
use Neos\ContentRepository\Core\Service\ContentRepositoryMaintainer;
use Neos\ContentRepository\Core\Service\ContentRepositoryMaintainerFactory;
use Neos\ContentRepository\Core\Subscription\ProjectionSubscriptionStatus;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\ContentRepositoryRegistry\Exception\InvalidConfigurationException;
use Neos\EventStore\Model\EventStore\StatusType;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class CrHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private ContentRepositoryRegistry $contentRepositoryRegistry
    ) {
    }

    public function getTitle(): string
    {
        return 'Neos content repository';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        $crIdentifiers = iterator_to_array(
            $this->contentRepositoryRegistry->getContentRepositoryIds()
        );

        if (count($crIdentifiers) === 0) {
            return new Health(
                'No content repository is configured.',
                Status::ERROR(),
            );
        }

        $unSetupContentRepositories = [];
        foreach ($crIdentifiers as $crIdentifier) {
            try {
                $crMaintainer = $this->contentRepositoryRegistry->buildService(
                    $crIdentifier,
                    new ContentRepositoryMaintainerFactory()
                );
            } catch (InvalidConfigurationException $e) {
                return new Health(
                    sprintf('Content repository %s is invalid configured%s', $crIdentifier->value, $environment->isSafeToLeakTechnicalDetails() ? ': ' . $e->getMessage() : ''),
                    Status::ERROR(),
                );
            }
            if ($this->isContentRepositorySetup($crMaintainer) === false) {
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

    private function isContentRepositorySetup(ContentRepositoryMaintainer $contentRepositoryMaintainer): bool
    {
        $status = $contentRepositoryMaintainer->status();
        if ($status->eventStoreStatus->type !== StatusType::OK) {
            return false;
        }
        foreach ($status->subscriptionStatus as $status) {
            if ($status instanceof ProjectionSubscriptionStatus) {
                if ($status->setupStatus->type !== ProjectionStatusType::OK) {
                    return false;
                }
            }
        }
        return true;
    }
}
