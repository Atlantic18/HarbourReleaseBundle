<?php

namespace Harbour\ReleaseBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Coral\ContentBundle\Controller\ConfigurableJsonController;
use Coral\CoreBundle\Utility\JsonParser;
use Coral\CoreBundle\Exception\JsonException;
use Coral\CoreBundle\Exception\AuthenticationException;

use Harbour\ReleaseBundle\Entity\Release;

/**
 * @Route("/v1/release")
 */
class DefaultController extends ConfigurableJsonController
{
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
                WHERE r.application LIKE :application
                ORDER BY r.created_at DESC, r.os_code ASC'
            )
            ->setParameter('application', $application)
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $this->throwNotFoundExceptionIf(!count($releases), "Application name not found.");

        $configuration = $this->getConfiguration('config-release', true);
        $uriPrefix = $configuration->getOptionalParam('harbour.release.' . $application);

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
                'fileName'       => $uriPrefix . $release['version'] . '/' . $release['filename'],
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

        $configuration = $this->getConfiguration('config-release', true);
        $uriPrefix = $configuration->getOptionalParam('harbour.release.' . $application);

        try {
            $supportedOsVersions = $this->getDoctrine()->getManager()->createQuery(
                    'SELECT r.os_min_version, r.version
                    FROM HarbourReleaseBundle:Release r
                    WHERE r.application = :application
                    AND r.state = :state
                    AND r.os_code = :osCode
                    AND r.os_bit = :osBit
                    AND r.os_min_version <= :osVersion
                    ORDER BY r.os_min_version DESC, r.created_at DESC'
                )
                ->setParameter('application', $application)
                ->setParameter('state', $state)
                ->setParameter('osCode', $osCode)
                ->setParameter('osBit', $osBit)
                ->setParameter('osVersion', $osVersion)
                ->setMaxResults(1)
                ->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

            if(count($supportedOsVersions) != 1)
            {
                $this->throwNotFoundExceptionIf(true, "No release has been found.");
            }
        }
        catch(\Doctrine\ORM\NoResultException $e)
        {
            $this->throwNotFoundExceptionIf(true, "No release has been found.");
        }

        $releases = $this->getDoctrine()->getManager()->createQuery(
                'SELECT r
                FROM HarbourReleaseBundle:Release r
                WHERE r.application = :application
                AND r.state = :state
                AND r.os_code = :osCode
                AND r.version = :version
                AND r.os_bit = :osBit
                AND r.os_min_version = :osVersion
                ORDER BY r.created_at DESC'
            )
            ->setParameter('application', $application)
            ->setParameter('state', $state)
            ->setParameter('version', $supportedOsVersions[0]['version'])
            ->setParameter('osCode', $osCode)
            ->setParameter('osBit', $osBit)
            ->setParameter('osVersion', $supportedOsVersions[0]['os_min_version'])
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $items = array();
        foreach ($releases as $release) {
            $items[] = array(
                'version'        => $release['version'],
                'changeLog'      => $release['change_log'] ? $release['change_log'] : null,
                'minimalVersion' => $release['os_min_version'] ? $release['os_min_version'] : null,
                'createdAt'      => $release['created_at']->getTimestamp(),
                'fileName'       => $uriPrefix . $release['version'] . '/' . $release['filename'],
                'fileType'       => $release['filetype']
            );
        }

        return new JsonResponse(array('status'  => 'ok', 'releases' => $items), 200);
    }

    private function getFilesByVersion($application, $state, $version)
    {
        $configuration = $this->getConfiguration('config-release', true);
        $uriPrefix = $configuration->getOptionalParam('harbour.release.' . $application);

        $releases = $this->getDoctrine()->getManager()->createQuery(
                'SELECT r
                FROM HarbourReleaseBundle:Release r
                WHERE r.application = :application
                AND r.state = :state
                AND r.version = :version
                ORDER BY r.created_at DESC'
            )
            ->setParameter('application', $application)
            ->setParameter('state', $state)
            ->setParameter('version', $version)
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $items = array(
            'version'   => $version,
            'changeLog' => '',
            'files'     => array()
        );
        foreach ($releases as $release) {
            if(!$items['changeLog'] && $release['change_log'])
            {
                $items['changeLog'] = $release['change_log'];
            }
            $items['files'][]   = array(
                'createdAt'      => $release['created_at']->getTimestamp(),
                'osCode'         => $release['os_code'],
                'osBit'          => $release['os_bit'],
                'fileName'       => $uriPrefix . $release['version'] . '/' . $release['filename'],
                'minimalVersion' => $release['os_min_version'] ? $release['os_min_version'] : null,
                'fileType'       => $release['filetype']
            );
        }

        return $items;
    }

    /**
     * @Route("/latest/{application}/{state}")
     * @Method("GET")
     */
    public function latestDownloadAction($application, $state)
    {
        try {
            $latestVersion = $this->getDoctrine()->getManager()->createQuery(
                    'SELECT r.version
                    FROM HarbourReleaseBundle:Release r
                    WHERE r.application = :application
                    AND r.state = :state
                    ORDER BY r.created_at DESC'
                )
                ->setParameter('application', $application)
                ->setParameter('state', $state)
                ->setMaxResults(1)
                ->getSingleScalarResult();
        }
        catch(\Doctrine\ORM\NoResultException $e)
        {
            $this->throwNotFoundExceptionIf(true, "No release has been found.");
        }

        return new JsonResponse(array(
            'status'  => 'ok',
            'release' => $this->getFilesByVersion($application, $state, $latestVersion)
        ), 200);
    }

    /**
     * @Route("/log/{application}/{state}/{limit}")
     * @Method("GET")
     */
    public function logAction($application, $state, $limit = 10)
    {
        $limit = intval($limit);
        $limit = ($limit >= 10 || $limit <= 0) ? 10 : $limit;

        $versions = $this->getDoctrine()->getManager()->createQuery(
                'SELECT r.version
                FROM HarbourReleaseBundle:Release r
                WHERE r.application = :application
                AND r.state = :state
                GROUP BY r.version
                ORDER BY r.created_at DESC'
            )
            ->setParameter('application', $application)
            ->setParameter('state', $state)
            ->setMaxResults($limit)
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $items = array();

        foreach ($versions as $version)
        {
            $items[] = $this->getFilesByVersion($application, $state, $version['version']);
        }

        return new JsonResponse(array('status'  => 'ok', 'releases' => $items), 200);
    }

    /**
     * @Route("/default-link/{application}/{fileType}/{state}/{osCode}/{osBit}/{osVersion}")
     * @Method("GET")
     */
    public function defaultLinkAction($application, $fileType, $state, $osCode, $osBit, $osVersion = PHP_INT_MAX)
    {
        $configuration = $this->getConfiguration('config-release', true);
        $filename      = false;

        try {
            //search for full match
            $filename = $this->getDoctrine()->getManager()->createQuery(
                    'SELECT CONCAT(r.version, \'/\', r.filename)
                    FROM HarbourReleaseBundle:Release r
                    WHERE r.application = :application
                    AND r.state = :state
                    AND r.os_code = :osCode
                    AND r.os_bit = :osBit
                    AND r.os_min_version <= :osVersion
                    AND r.filetype = :fileType
                    ORDER BY r.os_min_version DESC, r.created_at DESC'
                )
                ->setParameter('application', $application)
                ->setParameter('state', $state)
                ->setParameter('osCode', $osCode)
                ->setParameter('osBit', $osBit)
                ->setParameter('osVersion', $osVersion)
                ->setParameter('fileType', $fileType)
                ->setMaxResults(1)
                ->getSingleScalarResult();
        }
        catch(\Doctrine\ORM\NoResultException $e)
        {
            $filename = false;
        }

        try {
            //search for match without filetype
            $filename = $this->getDoctrine()->getManager()->createQuery(
                    'SELECT CONCAT(r.version, \'/\', r.filename)
                    FROM HarbourReleaseBundle:Release r
                    WHERE r.application = :application
                    AND r.state = :state
                    AND r.os_code = :osCode
                    AND r.os_bit = :osBit
                    AND r.os_min_version <= :osVersion
                    ORDER BY r.os_min_version DESC, r.created_at DESC'
                )
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
            $filename = false;
        }

        if(!$filename)
        {
            try {
                //search for match without min os version
                $filename = $this->getDoctrine()->getManager()->createQuery(
                        'SELECT CONCAT(r.version, \'/\', r.filename)
                        FROM HarbourReleaseBundle:Release r
                        WHERE r.application = :application
                        AND r.state = :state
                        AND r.os_code = :osCode
                        AND r.os_bit = :osBit
                        AND r.filetype = :fileType
                        ORDER BY r.created_at DESC, r.os_min_version DESC'
                    )
                    ->setParameter('application', $application)
                    ->setParameter('state', $state)
                    ->setParameter('osCode', $osCode)
                    ->setParameter('osBit', $osBit)
                    ->setParameter('fileType', $fileType)
                    ->setMaxResults(1)
                    ->getSingleScalarResult();
            }
            catch(\Doctrine\ORM\NoResultException $e)
            {
                $filename = false;
            }
        }

        if(!$filename)
        {
            try {
                //search for default release
                $filename = $this->getDoctrine()->getManager()->createQuery(
                        'SELECT CONCAT(r.version, \'/\', r.filename)
                        FROM HarbourReleaseBundle:Release r
                        WHERE r.application = :application
                        AND r.state = :state
                        AND r.os_code = :osCode
                        AND r.os_bit = :osBit
                        AND r.filetype = :fileType
                        ORDER BY r.created_at DESC'
                    )
                    ->setParameter('application', $application)
                    ->setParameter('state', $configuration->getMandatoryParam('harbour.release.default.state'))
                    ->setParameter('osCode', $configuration->getMandatoryParam('harbour.release.default.os_code'))
                    ->setParameter('osBit', $configuration->getMandatoryParam('harbour.release.default.os_bit'))
                    ->setParameter('fileType', $configuration->getMandatoryParam('harbour.release.default.file_type'))
                    ->setMaxResults(1)
                    ->getSingleScalarResult();
            }
            catch(\Doctrine\ORM\NoResultException $e)
            {
                $this->throwNotFoundExceptionIf(true, "No release has been found.");
            }
        }

        return new JsonResponse(
            array(
                'status'  => 'ok',
                'filename' => $configuration->getOptionalParam('harbour.release.' . $application) . $filename
            ), 200);
    }
}
