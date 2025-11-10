<?php

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryIds;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Model\SiteConfiguration;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;
use Neos\Utility\Arrays;

class SiteConfigurationHealthcheck implements HealthcheckInterface
{
    /**
     * @var array
     * @phpstan-var array<string,array<string,mixed>>
     */
    #[Flow\InjectConfiguration(path: 'sites', package: 'Neos.Neos')]
    protected $sitesConfiguration = [];

    /**
     * @var array
     * @phpstan-var array<string,mixed>
     */
    #[Flow\InjectConfiguration(path: 'sitePresets', package: 'Neos.Neos')]
    protected $sitePresetsConfiguration = [];

    public function __construct(
        private ContentRepositoryRegistry $contentRepositoryRegistry,
    ) {
    }

    public function getTitle(): string
    {
        return 'Neos site configuration';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        if (!isset($this->sitesConfiguration['*'])) {
            return new Health(
                'No default site configuration exist at Neos.Neos.sites.*.',
                Status::ERROR(),
            );
        }

        $registeredContentRepositoryIds = $this->contentRepositoryRegistry->getContentRepositoryIds();

        foreach ($this->sitesConfiguration as $siteNameRaw => $siteExplicitConfiguration) {
            if ($siteNameRaw !== '*') {
                if (preg_match(NodeName::PATTERN, $siteNameRaw) !== 1) {
                    $transliterated = NodeName::transliterateFromString($siteNameRaw);
                    return new Health(
                        sprintf('The site configuration for Neos.Neos.sites%s will never match a site as the pattern is invalid%s.', $environment->isSafeToLeakTechnicalDetails() ? '.' . $siteNameRaw : '', $environment->isSafeToLeakTechnicalDetails() ? ' did you meant to use ' . $transliterated->value : ''),
                        Status::ERROR(),
                    );
                }
            }

            // merge site configuration (TODO share code with Site::getConfiguration()? but we need to have this for any site and validate configuration already before it might be possible to build the DTOs)
            if (isset($siteExplicitConfiguration['preset'])) {
                if (!is_string($siteExplicitConfiguration['preset'])) {
                    return new Health(
                        $environment->isSafeToLeakTechnicalDetails() ? sprintf('Invalid "preset" configuration for "Neos.Neos.sites.%s". Expected string, got: %s', $siteNameRaw, get_debug_type($siteExplicitConfiguration['preset'])) : 'Site preset is not correctly used in Neos.Neos.sites',
                        Status::ERROR(),
                    );
                }
                if (!isset($this->sitePresetsConfiguration[$siteExplicitConfiguration['preset']]) || !is_array($this->sitePresetsConfiguration[$siteExplicitConfiguration['preset']])) {
                    return new Health(
                        $environment->isSafeToLeakTechnicalDetails() ? sprintf('Site configuration "Neos.Neos.sites.%s" refer to a preset "%s", but no corresponding preset is configured', $siteNameRaw, $siteExplicitConfiguration['preset']) : 'Site preset is not correctly configured in Neos.Neos.sitePresets',
                        Status::ERROR(),
                    );
                }
                $siteMergedConfiguration = Arrays::arrayMergeRecursiveOverrule($this->sitePresetsConfiguration[$siteExplicitConfiguration['preset']], $siteExplicitConfiguration);
                unset($siteMergedConfiguration['preset']);
            } else {
                $siteMergedConfiguration = $siteExplicitConfiguration;
            }

            try {
                $siteConfiguration = SiteConfiguration::fromArray($siteMergedConfiguration);
            } catch (\Throwable $e) {
                return new Health(
                    $environment->isSafeToLeakTechnicalDetails() ? sprintf('Site configuration "Neos.Neos.sites.%s" is invalid: %s', $siteNameRaw, $e->getMessage()) : 'Invalid site configuration in Neos.Neos.sites',
                    Status::ERROR(),
                );
            }

            if (!self::containsId($siteConfiguration->contentRepositoryId, $registeredContentRepositoryIds)) {
                return new Health(
                    $environment->isSafeToLeakTechnicalDetails() ? sprintf('Site configuration "Neos.Neos.sites.%s" uses a content repository "%s" which is not registered. Please adjust the "contentRepositoryId" in Neos.Neos.sites or Neos.Neos.sitePresets.', $siteNameRaw, $siteConfiguration->contentRepositoryId->value) : 'Invalid content repository used in site configuration in Neos.Neos.sites',
                    Status::ERROR(),
                );
            }

            $contentRepository = $this->contentRepositoryRegistry->get($siteConfiguration->contentRepositoryId);

            if (!$contentRepository->getVariationGraph()->getDimensionSpacePoints()->contains($siteConfiguration->defaultDimensionSpacePoint)) {
                return new Health(
                    sprintf(
                        'Site configuration "Neos.Neos.sites.%1$s" uses defaultDimensionSpacePoint %2$s which not part of the configured dimensions %3$s of content repository %4s. You need to change Settings.yaml at Neos.Neos.sites.%1$s.contentDimensions.defaultDimensionSpacePoint, and then clear the cache via {{flowCommand}} flow:cache:flush --force.',
                        $siteNameRaw,
                        $siteConfiguration->defaultDimensionSpacePoint->toJson(),
                        $contentRepository->getVariationGraph()->getDimensionSpacePoints()->toJson(),
                        $contentRepository->id->value,
                    ),
                    Status::ERROR(),
                );
            }
        }

        return new Health(
            'Neos sites are correctly configured',
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
