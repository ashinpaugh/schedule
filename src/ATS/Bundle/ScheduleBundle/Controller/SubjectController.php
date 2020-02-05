<?php

namespace ATS\Bundle\ScheduleBundle\Controller;

use ATS\Bundle\ScheduleBundle\Entity\Subject;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

/**
 * Loads a subject.
 * 
 * @ApiDoc()
 * 
 * @RouteResource("subject", pluralize=false)
 * @View(serializerEnableMaxDepthChecks=true)
 * 
 * @author Austin Shinpaugh <ashinpaugh@ou.edu>
 */
class SubjectController extends AbstractController implements ClassResourceInterface
{
    /**
     * Get the list of available subjects.
     * 
     * @View(serializerEnableMaxDepthChecks=true)
     * 
     * @return Subject[]
     */
    public function cgetAction()
    {
        $subjects = $this->getRepo(Subject::class)
            ->findAll()
        ;
        
        return ['subjects' => $subjects];
    }
    
    /**
     * Get the courses related to a subject.
     *
     * @Route(
     *     requirements={
     *      "subject_id": "\d+"
     *     }
     * )
     * 
     * @View(serializerGroups={"Default", "courses", "sections"}, serializerEnableMaxDepthChecks=true)
     * 
     * @param integer $subject_id
     *
     * @return null|Subject
     */
    public function getAction($subject_id)
    {
        $subject = $this->getRepo(Subject::class)
            ->findOneBy([
                'id' => $subject_id,
            ])
        ;
        
        return $subject;
    }
    
    /**
     * Get the courses related to a subject.
     * 
     * @Route(
     *     path="/subject/{name}"
     * )
     * 
     * @QueryParam(
     *     name="name",
     *     allowBlank=false,
     *     description="The short-name of the subject/department to look up."
     * )
     */
    public function getByNameAction(ParamFetcher $fetcher)
    {
        $subject = $this->getRepo(Subject::class)
            ->findOneBy([
                'name' => $fetcher->get('name'),
            ])
        ;
        
        return $subject;
    }
}
