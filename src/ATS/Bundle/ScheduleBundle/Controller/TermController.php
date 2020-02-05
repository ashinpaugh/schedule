<?php

namespace ATS\Bundle\ScheduleBundle\Controller;

use ATS\Bundle\ScheduleBundle\Entity\Term;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Routing\ClassResourceInterface;

/**
 * Term controller.
 * 
 * @RouteResource("/term", pluralize=false)
 * 
 * @author Austin Shinpaugh <ashinpaugh@ou.edu>
 */
class TermController extends AbstractController implements ClassResourceInterface
{
    /**
     * Fetch a collection of terms and term blocks.
     * 
     * @View(serializerEnableMaxDepthChecks=true)
     */
    public function cgetAction()
    {
        $terms = $this->getRepo(Term::class)
            ->findBy([], ['year' => 'DESC', 'semester' => 'ASC'])
        ;
        
        return ['terms' => $terms];
    }
}
