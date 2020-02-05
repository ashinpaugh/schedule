<?php

namespace ATS\Bundle\ScheduleBundle\Controller;

use ATS\Bundle\ScheduleBundle\Entity\Instructor;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

/**
 * The instructor controller.
 * 
 * @ApiDoc()
 * @RouteResource("instructor", pluralize=false)
 * 
 * @author Austin Shinpaugh <ashinpaugh@ou.edu>
 */
class InstructorController extends AbstractController implements ClassResourceInterface
{
    /**
     * Fetches all the known instructors.
     * 
     * @View(serializerEnableMaxDepthChecks=true)
     */
    public function cgetAction()
    {
        $instructors = $this->getRepo(Instructor::class)
            ->findAll()
        ;
        
        return ['instructors' => $instructors];
    }
    
    /**
     * Get all the sections taught by an instructor.
     *
     * @View(templateVar="instructor", serializerEnableMaxDepthChecks=true)
     * 
     * @QueryParam(
     *     name="id",
     *     requirements="\d+",
     *     description="The instructor's campus ID.",
     *     strict=true,
     *     allowBlank=false
     * )
     *
     * @return mixed
     */
    public function getAction(ParamFetcher $fetcher)
    {
        $instructor = $this->getRepo(Instructor::class)
            ->find($fetcher->get('id'))
        ;
        
        if (!$instructor instanceof Instructor) {
            return null;
        }
        
        return ['instructor' => $instructor];
    }
    
    /**
     * Fetches all the known instructors and groups them by subject name.
     * 
     * @View(serializerEnableMaxDepthChecks=true)
     */
    public function cgetBySubjectAction()
    {
        $instructors = $this->getRepo(Instructor::class)
            ->getInstructorsBySubject()
        ;
        
        return ['instructors' => $instructors];
    }
}
