<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PropertyRepository")
 */
class Property
{

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="text")
     */
    private $description;

    /**
     * @ORM\Column(type="smallint", options={"unsigned"=true, "not null"=true, "default"=1})
     * @Assert\Range(min="1", max="10")
     */
    private $rooms;

    /**
     * @ORM\Column(type="smallint", options={"not null"=true, "unsigned"=true, "default"=1})
     */
    private $bedrooms;

    /**
     * @ORM\Column(type="smallint", options={"unsigned"=true})
     */
    private $surface;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\Column(type="string", length=255, options={"not null"=false, "default"="100000"})
     */
    private $price;


    public function __construct(){

        $this->created = new \DateTime();
    }


    /**
     *
     */
    public function getBedrooms(): ?int
    {
        return $this->bedrooms;
    }


    /**
     * @param $bedrooms
     * @return Property
     */
    public function setBedrooms($bedrooms): self
    {
        $this->bedrooms = $bedrooms;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getRooms(): ?int
    {
        return $this->rooms;
    }

    public function setRooms(int $rooms): self
    {
        $this->rooms = $rooms;

        return $this;
    }

    public function getSurface(): ?int
    {
        return $this->surface;
    }

    public function setSurface(int $surface): self
    {
        $this->surface = $surface;

        return $this;
    }

    public function getCreated() : ?string
    {
        return $this->created->format('Y-m-d H:i:s');
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }


    public function getFormatPrice(): ?string
    {
        return number_format($this->price, 0, '', ' ');
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;

        return $this;
    }
}
