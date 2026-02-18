<?php

namespace App\Entity;

use App\Enum\SignalementStatus;
use App\Enum\PriorityLevel;
use App\Repository\SignalementRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SignalementRepository::class)]
class Signalement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(length: 50)]
    private ?string $priority = null;

    #[ORM\Column(type: 'float')]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float')]
    private ?float $longitude = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $updatedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'signalements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'signalements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\OneToMany(mappedBy: 'signalement', targetEntity: Media::class, cascade: ['remove'])]
    private Collection $medias;

    #[ORM\OneToMany(mappedBy: 'signalement', targetEntity: Intervention::class)]
    private Collection $interventions;

    #[ORM\OneToMany(mappedBy: 'signalement', targetEntity: Notification::class)]
    private Collection $notifications;

    public function __construct()
    {
        $this->medias = new ArrayCollection();
        $this->interventions = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->createdAt = new DateTime();
        $this->status = SignalementStatus::NEW->value;
        $this->priority = PriorityLevel::MEDIUM->value;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): ?SignalementStatus
    {
        return $this->status ? SignalementStatus::from($this->status) : null;
    }

    public function setStatus(SignalementStatus $status): static
    {
        $this->status = $status->value;

        return $this;
    }

    public function getPriority(): ?PriorityLevel
    {
        return $this->priority ? PriorityLevel::from($this->priority) : null;
    }

    public function setPriority(PriorityLevel $priority): static
    {
        $this->priority = $priority->value;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function addMedia(Media $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setSignalement($this);
        }

        return $this;
    }

    public function removeMedia(Media $media): static
    {
        if ($this->medias->removeElement($media)) {
            if ($media->getSignalement() === $this) {
                $media->setSignalement(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Intervention>
     */
    public function getInterventions(): Collection
    {
        return $this->interventions;
    }

    public function addIntervention(Intervention $intervention): static
    {
        if (!$this->interventions->contains($intervention)) {
            $this->interventions->add($intervention);
            $intervention->setSignalement($this);
        }

        return $this;
    }

    public function removeIntervention(Intervention $intervention): static
    {
        if ($this->interventions->removeElement($intervention)) {
            if ($intervention->getSignalement() === $this) {
                $intervention->setSignalement(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setSignalement($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            if ($notification->getSignalement() === $this) {
                $notification->setSignalement(null);
            }
        }

        return $this;
    }

    public function changeStatus(SignalementStatus $newStatus): void
    {
        if ($this->status !== $newStatus->value) {
            $this->status = $newStatus->value;
            $this->updatedAt = new DateTime();
        }
    }

    public function assignAgent(User $agent): ?Intervention
    {
        // Create an intervention record for the assigned agent
        $intervention = new Intervention();
        $intervention->setSignalement($this);
        $intervention->setStartDate(new DateTime());
        
        // Add the intervention to this signalement
        $this->addIntervention($intervention);
        
        // Update status to IN_PROGRESS
        $this->changeStatus(SignalementStatus::IN_PROGRESS);
        $this->updatedAt = new DateTime();
        $this->updatedBy = (string)$agent->getId();
        
        return $intervention;
    }
}
