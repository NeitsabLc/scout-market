<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\ConfigurationDistributionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: ConfigurationDistributionRepository::class)]
#[ORM\Table(name: 'configuration_distribution', schema: 'scout_market')]
class ConfigurationDistribution
{
    use EntityIdTrait;
    use TimestampableTrait;

    #[ORM\Column(name: 'distribution_publique_active', options: ['default' => true])]
    private bool $distributionPubliqueActive = true;

    #[ORM\Column(name: 'distribuer_gouter_dejeuner', options: ['default' => false])]
    private bool $distribuerGouterDejeuner = false;

    #[ORM\Column(name: 'jeton_distribution_publique', type: 'uuid', unique: true)]
    private Uuid $jetonDistributionPublique;

    public function __construct()
    {
        $this->initializeId();
        $this->initializeTimestamps();
        $this->jetonDistributionPublique = new UuidV7();
    }

    public function isDistributionPubliqueActive(): bool
    {
        return $this->distributionPubliqueActive;
    }

    public function setDistributionPubliqueActive(bool $actif): self
    {
        $this->distributionPubliqueActive = $actif;
        $this->touch();

        return $this;
    }

    public function isDistributionPubliqueOuverte(): bool
    {
        return $this->distributionPubliqueActive;
    }

    public function isDistribuerGouterDejeuner(): bool
    {
        return $this->distribuerGouterDejeuner;
    }

    public function setDistribuerGouterDejeuner(bool $valeur): self
    {
        $this->distribuerGouterDejeuner = $valeur;
        $this->touch();

        return $this;
    }

    public function getJetonDistributionPublique(): Uuid
    {
        return $this->jetonDistributionPublique;
    }

    public function renouvelerJetonDistributionPublique(): self
    {
        $this->jetonDistributionPublique = new UuidV7();
        $this->touch();

        return $this;
    }
}
