<?php

/**
 * Description of BaseController
 *
 * @author Nur Hidayatullah <kematjaya0@gmail.com>
 */

namespace Kematjaya\CrudMakerBundle\Controller\Base;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Lexik\Bundle\FormFilterBundle\Filter\FilterBuilderUpdaterInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;


class BaseController extends AbstractController{
    
    /**
     * @var limit
     */
    private $limit = 10;
    
    
    /**
     * @return Symfony\Component\Translation\TranslatorInterface
     */
    public function getTranslator(): TranslatorInterface
    {
        return $this->get('translator');
    }
    
    public function getQueryBuilder(string $entityClassName): QueryBuilder
    {
        $queryBuilder = $this->getDoctrine()->getEntityManager()->createQueryBuilder()
            ->select('this')
            ->from($entityClassName, 'this');
        return $queryBuilder;
    }
    
    public function createPaginator(Request $request, Query $query): Pagerfanta
    {
        if($request->get('_limit') && is_numeric($request->get('_limit'))) {
            $request->getSession()->set('limit', $request->get('_limit'));
        }
        
        if(!$request->getSession()->get("limit")) {
            $request->getSession()->set('limit', $this->limit);
        }
        $adapter = new DoctrineORMAdapter($query, false);
        $paginator = new Pagerfanta($adapter);
        $paginator->setAllowOutOfRangePages(true);
        //  Set pages based on the request parameters.
        $paginator->setMaxPerPage($request->getSession()->get("limit"), $this->limit);
        $paginator->setCurrentPage($request->query->get('page', 1));
        
        return $paginator;
    }
    
    public function setFilters($filters = array(), $name)
    {
        $this->get('session')->set($name, $filters);
    }
    
    public function getFilters($name)
    {
        return $this->get('session')->get($name, []);
    }
    
    public function getFilterAdapter(): FilterBuilderUpdaterInterface
    {
        return $this->get('lexik_form_filter.query_builder_updater');
    }
}
