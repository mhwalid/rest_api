<?php

namespace App\Entity;

use App\Repository\AddressRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $numero = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $voie = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $cdp = null;

    #[ORM\Column(length: 255)]
    private ?string $ville = null;

    #[ORM\Column(nullable: true)]
    private ?float $gpsLatitude = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $gpsLongitude = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(?int $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getVoie(): ?string
    {
        return $this->voie;
    }

    public function setVoie(?string $voie): static
    {
        $this->voie = $voie;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getGpsLatitude(): ?float
    {
        return $this->gpsLatitude;
    }

    public function setGpsLatitude(?float $gpsLatitude): static
    {
        $this->gpsLatitude = $gpsLatitude;

        return $this;
    }

    public function getGpsLongitude(): ?string
    {
        return $this->gpsLongitude;
    }

    public function setGpsLongitude(?string $gpsLongitude): static
    {
        $this->gpsLongitude = $gpsLongitude;

        return $this;
    }

    public function getCdp(): ?string
    {
        return $this->cdp;
    }

    public function setCdp(?string $cdp): static
    {
        $this->cdp = $cdp;

        return $this;
    }
}
