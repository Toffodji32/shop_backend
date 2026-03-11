<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Retourne les produits filtrés par catégorie et/ou recherche
     *
     * @param int|null $categoryId
     * @param string|null $search
     * @return Product[]
     */
    public function findByFilters(?int $categoryId, ?string $search): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.categorie', 'c')
            ->addSelect('c');

        if ($categoryId) {
            $qb->andWhere('c.id = :category')
               ->setParameter('category', $categoryId);
        }

        if ($search) {
            $qb->andWhere('p.name LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
