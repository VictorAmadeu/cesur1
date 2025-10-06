<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Controller\Admin\AuxController;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Device;

#[Route('/api/device', methods: ['POST'])]
class DeviceController extends AbstractController
{
    private $em;
    private $aux;

    public function __construct(EntityManagerInterface $em, AuxController $aux)
    {
        date_default_timezone_set('Europe/Madrid');
        $this->em = $em;
        $this->aux = $aux;
    }

    #[Route('/can-register', name: 'device_can_register', methods: ['GET'])]
    public function canRegisterDevice(): JsonResponse
    {
        // Obtener el usuario autenticado
        $user = $this->getUser();

        // Obtener la empresa del usuario autenticado
        $company = $user->getCompany();

        // Verificar si la empresa permite el registro de dispositivos
        $allowRegistration = $company->getAllowDeviceRegistration();

        return new JsonResponse([
            'status' => 'success',
            'allowDeviceRegistration' => $allowRegistration
        ]);
    }

    #[Route('/check-registration', name: 'device_check_registration', methods: ['POST'])]
    public function checkDeviceRegistration(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['deviceId'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Falta el deviceId']);
        }

        $device = $this->em->getRepository(Device::class)->findOneBy(['deviceId' => $data['deviceId']]);

        if (!$device) {
            return new JsonResponse(['status' => 'error', 'message' => 'El dispositivo no está registrado.', 'code' => 400]);
        }

        return new JsonResponse(['status' => 'success', 'message' => 'El dispositivo está registrado.', 'name' =>  $device->getDeviceName(), 'code' => 200]);
    }

    #[Route('/register', name: 'device_register')]
    public function registerDevice(Request $request): JsonResponse
    {
        // Obtener los datos del cuerpo de la solicitud
        $data = json_decode($request->getContent(), true);

        // Validar si faltan parámetros
        if (!isset($data['deviceId']) || !isset($data['deviceType']) || !isset($data['deviceName'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Faltan parámetros.', 'code' => 400]);
        }

        // Validar que los parámetros no estén vacíos
        if (empty($data['deviceId']) || empty($data['deviceType']) || empty($data['deviceName'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Todos los campos son obligatorios.', 'code' => 400]);
        }

        // Validar longitud mínima y máxima de deviceId
        if (strlen($data['deviceId']) < 5 || strlen($data['deviceId']) > 50) {
            return new JsonResponse(['status' => 'error', 'message' => 'El ID del dispositivo debe tener entre 5 y 50 caracteres.', 'code' => 400]);
        }

        // Validar longitud mínima del nombre del dispositivo
        if (strlen($data['deviceName']) < 3) {
            return new JsonResponse(['status' => 'error', 'message' => 'El nombre del dispositivo debe tener al menos 3 caracteres.', 'code' => 400]);
        }

        // Obtener el usuario autenticado
        $user = $this->getUser();

        // Obtener la empresa asociada al usuario
        $company = $user->getCompany();

        // Verificar si la empresa permite el registro de dispositivos
        if (!$company->getAllowDeviceRegistration()) {
            return new JsonResponse(['status' => 'error', 'message' => 'El registro de dispositivos no está permitido para esta empresa.', 'code' => 400]);
        }

        // Verificar si el dispositivo ya está registrado por deviceId o deviceName
        $existingDeviceById = $this->em->getRepository(Device::class)->findOneBy(['deviceId' => $data['deviceId']]);
        $existingDeviceByName = $this->em->getRepository(Device::class)->findOneBy(['deviceName' => $data['deviceName']]);

        if ($existingDeviceById) {
            return new JsonResponse(['status' => 'error', 'message' => 'Este dispositivo ya está registrado por ID.', 'code' => 400]);
        }

        if ($existingDeviceByName) {
            return new JsonResponse(['status' => 'error', 'message' => 'Este nombre de dispositivo ya está en uso.', 'code' => 400]);
        }

        // Crear un nuevo dispositivo
        $device = new Device();
        $device->setDeviceId($data['deviceId']);
        $device->setDeviceType($data['deviceType']);
        $device->setDeviceName($data['deviceName']); // Guardar el nombre del dispositivo
        $device->setCompany($company); // Asociar la empresa
        $device->setRegisteredBy($user); // Asociar el usuario

        // Guardar el dispositivo en la base de datos
        $this->em->persist($device);
        $this->em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Dispositivo registrado exitosamente.', 'code' => 200]);
    }


}
