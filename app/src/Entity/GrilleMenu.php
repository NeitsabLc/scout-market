<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GrilleMenuRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: GrilleMenuRepository::class)]
#[ORM\Table(name: 'grille_menu', schema: 'scout_market')]
#[ORM\UniqueConstraint(name: 'uq_grille_menu_label', columns: ['label'])]
#[ORM\Index(name: 'idx_grille_menu_dates', columns: ['date_debut', 'date_fin'])]
#[ORM\HasLifecycleCallbacks]
class GrilleMenu
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 150)]
    private string $label;

    #[ORM\Column(name: 'date_debut', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dateDebut;

    #[ORM\Column(name: 'date_fin', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dateFin;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $label = '', ?\DateTimeImmutable $dateDebut = null, ?\DateTimeImmutable $dateFin = null)
    {
        $this->id = new UuidV7();
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
        $this->label = $label;
        $this->dateDebut = $dateDebut ?? new \DateTimeImmutable('today');
        $this->dateFin = $dateFin ?? $this->dateDebut;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getDateDebut(): \DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function getDateFin(): \DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDates(\DateTimeImmutable $dateDebut, \DateTimeImmutable $dateFin): self
    {
        if ($dateFin < $dateDebut) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure ou égale à la date de début.');
        }
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function actualiserDateModification(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
