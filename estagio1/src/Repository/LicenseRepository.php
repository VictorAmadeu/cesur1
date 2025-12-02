<?php

namespace App\Repository;

use App\Entity\License;
use App\Entity\User;
use App\Entity\Companies;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repositorio de la entidad License.
 *
 * Aquí centralizamos las consultas personalizadas relacionadas con:
 * - Histórico de licencias por usuario y año.
 * - Cálculo y listado de licencias pendientes de aprobación
 *   para supervisores y administradores.
 *
 * @extends ServiceEntityRepository<License>
 *
 * @method License|null find($id, $lockMode = null, $lockVersion = null)
 * @method License|null findOneBy(array $criteria, array $orderBy = null)
 * @method License[]    findAll()
 * @method License[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LicenseRepository extends ServiceEntityRepository
{
    /**
     * Constructor estándar del repositorio.
     *
     * @param ManagerRegistry $registry Gestor de conexiones de Doctrine.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, License::class);
    }

    /**
     * Devuelve las licencias activas de un usuario en un año concreto.
     *
     * Se utiliza para construir el histórico de ausencias en el portal
     * de empleado (filtro por año).
     *
     * @param User|int $user Usuario (o su id) del que queremos las licencias.
     * @param int      $year Año a consultar (por ejemplo 2025).
     *
     * @return License[] Lista de licencias activas dentro del año indicado.
     */
    public function getTimesByUserYear($user, $year): array
    {
        // Fechas de inicio y fin del año solicitado
        $startDate = sprintf('%d-01-01', $year); // Inicio del año
        $endDate   = sprintf('%d-12-31', $year); // Fin del año

        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.dateStart BETWEEN :startDate AND :endDate')
            ->andWhere('t.isActive = true')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta licencias pendientes para un supervisor.
     *
     * Reglas:
     * - Solo tiene en cuenta licencias con status = 0 (EN PROCESO).
     * - Solo de usuarios asignados a ese supervisor (tabla AssignedUser).
     * - Solo licencias activas (isActive = true).
     *
     * @param User     $supervisor Supervisor autenticado.
     * @param int|null $userId     (Opcional) Filtrar por un empleado concreto.
     *
     * @return int Número de licencias pendientes para ese supervisor.
     */
    public function countPendingForSupervisor(User $supervisor, ?int $userId = null): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->innerJoin('l.user', 'u')
            // Join con la entidad AssignedUser a través del id de usuario
            ->innerJoin('App\Entity\AssignedUser', 'au', 'WITH', 'au.user = u.id')
            ->andWhere('au.supervisor = :supervisor')
            ->andWhere('l.status = 0')        // 0 = En proceso (pendiente)
            ->andWhere('l.isActive = true')   // Solo licencias activas
            ->setParameter('supervisor', $supervisor);

        // Si se pasa un usuario concreto, filtramos por él
        if ($userId) {
            $qb->andWhere('u.id = :targetUser')
               ->setParameter('targetUser', $userId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Devuelve listado de licencias pendientes para un supervisor.
     *
     * Reglas:
     * - Solo status = 0 (EN PROCESO).
     * - Solo usuarios asignados al supervisor (AssignedUser).
     * - Solo licencias activas.
     * - Ordenadas por fecha de inicio (más antiguas primero).
     *
     * @param User     $supervisor Supervisor autenticado.
     * @param int|null $userId     (Opcional) Filtrar por un empleado concreto.
     * @param int      $limit      Máximo de registros a devolver.
     * @param int      $offset     Desplazamiento para paginación.
     *
     * @return License[] Lista de licencias pendientes.
     */
    public function findPendingForSupervisor(
        User $supervisor,
        ?int $userId = null,
        int $limit = 10,
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->innerJoin('l.user', 'u')
            // Relación con AssignedUser: usuarios bajo la supervisión indicada
            ->innerJoin('App\Entity\AssignedUser', 'au', 'WITH', 'au.user = u.id')
            ->andWhere('au.supervisor = :supervisor')
            ->andWhere('l.status = 0')
            ->andWhere('l.isActive = true')
            ->orderBy('l.dateStart', 'ASC')
            ->setParameter('supervisor', $supervisor)
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($userId) {
            $qb->andWhere('u.id = :targetUser')
               ->setParameter('targetUser', $userId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Cuenta licencias pendientes para toda una empresa (admins).
     *
     * Reglas:
     * - Solo status = 0 (EN PROCESO).
     * - Solo licencias activas.
     * - Filtra por compañía, y opcionalmente por oficina o por usuario.
     *
     * @param Companies $company  Empresa asociada al admin.
     * @param int|null  $officeId (Opcional) Id de la oficina a filtrar.
     * @param int|null  $userId   (Opcional) Id de usuario concreto.
     *
     * @return int Número total de licencias pendientes en ese ámbito.
     */
    public function countPendingForCompany(
        Companies $company,
        ?int $officeId = null,
        ?int $userId = null
    ): int {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->innerJoin('l.user', 'u')
            ->andWhere('u.company = :company')
            ->andWhere('l.status = 0')
            ->andWhere('l.isActive = true')
            ->setParameter('company', $company);

        // Filtro opcional por oficina
        if ($officeId) {
            $qb->andWhere('u.office = :officeId')
               ->setParameter('officeId', $officeId);
        }

        // Filtro opcional por usuario
        if ($userId) {
            $qb->andWhere('u.id = :targetUser')
               ->setParameter('targetUser', $userId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Listado de licencias pendientes para una empresa (admins).
     *
     * Reglas:
     * - Solo status = 0 (EN PROCESO).
     * - Solo licencias activas.
     * - Filtra por compañía, y opcionalmente por oficina o usuario.
     * - Ordena por fecha de inicio.
     *
     * @param Companies $company  Empresa asociada al admin.
     * @param int|null  $officeId (Opcional) Id de la oficina a filtrar.
     * @param int|null  $userId   (Opcional) Id de usuario concreto.
     * @param int       $limit    Máximo de registros a devolver.
     * @param int       $offset   Desplazamiento para paginación.
     *
     * @return License[] Lista de licencias pendientes.
     */
    public function findPendingForCompany(
        Companies $company,
        ?int $officeId = null,
        ?int $userId = null,
        int $limit = 20,
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->innerJoin('l.user', 'u')
            ->andWhere('u.company = :company')
            ->andWhere('l.status = 0')
            ->andWhere('l.isActive = true')
            ->orderBy('l.dateStart', 'ASC')
            ->setParameter('company', $company)
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        // Filtro opcional por oficina
        if ($officeId) {
            $qb->andWhere('u.office = :officeId')
               ->setParameter('officeId', $officeId);
        }

        // Filtro opcional por usuario
        if ($userId) {
            $qb->andWhere('u.id = :targetUser')
               ->setParameter('targetUser', $userId);
        }

        return $qb->getQuery()->getResult();
    }
}
