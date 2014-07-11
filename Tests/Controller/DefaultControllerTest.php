<?php

namespace Harbour\ReleaseBundle\Tests\Controller;

use Coral\CoreBundle\Utility\JsonParser;
use Coral\CoreBundle\Test\JsonTestCase;

class DefaultControllerTest extends JsonTestCase
{
    private static $uri_prefix = "http://www.orm-designer.com/uploads/ormd2/";

    private function getReleasesConfig($createdAt, $biggerCreatedAt)
    {
        return '{
                "releases": [
                    {
                        "application": "ormd2",
                        "version": "2.1.2.254",
                        "createdAt": "' . $createdAt . '",
                        "state" : "beta",
                        "osCode": "linux",
                        "osBit": "32",
                        "changeLog": "markdown_changelog",
                        "minimalVersion" : "100006",
                        "fileName" : "ormd2-win-32-104.zip",
                        "fileType" : "portable"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.253",
                        "createdAt": "' . $biggerCreatedAt . '",
                        "changeLog": "markdown_changelog2",
                        "state" : "beta",
                        "osCode": "linux",
                        "minimalVersion" : "120006",
                        "osBit": "32",
                        "fileName" : "ormd2-win-32-107.exe",
                        "fileType" : "installer"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.251",
                        "createdAt": "' . $biggerCreatedAt . '",
                        "changeLog": "markdown_changelog2",
                        "state" : "beta",
                        "osCode": "linux",
                        "minimalVersion" : "120000",
                        "osBit": "32",
                        "fileName" : "ormd2-win-32-106.exe",
                        "fileType" : "installer"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.250",
                        "createdAt": "' . ($biggerCreatedAt + 2) . '",
                        "state" : "release",
                        "osCode": "linux",
                        "osBit": "32",
                        "fileName" : "ormd2-win-32-106.exe",
                        "fileType" : "installer"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.250",
                        "createdAt": "' . ($biggerCreatedAt) . '",
                        "state" : "release",
                        "osCode": "mac",
                        "osBit": "64",
                        "minimalVersion" : "0",
                        "fileName" : "ormd2-win-32-116.exe",
                        "fileType" : "portable"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.250",
                        "createdAt": "' . ($biggerCreatedAt) . '",
                        "state" : "release",
                        "osCode": "windows",
                        "osBit": "32",
                        "minimalVersion" : "20",
                        "fileName" : "ormd2-win-32-110.exe",
                        "fileType" : "installer"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.253",
                        "createdAt": "' . ($biggerCreatedAt + 1) . '",
                        "state" : "release",
                        "osCode": "windows",
                        "osBit": "32",
                        "minimalVersion" : "0",
                        "fileName" : "ormd2-win-32-112.exe",
                        "fileType" : "installer"
                    }
                ]
            }';
    }

    public function testLog()
    {
        $this->loadFixtures(array(
            'Coral\CoreBundle\Tests\DataFixtures\ORM\MinimalSettingsData'
        ));

        $createdAt = time() - 30*86400;
        $biggerCreatedAt = time() + 30*86400;

        $client = $this->doPostRequest('/v1/release/add', $this->getReleasesConfig($createdAt, $biggerCreatedAt));
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 201);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));

        $client = $this->doGetRequest('/v1/release/log/ormd2/beta/2');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertCount(2, $jsonRequest->getMandatoryParam('releases'));
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));

        $this->assertCount(1, $jsonRequest->getMandatoryParam('releases[0].files'));
        $this->assertEquals("2.1.2.253", $jsonRequest->getMandatoryParam('releases[0].version'));
        $this->assertEquals("markdown_changelog2", $jsonRequest->getMandatoryParam('releases[0].changeLog'));
        $this->assertEquals($biggerCreatedAt, $jsonRequest->getMandatoryParam('releases[0].files[0].createdAt'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.253/ormd2-win-32-107.exe", $jsonRequest->getMandatoryParam('releases[0].files[0].fileName'));
        $this->assertEquals("installer", $jsonRequest->getMandatoryParam('releases[0].files[0].fileType'));
        $this->assertEquals("32", $jsonRequest->getMandatoryParam('releases[0].files[0].osBit'));
        $this->assertEquals("linux", $jsonRequest->getMandatoryParam('releases[0].files[0].osCode'));
        $this->assertEquals("120006", $jsonRequest->getMandatoryParam('releases[0].files[0].minimalVersion'));

        $this->assertCount(1, $jsonRequest->getMandatoryParam('releases[1].files'));
        $this->assertEquals("2.1.2.251", $jsonRequest->getMandatoryParam('releases[1].version'));
        $this->assertEquals("markdown_changelog2", $jsonRequest->getMandatoryParam('releases[1].changeLog'));
        $this->assertEquals($biggerCreatedAt, $jsonRequest->getMandatoryParam('releases[1].files[0].createdAt'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.251/ormd2-win-32-106.exe", $jsonRequest->getMandatoryParam('releases[1].files[0].fileName'));
        $this->assertEquals("installer", $jsonRequest->getMandatoryParam('releases[1].files[0].fileType'));
        $this->assertEquals("32", $jsonRequest->getMandatoryParam('releases[1].files[0].osBit'));
        $this->assertEquals("linux", $jsonRequest->getMandatoryParam('releases[1].files[0].osCode'));
        $this->assertEquals("120000", $jsonRequest->getMandatoryParam('releases[1].files[0].minimalVersion'));

        $client = $this->doGetRequest('/v1/release/latest/ormd2/invalid');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 404);

        $client = $this->doGetRequest('/v1/release/latest/invalid/beta');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 404);
    }

    public function testLatestRelease()
    {
        $this->loadFixtures(array(
            'Coral\CoreBundle\Tests\DataFixtures\ORM\MinimalSettingsData'
        ));

        $createdAt = time() - 30*86400;
        $biggerCreatedAt = time() + 30*86400;

        $client = $this->doPostRequest('/v1/release/add', $this->getReleasesConfig($createdAt, $biggerCreatedAt));
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 201);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));

        $client = $this->doGetRequest('/v1/release/latest/ormd2/release');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertCount(3, $jsonRequest->getMandatoryParam('release.files'));
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("2.1.2.250", $jsonRequest->getMandatoryParam('release.version'));
        $this->assertEquals("", $jsonRequest->getMandatoryParam('release.changeLog'));
        $this->assertEquals($biggerCreatedAt + 2, $jsonRequest->getMandatoryParam('release.files[0].createdAt'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.250/ormd2-win-32-106.exe", $jsonRequest->getMandatoryParam('release.files[0].fileName'));
        $this->assertEquals("installer", $jsonRequest->getMandatoryParam('release.files[0].fileType'));
        $this->assertEquals("32", $jsonRequest->getMandatoryParam('release.files[0].osBit'));
        $this->assertEquals("linux", $jsonRequest->getMandatoryParam('release.files[0].osCode'));

        $this->assertEquals($biggerCreatedAt, $jsonRequest->getMandatoryParam('release.files[1].createdAt'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.250/ormd2-win-32-116.exe", $jsonRequest->getMandatoryParam('release.files[1].fileName'));
        $this->assertEquals("portable", $jsonRequest->getMandatoryParam('release.files[1].fileType'));
        $this->assertEquals("64", $jsonRequest->getMandatoryParam('release.files[1].osBit'));
        $this->assertEquals("mac", $jsonRequest->getMandatoryParam('release.files[1].osCode'));
        $this->assertTrue(false === $jsonRequest->getOptionalParam('release.files[1].minimalVersion'));

        $this->assertEquals($biggerCreatedAt, $jsonRequest->getMandatoryParam('release.files[2].createdAt'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.250/ormd2-win-32-110.exe", $jsonRequest->getMandatoryParam('release.files[2].fileName'));
        $this->assertEquals("installer", $jsonRequest->getMandatoryParam('release.files[2].fileType'));
        $this->assertEquals("32", $jsonRequest->getMandatoryParam('release.files[2].osBit'));
        $this->assertEquals("windows", $jsonRequest->getMandatoryParam('release.files[2].osCode'));
        $this->assertEquals("20", $jsonRequest->getMandatoryParam('release.files[2].minimalVersion'));

        $client = $this->doGetRequest('/v1/release/latest/ormd2/invalid');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 404);

        $client = $this->doGetRequest('/v1/release/latest/invalid/beta');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 404);

        $client = $this->doGetRequest('/v1/release/latest/ormd2/beta');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertCount(1, $jsonRequest->getMandatoryParam('release.files'));
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("2.1.2.253", $jsonRequest->getMandatoryParam('release.version'));
        $this->assertEquals("markdown_changelog2", $jsonRequest->getMandatoryParam('release.changeLog'));
        $this->assertEquals($biggerCreatedAt, $jsonRequest->getMandatoryParam('release.files[0].createdAt'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.253/ormd2-win-32-107.exe", $jsonRequest->getMandatoryParam('release.files[0].fileName'));
        $this->assertEquals("installer", $jsonRequest->getMandatoryParam('release.files[0].fileType'));
        $this->assertEquals("120006", $jsonRequest->getMandatoryParam('release.files[0].minimalVersion'));
        $this->assertEquals("32", $jsonRequest->getMandatoryParam('release.files[0].osBit'));
        $this->assertEquals("linux", $jsonRequest->getMandatoryParam('release.files[0].osCode'));
    }

    public function testDefaultLink()
    {
        $this->loadFixtures(array(
            'Coral\CoreBundle\Tests\DataFixtures\ORM\MinimalSettingsData'
        ));

        $createdAt = time() - 30*86400;
        $biggerCreatedAt = time() + 30*86400;

        $client = $this->doPostRequest('/v1/release/add', $this->getReleasesConfig($createdAt, $biggerCreatedAt));
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 201);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));

        $client = $this->doGetRequest('/v1/release/default-link/ormd2/installer/beta/linux/32/120000');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("http://www.orm-designer.com/uploads/ormd2/ormd2-win-32-106.exe", $jsonRequest->getMandatoryParam('filename'));

        $client = $this->doGetRequest('/v1/release/default-link/ormd2/unknown/beta/linux/32/120000');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("http://www.orm-designer.com/uploads/ormd2/ormd2-win-32-106.exe", $jsonRequest->getMandatoryParam('filename'));

        $client = $this->doGetRequest('/v1/release/default-link/ormd2/installer/beta/linux/32/140000');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("http://www.orm-designer.com/uploads/ormd2/ormd2-win-32-107.exe", $jsonRequest->getMandatoryParam('filename'));

        $client = $this->doGetRequest('/v1/release/default-link/ormd2/portable/beta/brabus/128');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("http://www.orm-designer.com/uploads/ormd2/ormd2-win-32-112.exe", $jsonRequest->getMandatoryParam('filename'));

        $client = $this->doGetRequest('/v1/release/default-link/nothing/beta/linux/32/120000');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 404);
    }

    public function testLatest()
    {
        $this->loadFixtures(array(
            'Coral\CoreBundle\Tests\DataFixtures\ORM\MinimalSettingsData'
        ));

        $createdAt = time() - 30*86400;
        $biggerCreatedAt = time() + 30*86400;

        $client = $this->doPostRequest('/v1/release/add', $this->getReleasesConfig($createdAt, $biggerCreatedAt));
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 201);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));

        $client = $this->doGetRequest('/v1/release/latest/ormd2/beta/linux/32');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertCount(1, $jsonRequest->getMandatoryParam('releases'));
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("2.1.2.253", $jsonRequest->getMandatoryParam('releases[0].version'));
        $this->assertEquals($biggerCreatedAt, $jsonRequest->getMandatoryParam('releases[0].createdAt'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.253/ormd2-win-32-107.exe", $jsonRequest->getMandatoryParam('releases[0].fileName'));
        $this->assertEquals("markdown_changelog2", $jsonRequest->getMandatoryParam('releases[0].changeLog'));
        $this->assertEquals(120006, $jsonRequest->getMandatoryParam('releases[0].minimalVersion'));
        $this->assertEquals("installer", $jsonRequest->getMandatoryParam('releases[0].fileType'));

        $client = $this->doGetRequest('/v1/release/latest/ormd2/release/mac/64');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertCount(1, $jsonRequest->getMandatoryParam('releases'));
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("2.1.2.250", $jsonRequest->getMandatoryParam('releases[0].version'));
        $this->assertEquals($biggerCreatedAt, $jsonRequest->getMandatoryParam('releases[0].createdAt'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.250/ormd2-win-32-116.exe", $jsonRequest->getMandatoryParam('releases[0].fileName'));
        $this->assertEquals("portable", $jsonRequest->getMandatoryParam('releases[0].fileType'));

        $client = $this->doGetRequest('/v1/release/latest/ormd2/beta/linux/32/110002');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertCount(1, $jsonRequest->getMandatoryParam('releases'));
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("2.1.2.254", $jsonRequest->getMandatoryParam('releases[0].version'));

        $client = $this->doGetRequest('/v1/release/latest/ormd2/beta/linux/32/130000');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertCount(1, $jsonRequest->getMandatoryParam('releases'));
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("2.1.2.253", $jsonRequest->getMandatoryParam('releases[0].version'));

        $client = $this->doGetRequest('/v1/release/latest/unknown/beta/linux/32/110002');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 404);
    }

    public function testAddOverwrite()
    {
        $this->loadFixtures(array(
            'Coral\CoreBundle\Tests\DataFixtures\ORM\MinimalSettingsData'
        ));

        $time = time();

        $client = $this->doPostRequest(
            '/v1/release/add',
            '{
                "releases": [
                    {
                        "application": "ormd2",
                        "version": "2.1.2.254",
                        "createdAt": ' . ($time + 10) . ',
                        "state" : "beta",
                        "osCode": "linux",
                        "osBit": "32",
                        "changeLog": "markdown_changelog",
                        "minimalVersion" : "100006",
                        "fileName" : "ormd2-win-32-106.zip",
                        "fileType" : "portable"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.250",
                        "createdAt": ' . ($time + 5) . ',
                        "changeLog": "markdown_changelog2",
                        "state" : "beta",
                        "osCode": "linux",
                        "minimalVersion" : "120006",
                        "osBit": "32",
                        "fileName" : "ormd2-win-32-106.exe",
                        "fileType" : "installer"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.250",
                        "createdAt": ' . $time . ',
                        "state" : "release",
                        "osCode": "linux",
                        "osBit": "32",
                        "fileName" : "ormd2-win-32-106.exe",
                        "fileType" : "installer"
                    }
                ]
            }'
        );
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 201);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));

        $client = $this->doPostRequest(
            '/v1/release/add',
            '{
                "releases": [
                    {
                        "application": "ormd2",
                        "version": "2.1.2.250",
                        "changeLog": "markdown_changelog2",
                        "createdAt": "' . ($time + 5) . '",
                        "state" : "beta",
                        "osCode": "linux",
                        "minimalVersion" : "120006",
                        "osBit": "32",
                        "fileName" : "ormd2.exe",
                        "fileType" : "installer"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.250",
                        "createdAt": "' . $time . '",
                        "state" : "release",
                        "osCode": "linux",
                        "osBit": "32",
                        "fileName" : "ormd2.zip",
                        "fileType" : "portable"
                    }
                ]
            }'
        );
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 201);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));

        $client = $this->doGetRequest('/v1/release/list/ormd2');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertCount(3, $jsonRequest->getMandatoryParam('releases'));
        $this->assertEquals("2.1.2.254", $jsonRequest->getMandatoryParam('releases[0].version'));
        $this->assertEquals("linux", $jsonRequest->getMandatoryParam('releases[0].osCode'));
        $this->assertEquals("2.1.2.250", $jsonRequest->getMandatoryParam('releases[1].version'));
        $this->assertEquals("beta", $jsonRequest->getMandatoryParam('releases[1].state'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.250/ormd2.exe", $jsonRequest->getMandatoryParam('releases[1].fileName'));
        $this->assertEquals("installer", $jsonRequest->getMandatoryParam('releases[1].fileType'));
        $this->assertEquals("2.1.2.250", $jsonRequest->getMandatoryParam('releases[2].version'));
        $this->assertEquals("release", $jsonRequest->getMandatoryParam('releases[2].state'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.250/ormd2.zip", $jsonRequest->getMandatoryParam('releases[2].fileName'));
        $this->assertEquals("portable", $jsonRequest->getMandatoryParam('releases[2].fileType'));
    }

    public function testAddAndList()
    {
        $this->loadFixtures(array(
            'Coral\CoreBundle\Tests\DataFixtures\ORM\MinimalSettingsData'
        ));

        $createdAt = time() - 30*86400;

        $client = $this->doPostRequest(
            '/v1/release/add',
            '{
                "releases": [
                    {
                        "application": "ormd2",
                        "version": "2.1.2.250",
                        "createdAt": "' . $createdAt . '",
                        "state" : "beta",
                        "osCode": "linux",
                        "osBit": "32",
                        "changeLog": "markdown_changelog",
                        "minimalVersion" : "100006",
                        "fileName" : "ormd2-win-32-106.zip",
                        "fileType" : "portable"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.252",
                        "state" : "release",
                        "osCode": "windows",
                        "osBit": "64",
                        "fileName" : "ormd2-win-32-106.exe",
                        "fileType" : "installer"
                    },
                    {
                        "application": "pulpo",
                        "version": "2.1.2.252",
                        "state" : "release",
                        "osCode": "windows",
                        "osBit": "64",
                        "fileName" : "ormd2-win-32-106.exe",
                        "fileType" : "installer"
                    }
                ]
            }'
        );
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 201);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));

        $client = $this->doGetRequest('/v1/release/list/ormd2');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertCount(2, $jsonRequest->getMandatoryParam('releases'));
        $this->assertEquals("2.1.2.252", $jsonRequest->getMandatoryParam('releases[0].version'));
        $this->assertGreaterThan(time() - 100, $jsonRequest->getMandatoryParam('releases[0].createdAt'));
        $this->assertEquals("release", $jsonRequest->getMandatoryParam('releases[0].state'));
        $this->assertEquals("windows", $jsonRequest->getMandatoryParam('releases[0].osCode'));
        $this->assertEquals("64", $jsonRequest->getMandatoryParam('releases[0].osBit'));
        $this->assertFalse($jsonRequest->hasParam('releases[0].minimalVersion'));
        $this->assertFalse($jsonRequest->hasParam('releases[0].changeLog'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.252/ormd2-win-32-106.exe", $jsonRequest->getMandatoryParam('releases[0].fileName'));
        $this->assertEquals("installer", $jsonRequest->getMandatoryParam('releases[0].fileType'));
        $this->assertEquals("2.1.2.250", $jsonRequest->getMandatoryParam('releases[1].version'));
        $this->assertEquals($createdAt, $jsonRequest->getMandatoryParam('releases[1].createdAt'));
        $this->assertEquals("beta", $jsonRequest->getMandatoryParam('releases[1].state'));
        $this->assertEquals("linux", $jsonRequest->getMandatoryParam('releases[1].osCode'));
        $this->assertEquals("32", $jsonRequest->getMandatoryParam('releases[1].osBit'));
        $this->assertEquals("100006", $jsonRequest->getMandatoryParam('releases[1].minimalVersion'));
        $this->assertEquals("markdown_changelog", $jsonRequest->getMandatoryParam('releases[1].changeLog'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.250/ormd2-win-32-106.zip", $jsonRequest->getMandatoryParam('releases[1].fileName'));
        $this->assertEquals("portable", $jsonRequest->getMandatoryParam('releases[1].fileType'));

        $client = $this->doGetRequest('/v1/release/list/pulpo');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertCount(1, $jsonRequest->getMandatoryParam('releases'));
        $this->assertEquals("pulpo", $jsonRequest->getMandatoryParam('releases[0].application'));
        $this->assertEquals("http://www.orm-designer.com/uploads/pulpo/2.1.2.252/ormd2-win-32-106.exe", $jsonRequest->getMandatoryParam('releases[0].fileName'));

        $client = $this->doGetRequest('/v1/release/list/unknown');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 404);
    }

    public function testAddAlternative()
    {
        $this->loadFixtures(array(
            'Coral\CoreBundle\Tests\DataFixtures\ORM\MinimalSettingsData'
        ));

        $createdAt = time() - 30*86400;

        $client = $this->doPostRequest(
            '/v1/release/add',
            '{
                "releases": [
                    {
                        "application": "ormd2",
                        "version": "2.1.2.250",
                        "createdAt": "' . $createdAt . '",
                        "state" : "beta",
                        "osCode": "linux",
                        "osBit": "32",
                        "minimalVersion" : "100006",
                        "fileName" : "ormd2-win-32-106.zip",
                        "fileType" : "portable"
                    }
                ]
            }'
        );
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 201);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));

        $client = $this->doAlternativeAccountGetRequest('/v1/release/list/ormd2');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
    }

    public function testChange()
    {
        $this->loadFixtures(array(
            'Coral\CoreBundle\Tests\DataFixtures\ORM\MinimalSettingsData'
        ));

        $createdAt = time() - 30*86400;

        $client = $this->doPostRequest(
            '/v1/release/add',
            '{
                "releases": [
                    {
                        "application": "ormd2",
                        "version": "2.1.2.250",
                        "createdAt": "' . $createdAt . '",
                        "state" : "beta",
                        "osCode": "linux",
                        "osBit": "32",
                        "changeLog": "markdown_changelog",
                        "minimalVersion" : "100006",
                        "fileName" : "ormd2-win-32-106.zip",
                        "fileType" : "portable"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.252",
                        "state" : "release",
                        "osCode": "windows",
                        "osBit": "64",
                        "fileName" : "ormd2-win-32-106.exe",
                        "fileType" : "installer"
                    }
                ]
            }'
        );
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 201);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));

        $client = $this->doPostRequest('/v1/release/change-state/ormd2/2.1.2.252/disabled');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);

        $client = $this->doGetRequest('/v1/release/list/ormd2');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertCount(2, $jsonRequest->getMandatoryParam('releases'));
        $this->assertEquals("disabled", $jsonRequest->getMandatoryParam('releases[0].state'));
        $this->assertEquals("beta", $jsonRequest->getMandatoryParam('releases[1].state'));
    }
}
