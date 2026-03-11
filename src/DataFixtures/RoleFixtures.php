<?php

namespace App\DataFixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RoleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $roles = [
            ['ROLE_ADMIN', 'Administrateur'],
            ['ROLE_MANAGER', 'Manager'],
            ['ROLE_USER', 'Utilisateur'],
        ];

        foreach ($roles as $r) {
            $role = new Role();
            $role->setName($r[0]);
            $role->setDescription($r[1]);
            
            $manager->persist($role);
        }

        $manager->flush();
    }
}
