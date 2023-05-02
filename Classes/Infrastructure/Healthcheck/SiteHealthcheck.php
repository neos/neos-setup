<?php

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class SiteHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private readonly SiteRepository $siteRepository
    ) {
    }

    public function getTitle(): string
    {
        return 'Neos site';
    }

    public function execute(): Health
    {
        if ($this->siteRepository->findAll()->count() === 0) {
            return new Health(<<<'MSG'
            No Neos site site was created. You can run <code>./flow site:import</code>
            MSG, Status::WARNING);
        }
        return new Health('Neos site exists.', Status::OK);
    }
}
