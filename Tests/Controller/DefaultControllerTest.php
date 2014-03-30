<?php

namespace Harbour\ReleaseBundle\Tests\Controller;

use Coral\CoreBundle\Utility\JsonParser;
use Coral\CoreBundle\Test\JsonTestCase;

class DefaultControllerTest extends JsonTestCase
{
    private static $uri_prefix = "http://www.orm-designer.com/uploads/ormd2/";

    public function testLatest()
    {
        $this->loadFixtures(array(
            'Coral\CoreBundle\Tests\DataFixtures\ORM\MinimalSettingsData'
        ));

        $createdAt = time() - 30*86400;
        $biggerCreatedAt = time() + 30*86400;

        $client = $this->doPostRequest(
            '/v1/release/add',
            '{
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
                        "fileName" : "ormd2-win-32-106.zip",
                        "fileType" : "portable"
                    },
                    {
                        "application": "ormd2",
                        "version": "2.1.2.250",
                        "createdAt": "' . $biggerCreatedAt . '",
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
                        "createdAt": "' . ($biggerCreatedAt + 1) . '",
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
        // $this->assertIsStatusCode($client, 201);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('message'));
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));

        $client = $this->doGetRequest('/v1/release/latest/ormd2/beta/linux/32');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertCount(1, $jsonRequest->getMandatoryParam('releases'));
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("2.1.2.250", $jsonRequest->getMandatoryParam('releases[0].version'));
        $this->assertEquals($biggerCreatedAt, $jsonRequest->getMandatoryParam('releases[0].createdAt'));
        $this->assertEquals(self::$uri_prefix . "2.1.2.250/ormd2-win-32-106.exe", $jsonRequest->getMandatoryParam('releases[0].fileName'));
        $this->assertEquals("markdown_changelog2", $jsonRequest->getMandatoryParam('releases[0].changeLog'));
        $this->assertEquals(120006, $jsonRequest->getMandatoryParam('releases[0].minimalVersion'));
        $this->assertEquals("installer", $jsonRequest->getMandatoryParam('releases[0].fileType'));

        $client = $this->doGetRequest('/v1/release/latest/ormd2/beta/linux/32/110002');
        $this->assertIsJsonResponse($client);
        $this->assertIsStatusCode($client, 200);
        $jsonRequest  = new JsonParser($client->getResponse()->getContent());
        $this->assertCount(1, $jsonRequest->getMandatoryParam('releases'));
        $this->assertEquals('ok', $jsonRequest->getMandatoryParam('status'));
        $this->assertEquals("2.1.2.254", $jsonRequest->getMandatoryParam('releases[0].version'));

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
        $this->assertIsStatusCode($client, 404);
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
