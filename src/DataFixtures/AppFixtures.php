<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        // ===== USERS =====

        // ADMIN
        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setName('Admin User');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword(
            $this->hasher->hashPassword($admin, 'admin123')
        );
        $manager->persist($admin);

        // CLIENT
        $client = new User();
        $client->setEmail('client@test.com');
        $client->setName('Client User');
        $client->setRoles(['ROLE_CLIENT']);
        $client->setPassword(
            $this->hasher->hashPassword($client, 'client123')
        );
        $manager->persist($client);


        // ===== CATEGORIES =====

        $cat1 = new Category();
        $cat1->setName('Chaussures');
        $manager->persist($cat1);

        $cat2 = new Category();
        $cat2->setName('Vêtements');
        $manager->persist($cat2);

        $cat3 = new Category();
        $cat3->setName('Accessoires');
        $manager->persist($cat3);


        // ===== PRODUCTS =====

        $p1 = new Product();
        $p1->setName('Nike Air Force');
        $p1->setPrice(120);
        $p1->setDescription('Chaussure tendance');
        $p1->setImage('nike.jpg');
        $p1->setCategorie($cat1);
        $manager->persist($p1);

        $p2 = new Product();
        $p2->setName('Chemise élégante');
        $p2->setPrice(40);
        $p2->setDescription('Chemise classe');
        $p2->setImage('chemise.jpg');
        $p2->setCategorie($cat2);
        $manager->persist($p2);

        $p3 = new Product();
        $p3->setName('Casquette sport');
        $p3->setPrice(25);
        $p3->setDescription('Casquette stylée');
        $p3->setImage('casquette.jpg');
        $p3->setCategorie($cat3);
        $manager->persist($p3);


        $manager->flush();
    }
}
