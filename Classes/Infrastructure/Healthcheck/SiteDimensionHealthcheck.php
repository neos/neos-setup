<?php

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryIds;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Repository\UserRepository;
use Neos\Neos\Domain\Service\SiteService;
use Neos\Neos\FrontendRouting\DimensionResolution\Resolver\UriPathResolverFactory;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class SiteDimensionHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private SiteRepository            $siteRepository,
        private ContentRepositoryRegistry $contentRepositoryRegistry,
    )
    {
    }

    public function getTitle(): string
    {
        return 'Neos Site/Dimension Configuration';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        $sites = $this->siteRepository->findAll();
        if (count($sites->toArray()) === 0) {
            return new Health(
                'No site was added to the system.',
                Status::NOT_RUN(),
            );
        }

        $registeredContentRepositoryIds = $this->contentRepositoryRegistry->getContentRepositoryIds();
        foreach ($sites as $site) {
            assert($site instanceof Site);
            $siteConfiguration = $site->getConfiguration();
            if (!self::containsId($siteConfiguration->contentRepositoryId, $registeredContentRepositoryIds)) {
                return new Health(
                    'For Site ' . $site->getNodeName() . ', the configured Content Repository ' . $siteConfiguration->contentRepositoryId->value . ' is not registered. Please adjust the contentRepositoryId in Settings.yaml at Neos.Neos.sites / Neos.Neos.sitePresets, and then clear caches via ./flow flow:cache:flush --force.',
                    Status::ERROR(),
                );
            }

            $contentRepository = $this->contentRepositoryRegistry->get($siteConfiguration->contentRepositoryId);

            if (!$contentRepository->getVariationGraph()->getDimensionSpacePoints()->contains($siteConfiguration->defaultDimensionSpacePoint)) {
                return new Health(
                    'For Site ' . $site->getNodeName() . ', the defaultDimensionSpacePoint ' . $siteConfiguration->defaultDimensionSpacePoint->toJson() . ' is not part of the configured dimensions of ContentRepository ' . $contentRepository->id->value . ' ' . $contentRepository->getVariationGraph()->getDimensionSpacePoints()->toJson() . '. You need to change Settings.yaml at Neos.Neos.sites.*.contentDimensions.defaultDimensionSpacePoint, and then clear the cache via ./flow flow:cache:flush --force.',
                    Status::ERROR(),
                );
            }
        }

        return new Health(
            'Site and Content Repository Dimensions match - all good!',
            Status::OK(),
        );
    }


    private static function containsId(ContentRepositoryId $needle, ContentRepositoryIds $ids)
    {
        foreach ($ids as $id) {
            if ($id->equals($needle)) {
                return true;
            }
        }
        return false;
    }
}
