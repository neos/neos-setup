<?php

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Neos\Flow\Core\Bootstrap;
use Neos\Neos\Domain\Repository\UserRepository;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class UserHealthcheck implements HealthcheckInterface
{
    private function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    public static function fromBootstrap(Bootstrap $bootstrap): HealthcheckInterface
    {
        return new self(
            $bootstrap->getObjectManager()->get(UserRepository::class)
        );
    }

    public function getTitle(): string
    {
        return 'Neos user';
    }

    public function execute(): Health
    {
        $users = $this->userRepository->findAll();
        if (!$users->count()) {
            return new Health(<<<'MSG'
            No Neos user created yet. To create an user run <code>./flow user:create --roles Administrator admin admin Jon Doe</code>
            MSG, Status::ERROR);
        }

        return new Health('At least one Neos user exists', Status::OK);
    }
}
