<?php
namespace Harbour\ReleaseBundle\Entity;

use Doctrine\ORM\Mapping AS ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @ORM\Table(
 *     name="harbour_release", 
 *     indexes={
 *         @ORM\Index(name="HarbouReleaseApplicationNameIndex", columns={"application"}),
 *         @ORM\Index(name="HarbourReleaseApplicationVersionIndex", columns={"application","version"}),
 *         @ORM\Index(
 *             name="HarbouReleaseGetApplicationVersionIndex", 
 *             columns={"application","state","os_code","os_bit","os_min_version"}
 *         ),
 *         @ORM\Index(name="HarbouReleaseGetApplicationIndex", columns={"application","state","os_code","os_bit"})
 *     }
 * )
 */
class Release
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=64, nullable=false)
     */
    private $application;

    /**
     * @ORM\Column(type="string", length=32, nullable=false)
     */
    private $version;

    /**
     * @ORM\Column(type="string", length=16, nullable=false)
     */
    private $state;

    /**
     * @ORM\Column(type="string", length=16, nullable=false)
     */
    private $os_code;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private $os_bit;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $os_min_version;

    /** 
     * @ORM\Column(type="string", length=9999, nullable=true)
     */
    private $change_log;

    /**
     * @ORM\Column(type="string", length=64, nullable=false)
     */
    private $filename;

    /**
     * @ORM\Column(type="string", length=32, nullable=false)
     */
    private $filetype;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $created_at;

    /**
     * @ORM\ManyToOne(targetEntity="Coral\CoreBundle\Entity\Account")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", nullable=false)
     */
    private $account;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set version
     *
     * @param string $version
     * @return Release
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get version
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set state
     *
     * @param string $state
     * @return Release
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Get state
     *
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Set os_code
     *
     * @param string $osCode
     * @return Release
     */
    public function setOsCode($osCode)
    {
        $this->os_code = $osCode;

        return $this;
    }

    /**
     * Get os_code
     *
     * @return string
     */
    public function getOsCode()
    {
        return $this->os_code;
    }

    /**
     * Set os_bit
     *
     * @param integer $osBit
     * @return Release
     */
    public function setOsBit($osBit)
    {
        $this->os_bit = $osBit;

        return $this;
    }

    /**
     * Get os_bit
     *
     * @return integer
     */
    public function getOsBit()
    {
        return $this->os_bit;
    }

    /**
     * Set os_min_version
     *
     * @param integer $osMinVersion
     * @return Release
     */
    public function setOsMinVersion($osMinVersion)
    {
        $this->os_min_version = $osMinVersion;

        return $this;
    }

    /**
     * Get os_min_version
     *
     * @return integer
     */
    public function getOsMinVersion()
    {
        return $this->os_min_version;
    }

    /**
     * Set filename
     *
     * @param string $filename
     * @return Release
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Set filetype
     *
     * @param string $filetype
     * @return Release
     */
    public function setFiletype($filetype)
    {
        $this->filetype = $filetype;

        return $this;
    }

    /**
     * Get filetype
     *
     * @return string
     */
    public function getFiletype()
    {
        return $this->filetype;
    }

    /**
     * Set created_at
     *
     * @param \DateTime $createdAt
     * @return Release
     */
    public function setCreatedAt($createdAt)
    {
        $this->created_at = $createdAt;

        return $this;
    }

    /**
     * Get created_at
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Set account
     *
     * @param \Coral\CoreBundle\Entity\Account $account
     * @return Release
     */
    public function setAccount(\Coral\CoreBundle\Entity\Account $account)
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Get account
     *
     * @return \Coral\CoreBundle\Entity\Account
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * Set application
     *
     * @param string $application
     * @return Release
     */
    public function setApplication($application)
    {
        $this->application = $application;

        return $this;
    }

    /**
     * Get application
     *
     * @return string
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * Set change_log
     *
     * @param string $changeLog
     * @return Release
     */
    public function setChangeLog($changeLog)
    {
        $this->change_log = $changeLog;
    
        return $this;
    }

    /**
     * Get change_log
     *
     * @return string 
     */
    public function getChangeLog()
    {
        return $this->change_log;
    }
}