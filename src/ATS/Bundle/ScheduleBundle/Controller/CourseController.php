<?php

namespace ATS\Bundle\ScheduleBundle\Controller;

use ATS\Bundle\ScheduleBundle\Entity\Course;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

/**
 * Course controller.
 *
 * @ApiDoc()
 * 
 * @RouteResource("course", pluralize=false)
 * @View(serializerEnableMaxDepthChecks=true)
 * 
 * @author Austin Shinpaugh <ashinpaugh@ou.edu>
 */
class CourseController extends AbstractController implements ClassResourceInterface
{
    /**
     * Fetches all of the courses.
     * 
     * @ApiDoc(
     *     resource=true,
     *     description="Fetch all the available courses.",
     *     method="CGET"
     * )
     * 
     * @return Course[]
     */
    public function cgetAction()
    {
        $courses = $this->getRepo(Course::class)
            ->findBy([], ['number' => 'ASC'])
        ;
        
        return ['courses' => $courses];
    }
    
    /**
     * Fetch a specific course.
     * 
     * @ApiDoc(
     *     resource=true,
     *     method="GET"
     * )
     * 
     * @Route(
     *     requirements={
            "subject_id": "\d+",
     *      "course_id":  "\d+"
     *     }
     * )
     * 
     * @return object
     */
    public function getAction($subject_id, $course_id)
    {
        return $this->getRepo(Course::class)
            ->findOneBy([
                'subject' => [
                    'id' => $subject_id,
                ],
                'id' => $course_id,
            ])
        ;
    }
    
    /**
     * @Route(
     *     path="/course/{subject}/{number}"
     * )
     * 
     * @QueryParam(
     *     name="subject",
     *     description="The subject/department short name.",
     *     allowBlank=false
     * )
     * 
     * @QueryParam(
     *     name="number",
     *     description="The course number.",
     *     allowBlank=false,
     *     requirements="\d+"
     * )
     * 
     * @View(serializerEnableMaxDepthChecks=true)
     */
    public function getByNumberAction(ParamFetcher $fetcher)
    {
        return $this->getRepo(Course::class)
            ->findBy([
                'subject' => [
                    'name' => $fetcher->get('subject')
                ],
                'number'  => $fetcher->get('number'),
            ])
        ;
    }
}
