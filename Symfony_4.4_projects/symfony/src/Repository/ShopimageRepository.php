<?php

namespace App\Repository;

use App\Entity\Shopimage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Shopimage|null find($id, $lockMode = null, $lockVersion = null)
 * @method Shopimage|null findOneBy(array $criteria, array $orderBy = null)
 * @method Shopimage[]    findAll()
 * @method Shopimage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShopimageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shopimage::class);
    }

    // /**
    //  * @return Shopimage[] Returns an array of Shopimage objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Shopimage
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
