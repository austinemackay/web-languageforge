<?php

use Api\Library\Shared\Website;
use Api\Model\Scriptureforge\Sfchecks\QuestionModel;
use Api\Model\Shared\Command\SessionCommands;
use Api\Model\Shared\Command\ProjectCommands;
use Api\Model\Shared\ProjectModel;
//use PHPUnit\Framework\TestCase;

class SessionTestEnvironment
{
    /** @var ProjectModel */
    public $project;

    /** @var string */
    public $projectId;

    /** @var QuestionModel */
    public $question;

    /** @var string */
    public $questionId;

    /** @var string */
    public $userId;

    /** @var Website */
    public $website;

    public function create()
    {
        $environ = new MongoTestEnvironment();
        $environ->clean();
        $this->website = $environ->website;

        $this->project = $environ->createProject(SF_TESTPROJECT, SF_TESTPROJECTCODE);
        $this->project->appName = 'sfchecks';
        $this->project->write();
        $this->question = new QuestionModel($this->project);
        $this->question->write();

        $this->userId = $environ->createUser('test_user', 'Test User', 'test_user@example.com');
        $this->projectId = $this->project->id->asString();
        $this->questionId = $this->question->id->asString();
    }
}

class SessionCommandsTest extends PHPUnit_Framework_TestCase
{
    public function testSessionData_userIsNotPartOfProject()
    {
        $environ = new SessionTestEnvironment();
        $environ->create();
        $data = SessionCommands::getSessionData($environ->projectId, $environ->userId, $environ->website);

        // Session data should contain a userId and projectId
        $this->assertArrayHasKey('userId', $data);
        $this->assertTrue(is_string($data['userId']));
        $this->assertEquals($environ->userId, $data['userId']);
        $this->assertArrayHasKey('project', $data);
        $this->assertArrayHasKey('id', $data['project']);
        $this->assertTrue(is_string($data['project']['id']));
        $this->assertEquals($environ->projectId, $data['project']['id']);

        // Session data should also contain "site", a string...
        $this->assertArrayHasKey('baseSite', $data);
        $this->assertTrue(is_string($data['baseSite']));
        // ... and "fileSizeMax", an integer
        $this->assertArrayHasKey('fileSizeMax', $data);
        $this->assertTrue(is_integer($data['fileSizeMax']));

        // Session data should contain project settings, an associative array
        $this->assertArrayHasKey('projectSettings', $data);
        $this->assertTrue(is_array($data['projectSettings']));
        // ... which should not be empty
        $this->assertFalse(empty($data['projectSettings']));

        // Session data should contain user site rights, an array of integers
        $this->assertArrayHasKey('userSiteRights', $data);
        $this->assertTrue(is_array($data['userSiteRights']));
        // ... which should not be empty
        $this->assertFalse(empty($data['userSiteRights']));
        $this->assertTrue(is_integer($data['userSiteRights'][0]));

        // Session data should contain user project rights, an array of integers
        $this->assertArrayHasKey('userProjectRights', $data);
        $this->assertTrue(is_array($data['userProjectRights']));
        // ... which should be empty at first when the user is not part of the project
        $this->assertTrue(empty($data['userProjectRights']));
    }

    public function testSessionData_userIsPartOfProject()
    {
        $environ = new SessionTestEnvironment();
        $environ->create();
        ProjectCommands::updateUserRole($environ->projectId, $environ->userId);
        $data = SessionCommands::getSessionData($environ->projectId, $environ->userId, $environ->website);

        // Session data should contain user project rights, an array of integers
        $this->assertArrayHasKey('userProjectRights', $data);
        $this->assertTrue(is_array($data['userProjectRights']));
        // ... which should not be empty once the user has been assigned to the project
        $this->assertFalse(empty($data['userProjectRights']));
        $this->assertTrue(is_integer($data['userProjectRights'][0]));
    }
}