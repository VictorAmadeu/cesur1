<?php

namespace App\Service;

use App\Entity\UserExtraSegment;
use App\Entity\TimesRegister;
use Doctrine\ORM\EntityManagerInterface;
use App\Enum\SegmentConstants;
use App\Entity\User;

class UserExtraSegmentService
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function createFromTimesRegister(User $user, \DateTime $date, \DateTime $timeStart, \DateTime $timeEnd, int $type, string $comment): array
    {
        $date = \DateTime::createFromFormat('Y-m-d', $date->format('Y-m-d'));

        $timeStart = \DateTime::createFromFormat('H:i:s', $timeStart->format('H:i:s'));
        $timeEnd = \DateTime::createFromFormat('H:i:s', $timeEnd->format('H:i:s'));

        $segment = new UserExtraSegment();
        $segment->setUser($user);
        $segment->setDate($date);
        $segment->setTimeStart($timeStart);
        $segment->setTimeEnd($timeEnd);
        $segment->setType($type);
        $segment->setDescription($comment);

        $this->em->persist($segment);
        $this->em->flush();

        return ['message' => 'Segmento extra creado correctamente.', 'code' => 200];
    }

    public function createFromTimesRegisterManual(User $user, \DateTimeInterface $hourStart, \DateTimeInterface $hourEnd, int $type, string $comment): array
    {
        $date = \DateTime::createFromFormat('Y-m-d', $hourStart->format('Y-m-d'));

        $timeStart = \DateTime::createFromFormat('H:i:s', $hourStart->format('H:i:s'));
        $timeEnd = \DateTime::createFromFormat('H:i:s', $hourEnd->format('H:i:s'));

        $segment = new UserExtraSegment();
        $segment->setUser($user);
        $segment->setDate($date);
        $segment->setTimeStart($timeStart);
        $segment->setTimeEnd($timeEnd);
        $segment->setType($type);
        $segment->setDescription($comment);

        $this->em->persist($segment);
        $this->em->flush();

        return ['message' => 'Segmento extra creado correctamente.', 'code' => 200];
    }
}
