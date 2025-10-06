<?php

// src/Service/ExportService.php
namespace App\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Filesystem\Filesystem;
use App\Service\FilterSelectionService;
use App\Service\FilterSelectionOfficeService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Entity\User;
use App\Entity\Companies;
use App\Entity\Office;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TimesRegisterRepository;
use App\Repository\OfficeRepository;
use App\Repository\CompaniesRepository;
use App\Repository\UserRepository;

class ExportService
{
    private FilterSelectionService $filterSelectionService;
    private FilterSelectionOfficeService $filterSelectionOfficeService;
    private Security $security;
    private ParameterBagInterface $params;
    private TimesRegisterRepository $timesRegisterRepository;
    private EntityManagerInterface $em;
    private OfficeRepository $officeRepository;
    private CompaniesRepository $companiesRepository;
    private UserRepository $userRepository;

    public function __construct(
        FilterSelectionService $filterSelectionService,
        FilterSelectionOfficeService $filterSelectionOfficeService,
        Security $security,
        ParameterBagInterface $params,
        TimesRegisterRepository $timesRegisterRepository,
        OfficeRepository $officeRepository,
        CompaniesRepository $companiesRepository,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ) {
        $this->filterSelectionService = $filterSelectionService;
        $this->filterSelectionOfficeService = $filterSelectionOfficeService;
        $this->security = $security;
        $this->params = $params;
        $this->em = $em;
        $this->timesRegisterRepository = $timesRegisterRepository;
        $this->officeRepository = $officeRepository;
        $this->companiesRepository = $companiesRepository;
        $this->userRepository = $userRepository;
    }

    public function exportReport($com, $off, $us, $start, $end): array
    {
        $user = $this->security->getUser();
        $company = $user->getCompany();
        $selectedCompany = $this->companiesRepository->findOneBy(['id' => $com]);
        $allowProject = $selectedCompany->getAllowProjects();

        $selectedOffice = ($off && $off !== 'all')
            ? $this->officeRepository->findOneBy(['id' => $off])
            : null;

        $selectedUser = ($us && $us !== 'all')
            ? $this->userRepository->findOneBy(['id' => $us])
            : null;

        $timesRegisters = [];

        $shouldFetchAllUsers = !$selectedUser && $us === 'all';
        $shouldFetchAllOffices = !$selectedOffice || $off === 'all';


        if ($selectedUser && $selectedOffice) {
            // Caso: usuario específico y oficina seleccionada
            $timesRegisters = $this->timesRegisterRepository->findByFilters(
                $selectedUser,
                $selectedCompany,
                $start,
                $end
            );
        } elseif ($shouldFetchAllUsers && $selectedOffice) {
            // Caso: todos los usuarios de una oficina
            $userIds = $this->getUserIds($selectedCompany, $selectedOffice);
            if (!empty($userIds)) {
                $timesRegisters = $this->timesRegisterRepository->findByUserIdsAndDateRange($userIds, $start, $end);
            }
        } elseif ($shouldFetchAllOffices) {
            // Caso: todos los usuarios de la compañía
            $userIds = $this->getUserIds($selectedCompany);
            if (!empty($userIds)) {
                $timesRegisters = $this->timesRegisterRepository->findByUserIdsAndDateRange($userIds, $start, $end);
            }
        }


        // Agrupar los registros por usuario
        $groupedByUser = [];
        foreach ($timesRegisters as $register) {
            $userId = $register->getUser()->getId();
            if (!isset($groupedByUser[$userId])) {
                $groupedByUser[$userId] = [];
            }
            $groupedByUser[$userId][] = $register;
        }

        $usersPerFile = 50;
        $userChunks = array_chunk($groupedByUser, $usersPerFile, true); // Preserve keys

        $filesystem = new Filesystem();
        $uploadDirectory = $this->params->get('kernel.project_dir') . '/public/uploads/export/';
        if (!is_dir($uploadDirectory)) {
            $filesystem->mkdir($uploadDirectory);
        }

        if (!$selectedOffice) {
            $name = $selectedCompany ? $selectedCompany->getComercialName() : 'N/A';
        } else {
            $name = $selectedOffice ? $selectedOffice->getName() : 'N/A';
        }

        $safeUserName = $this->slugify($name);
        $baseFileName = "{$safeUserName}_{$start}_{$end}";

        $files = [];

        foreach ($userChunks as $index => $chunk) {
            $spreadsheet = new Spreadsheet();

            $headers = ['ID', 'Fecha', 'Inicio', 'Fin', 'Comentarios', 'Slot', 'Tiempo Total Slot', 'Tiempo Total'];
            if ($allowProject) {
                $headers[] = 'Proyecto';
            }

            foreach ($chunk as $userId => $userRegisters) {
                $user = $this->em->getRepository(User::class)->find($userId);

                $sheet = $spreadsheet->createSheet();
                $title = $user ? sprintf('%s %s', $user->getName(), $user->getLastName1()) : 'N/A';
                $sheet->setTitle(substr($title, 0, 31));

                $colLetter = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue($colLetter . '1', $header);
                    $colLetter++;
                }

                $row = 2;
                foreach ($userRegisters as $register) {
                    $sheet->setCellValue('A' . $row, $register->getId());
                    $sheet->setCellValue('B' . $row, $register->getDate()->format('d/m/Y'));
                    $sheet->setCellValue('C' . $row, $register->getHourStart()->format('H:i:s'));
                    $sheet->setCellValue('D' . $row, $register->getHourEnd()->format('H:i:s'));
                    $sheet->setCellValue('E' . $row, $register->getComments());
                    $sheet->setCellValue('F' . $row, $register->getSlot());
                    $sheet->setCellValue('G' . $row, $register->getTotalSlotTime());
                    $sheet->setCellValue('H' . $row, $register->getTotalTime());

                    if ($allowProject) {
                        $sheet->setCellValue('I' . $row, $register->getProject() ? $register->getProject()->getName() : 'N/A');
                    }

                    $row++;
                }
            }

            $spreadsheet->removeSheetByIndex(0);

            $fileIndex = $index + 1;
            $fileName = "{$baseFileName}.xlsx";
            $filePath = $uploadDirectory . $fileName;

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            $files[] = [
                'file_name' => $fileName,
                'file_path' => '/uploads/export/' . $fileName,
            ];
        }

        return $files;
    }

    private function getUserIds(Companies $company, ?Office $office = null): array
    {
        $qb = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('u.id')
            ->where('u.company = :company')
            ->andWhere('u.isActive = true')
            ->setParameter('company', $company);

        if ($office) {
            $qb->andWhere('u.office = :office')
                ->setParameter('office', $office);
        }

        return $qb->getQuery()->getSingleColumnResult();
    }

    private function slugify($text): string
    {
        // Pasa a minúsculas
        $text = strtolower($text);

        // Reemplaza caracteres especiales por ASCII (opcional)
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

        // Reemplaza todo lo que no sea letra o número por un guion
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Elimina guiones al principio o final
        $text = trim($text, '-');

        return $text;
    }
}
