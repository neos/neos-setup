<?php

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Neos\Flow\Package\PackageManager;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;
use Neos\Setup\Domain\WebEnvironment;

class SiteHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly PackageManager $packageManager
    ) {
    }

    public function getTitle(): string
    {
        return 'Neos site';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        /** @var Site[] $sites */
        $sites = $this->siteRepository->findAll()->toArray();
        if (count($sites)) {
            if ($environment->executionEnvironment instanceof WebEnvironment) {
                $root = $environment->executionEnvironment->requestUri
                    ->withPath('')
                    ->withQuery('')
                    ->withFragment('');
                $neosLogin = $root->withPath('neos');
                $openNeosLink = sprintf('Visit your instance at <a href="%1$s">%1$s</a>. You can login via <a href="%2$s">%2$s</a>', $root, $neosLogin);
            } else {
                $openNeosLink = 'You can now visit your neos and login via at the path: <em>/neos</em>';
            }
            return new Health('Neos site exists. ' . $openNeosLink, Status::OK);
        }

        if (!$environment->isSafeToLeakTechnicalDetails()) {
            return new Health('No Neos site was created. You can run <code>./flow site:import</code> to import one.', Status::WARNING);
        }

        $availableSitePackagesToBeImported = [];
        foreach ($this->packageManager->getFilteredPackages('available', 'neos-site') as $sitePackage) {
            $possibleSiteContentToImport = sprintf('resource://%s/Private/Content/Sites.xml', $sitePackage->getPackageKey());
            if (file_exists($possibleSiteContentToImport)) {
                $availableSitePackagesToBeImported[] = $sitePackage->getPackageKey();
            }
        }

        if (count($availableSitePackagesToBeImported) === 0) {
            if (!$this->packageManager->isPackageAvailable('Neos.SiteKickstarter')) {
                return new Health(<<<MSG
                No Neos site was created. You might want to install the site kickstarter: <code>composer require neos/site-kickstarter</code>.
                Or you can create a new site package completely from scratch via <code>./flow package:create My.Site --package-type=neos-site</code>.
                After that you need to create a root NodeType (for the homepage) and setup basic rendering.
                Then you can create a site via <code>./flow site:create</code>.
                MSG, Status::WARNING);
            }

            return new Health(<<<MSG
            No Neos site was created. You can kickstart a new site package via <code>./flow kickstart:site My.Site my-site</code>
            and import it via <code>./flow site:import --package-key My.Site</code>
            MSG, Status::WARNING);
        }

        if (count($availableSitePackagesToBeImported) === 1) {
            $availableSitePackageKey = $availableSitePackagesToBeImported[0];
            return new Health(<<<MSG
            No Neos site was created. To import the site from $availableSitePackageKey you can run <code>./flow site:import --package-key $availableSitePackageKey</code>
            MSG, Status::WARNING);
        }

        $availableSitePackages = join(', ', $availableSitePackagesToBeImported);
        return new Health(<<<MSG
        No Neos site was created. To import from one of the available site packages ($availableSitePackages) you can run <code>./flow site:import --package-key Package.Key</code>
        MSG, Status::WARNING);
    }
}
