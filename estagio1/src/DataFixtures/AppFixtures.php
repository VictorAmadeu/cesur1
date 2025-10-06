<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use DateTimeImmutable;
use App\Entity\User;
use App\Entity\Accounts;
use App\Entity\Companies;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $now = new DateTimeImmutable();

        // Admin account and user
        $account = new Accounts();
        $account->setName('LENGUASABROAD S.L');
        $account->setCreatedAt($now);
        $account->setUpdatedAt($now);
        $manager->persist($account);

        // Create default company
        $company = new Companies();
        $company->setName('LENGUASABROAD S.L.');
        $company->setNIF('B87496063');
        $company->setComercialName('LENGUASABROAD');
        $company->setAddress('C/ Antonio Machado, 1ºB');
        $company->setTown('Valdetorres del Jarama');
        $company->setCP('28150');
        $company->setProvince('Madrid');
        $company->setEmail('info@lenguasabroad.com');
        $company->setPhone('678499339');
        $company->setActive(true);
        $company->setRemove(false);
        $company->setAccounts($account);
        $company->setLogo('logoLeng.png');
        $company->setCreatedAt($now);
        $company->setUpdatedAt($now);
        $manager->persist($company);

        $manager->flush();

        $adminUser = new User();
        $adminUser->setPassword($this->userPasswordHasher->hashPassword($adminUser, 'Luire2205'));
        $adminUser->setRole('ROLE_SUPER_ADMIN');
        $adminUser->setName('Administrador');
        $adminUser->setEmail('info@ekium.es');
        $adminUser->setPhone('620127741');
        $adminUser->setLastname1(' ');
        $adminUser->setIsActive(true);
        $adminUser->setIsVerified(true);
        $adminUser->setAccounts($account);
        $adminUser->setCompany($company);
        $adminUser->setCreatedAt($now);
        $adminUser->setModifiedAt($now);
        $manager->persist($adminUser);

        $olgaUser = new User();
        $olgaUser->setPassword($this->userPasswordHasher->hashPassword($olgaUser, 'Passw0rd'));
        $olgaUser->setRole('ROLE_ADMIN');
        $olgaUser->setName('Olga');
        $olgaUser->setEmail('olga@lenguasabroad.com');
        $olgaUser->setPhone('678499339');
        $olgaUser->setLastname1('Julio');
        $olgaUser->setIsActive(true);
        $olgaUser->setIsVerified(true);
        $olgaUser->setAccounts($account);
        $olgaUser->setCompany($company);
        $olgaUser->setCreatedAt($now);
        $olgaUser->setModifiedAt($now);
        $manager->persist($olgaUser);

        $manager->flush();
    }
}
