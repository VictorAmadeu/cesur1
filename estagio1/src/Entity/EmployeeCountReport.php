<?php

namespace App\Entity;

use App\Repository\EmployeeCountReportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmployeeCountReportRepository::class)]
class EmployeeCountReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $accountName;

    #[ORM\Column(length: 255)]
    private string $companyName;

    #[ORM\Column]
    private int $employeeCount;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $accountName, string $companyName, int $employeeCount)
    {
        $this->accountName = $accountName;
        $this->companyName = $companyName;
        $this->employeeCount = $employeeCount;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccountName(): string
    {
        return $this->accountName;
    }

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function getEmployeeCount(): int
    {
        return $this->employeeCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
