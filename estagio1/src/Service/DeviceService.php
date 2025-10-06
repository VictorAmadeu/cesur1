<?php

namespace App\Service;

use App\Entity\Device;
use Doctrine\ORM\EntityManagerInterface;

class DeviceService
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function checkDeviceId(?string $deviceId): array
    {
        $device = $this->em->getRepository(Device::class)->findOneBy(['deviceId' => $deviceId]);

        if (!$device) {
            return ['status' => 'error', 'message' => 'El dispositivo no está registrado.', 'code' => 404];
        }

        return ['status' => 'success', 'message' => 'El dispositivo está registrado.', 'name' =>  $device->getDeviceName(), 'code' => 200];
    }
}
