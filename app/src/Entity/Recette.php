<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\ActivableTrait;
use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\RecetteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecetteRepository::class)]
#[ORM\Table(name: 'recette', schema: 'scout_market')]
#[ORM\UniqueConstraint(name: 'uq_recette_nom', columns: ['nom'])]
class Recette
{
    use EntityIdTrait;
    use TimestampableTrait;
    use ActivableTrait;
    public const CATEGORIES = ['PETIT_DEJEUNER', 'ENTREE', 'PLAT', 'FROMAGE', 'DESSERT', 'GOUTER'];
    public const CATEGORIES_MENU = ['ENTREE', 'PLAT', 'FROMAGE', 'DESSERT'];
    #[ORM\Column(length: 150)] private string $nom = '';
    #[ORM\Column(length: 20)] private string $categorie = 'PLAT';
    /** @var Collection<int, RecetteDenree> */
    #[ORM\OneToMany(mappedBy: 'recette', targetEntity: RecetteDenree::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $denrees;

    public function __construct()
    {
        $this->initializeId();
        $this->initializeTimestamps();
        $this->denrees = new ArrayCollection();
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

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): self
    {
        $this->categorie = $categorie;
        $this->touch();

        return $this;
    }

    /** @return Collection<int, RecetteDenree> */
    public function getDenrees(): Collection
    {
        return $this->denrees;
    }

    public function addDenree(RecetteDenree $ligne): self
    {
        if (!$this->denrees->contains($ligne)) {
            $this->denrees->add($ligne);
            $ligne->setRecette($this);
        }

        return $this;
    }

    public function removeDenree(RecetteDenree $ligne): self
    {
        $this->denrees->removeElement($ligne);

        return $this;
    }
}
