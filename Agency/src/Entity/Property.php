<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

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
    private $created_at;


    public function __construct(){

        $this->created_at = new \DateTime();
    }


    /**
     *
     */
    public function getBedrooms(): int
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

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getRooms(): int
    {
        return $this->rooms;
    }

    public function setRooms(int $rooms): self
    {
        $this->rooms = $rooms;

        return $this;
    }

    public function getSurface(): int
    {
        return $this->surface;
    }

    public function setSurface(int $surface): self
    {
        $this->surface = $surface;

        return $this;
    }

    public function getCreated_at()
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }
}
