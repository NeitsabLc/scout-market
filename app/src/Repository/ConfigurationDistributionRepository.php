<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ConfigurationDistribution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ConfigurationDistribution> */
final class ConfigurationDistributionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfigurationDistribution::class);
    }

    public function unique(): ConfigurationDistribution
    {
        $configuration = $this->findOneBy([]);
        if (!$configuration instanceof ConfigurationDistribution) {
            throw new \RuntimeException('La configuration de distribution est absente.');
        }

        return $configuration;
    }

    public function trouverParJeton(string $jeton): ?ConfigurationDistribution
    {
        return $this->findOneBy(['jetonDistributionPublique' => $jeton, 'distributionPubliqueActive' => true]);
    }
}
