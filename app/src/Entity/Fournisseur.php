<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\ActivableTrait;
use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\FournisseurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FournisseurRepository::class)]
#[ORM\Table(name: 'fournisseur', schema: 'scout_market')]
#[ORM\Index(name: 'idx_fournisseur_nom', columns: ['nom'])]
#[ORM\UniqueConstraint(name: 'uq_fournisseur_nom', columns: ['nom'])]
class Fournisseur
{
    use EntityIdTrait;
    use TimestampableTrait;
    use ActivableTrait;
    public const ADRESSE_LONGUEUR_MAX = 1000;

    #[ORM\Column(length: 150)]
    private string $nom;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adresse = null;

    public function __construct(string $nom)
    {
        $this->initializeId();
        $this->initializeTimestamps();
        $this->nom = $nom;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        $this->touch();

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $tel): self
    {
        $this->telephone = $tel;
        $this->touch();

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $mail): self
    {
        $this->email = $mail;
        $this->touch();

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        if (null !== $adresse && mb_strlen($adresse) > self::ADRESSE_LONGUEUR_MAX) {
            throw new \InvalidArgumentException('L’adresse ne peut pas dépasser 1 000 caractères.');
        }
        $this->adresse = $adresse;
        $this->touch();

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom;
    }
}
