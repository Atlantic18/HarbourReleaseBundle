<?php

namespace Harbour\ReleaseBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Coral\CoreBundle\Controller\JsonController;
use Coral\CoreBundle\Utility\JsonParser;
use Coral\CoreBundle\Exception\JsonException;
use Coral\CoreBundle\Exception\AuthenticationException;

use Harbour\ReleaseBundle\Entity\Release;

/**
 * @Route("/v1/release")
 */
class DefaultController extends JsonController
{
    private static $uri_prefix = "http://www.orm-designer.com/uploads/ormd2/";

    /**
     * @Route("/add")
     * @Method("POST")
     */
    public function addAction()
    {
        $request       = new JsonParser($this->get("request")->getContent(), true);
        $releaseCount  = count($request->getMandatoryParam('releases'));

        $em = $this->getDoctrine()->getManager();

        $em->getConnection()->beginTransaction();

        try {
            for($i = 0; $i < $releaseCount; $i++)
            {
                $release = new Release;
                $release->setApplication($request->getMandatoryParam("releases[$i].application"));
                $release->setVersion($request->getMandatoryParam("releases[$i].version"));
                $release->setState($request->getMandatoryParam("releases[$i].state"));
                $release->setOsCode($request->getMandatoryParam("releases[$i].osCode"));
                $release->setOsBit($request->getMandatoryParam("releases[$i].osBit"));
                $release->setOsMinVersion($request->getOptionalParam("releases[$i].minimalVersion"));
                $release->setChangeLog($request->getOptionalParam("releases[$i].changeLog"));
                $release->setFilename($request->getMandatoryParam("releases[$i].fileName"));
                $release->setFiletype($request->getMandatoryParam("releases[$i].fileType"));
                $release->setAccount($this->getAccount());

                if($request->hasParam("releases[$i].createdAt"))
                {
                    $release->setCreatedAt(new \DateTime(date('Y-m-d H:i:s', $request->getMandatoryParam("releases[$i].createdAt"))));
                }

                $em->persist($release);

                //delete old application and versions
                $this->getDoctrine()->getManager()
                    ->createQuery(
                        'DELETE
                        FROM  HarbourReleaseBundle:Release r
                        WHERE r.application = :application
                        AND r.version = :version
                        AND r.account = :account_id'
                    )
                    ->setParameter('application', $release->getApplication())
                    ->setParameter('version', $release->getVersion())
                    ->setParameter('account_id', $this->getAccount()->getId())
                    ->execute();
            }

            $em->getConnection()->commit();
        } catch (\Exception $e) {
            $em->getConnection()->rollback();
            $em->close();
            throw $e;
        }

        $em->flush();

        return new JsonResponse(array('status'  => 'ok'), 201);
    }

    /**
     * @Route("/list/{application}")
     * @Method("GET")
     */
    public function listAction($application)
    {
        $releases = $this->getDoctrine()->getManager()->createQuery(
                'SELECT r
                FROM HarbourReleaseBundle:Release r
                WHERE r.account = :account_id
                AND r.application LIKE :application
                ORDER BY r.created_at DESC, r.os_code ASC'
            )
            ->setParameter('account_id', $this->getAccount()->getId())
            ->setParameter('application', $application)
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $this->throwNotFoundExceptionIf(!count($releases), "Application name not found.");

        $items = array();
        foreach ($releases as $release) {
            $items[] = array(
                'application'    => $release['application'],
                'version'        => $release['version'],
                'state'          => $release['state'],
                'osCode'         => $release['os_code'],
                'osBit'          => $release['os_bit'],
                'changeLog'      => $release['change_log'] ? $release['change_log'] : null,
                'minimalVersion' => $release['os_min_version'] ? $release['os_min_version'] : null,
                'createdAt'      => $release['created_at']->getTimestamp(),
                'fileName'       => self::$uri_prefix . $release['version'] . '/' . $release['filename'],
                'fileType'       => $release['filetype']
            );
        }

        return new JsonResponse(array('status'  => 'ok', 'releases' => $items), 200);
    }

    /**
     * @Route("/change-state/{application}/{version}/{state}")
     * @Method("POST")
     */
    public function changeAction($application, $version, $state)
    {
        $this->getDoctrine()->getManager()->createQuery(
                'UPDATE
                    HarbourReleaseBundle:Release r
                SET
                    r.state = :state
                WHERE
                    r.account = :account_id
                AND
                    r.application = :application
                AND
                    r.version = :version
            ')
            ->setParameter('account_id', $this->getAccount()->getId())
            ->setParameter('application', $application)
            ->setParameter('version', $version)
            ->setParameter('state', $state)
            ->execute();

        return $this->createSuccessJsonResponse();
    }

    /**
     * @Route("/latest/{application}/{state}/{osCode}/{osBit}/{osVersion}")
     * @Method("GET")
     */
    public function latestAction($application, $state, $osCode, $osBit, $osVersion = PHP_INT_MAX)
    {
        //Log visit
        $this->get('coral_connect')->doPostRequest('/v1/logger/add', array(
            'service' => 'release',
            'level'   => 'info',
            'message' => 'IP:' . $this->container->get('request')->getClientIp() .
                ' Agent:' . $this->get("request")->headers->get('User-Agent') .
                ' URI:' . "/latest/$application/$state/$osCode/$osBit/$osVersion"
        ));

        try {
            $latestVersion = $this->getDoctrine()->getManager()->createQuery(
                    'SELECT r.version
                    FROM HarbourReleaseBundle:Release r
                    WHERE r.account = :account_id
                    AND r.application = :application
                    AND r.state = :state
                    AND r.os_code = :osCode
                    AND r.os_bit = :osBit
                    AND r.os_min_version <= :osVersion
                    ORDER BY r.created_at DESC'
                )
                ->setParameter('account_id', $this->getAccount()->getId())
                ->setParameter('application', $application)
                ->setParameter('state', $state)
                ->setParameter('osCode', $osCode)
                ->setParameter('osBit', $osBit)
                ->setParameter('osVersion', $osVersion)
                ->setMaxResults(1)
                ->getSingleScalarResult();
        }
        catch(\Doctrine\ORM\NoResultException $e)
        {
            $this->throwNotFoundExceptionIf(true, "No release has been found.");
        }

        $releases = $this->getDoctrine()->getManager()->createQuery(
                'SELECT r
                FROM HarbourReleaseBundle:Release r
                WHERE r.account = :account_id
                AND r.application = :application
                AND r.state = :state
                AND r.os_code = :osCode
                AND r.os_bit = :osBit
                AND r.version = :version
                AND r.os_min_version <= :osVersion
                ORDER BY r.created_at DESC'
            )
            ->setParameter('account_id', $this->getAccount()->getId())
            ->setParameter('application', $application)
            ->setParameter('state', $state)
            ->setParameter('osCode', $osCode)
            ->setParameter('osBit', $osBit)
            ->setParameter('version', $latestVersion)
            ->setParameter('osVersion', $osVersion)
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $items = array();
        foreach ($releases as $release) {
            $items[] = array(
                'version'        => $release['version'],
                'changeLog'      => $release['change_log'] ? $release['change_log'] : null,
                'minimalVersion' => $release['os_min_version'] ? $release['os_min_version'] : null,
                'createdAt'      => $release['created_at']->getTimestamp(),
                'fileName'       => self::$uri_prefix . $release['version'] . '/' . $release['filename'],
                'fileType'       => $release['filetype']
            );
        }

        return new JsonResponse(array('status'  => 'ok', 'releases' => $items), 200);
    }
}
