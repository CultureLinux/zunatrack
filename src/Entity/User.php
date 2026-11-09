<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 8)] private string $locale = 'fr';
    #[ORM\Column(length: 12)] private string $theme = 'system';
    #[ORM\Column(length: 12)] private string $density = 'normal';
    #[ORM\Column(length: 12)] private string $fontSize = 'normal';
    #[ORM\Column(length: 12)] private string $accent = 'blue';
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): static
    {
        $this->password = $hashedPassword;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $value): static
    {
        $this->locale = $value;

        return $this;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function setTheme(string $value): static
    {
        $this->theme = $value;

        return $this;
    }

    public function getDensity(): string
    {
        return $this->density;
    }

    public function setDensity(string $value): static
    {
        $this->density = $value;

        return $this;
    }

    public function getFontSize(): string
    {
        return $this->fontSize;
    }

    public function setFontSize(string $value): static
    {
        $this->fontSize = $value;

        return $this;
    }

    public function getAccent(): string
    {
        return $this->accent;
    }

    public function setAccent(string $value): static
    {
        $this->accent = $value;

        return $this;
    }
}
