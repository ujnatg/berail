<?php

namespace ApplauseAuto\TestRailExtension;

use Behat\Behat\Gherkin\Specification\Locator\FilesystemFeatureLocator;
use Behat\Behat\Gherkin\Specification\Locator\FilesystemScenariosListLocator;
use Behat\Gherkin\Keywords\CachedArrayKeywords;
use Behat\Gherkin\Loader\ArrayLoader;
use Behat\Gherkin\Parser;
use Behat\Mink\Mink;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Behat\Behat\EventDispatcher\Event\AfterStepTested;
use Behat\Behat\EventDispatcher\Event\StepTested;
use Behat\Testwork\Tester\Result\ExceptionResult;
use Behat\Testwork\Tester\Result\TestResults;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioLikeTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Testwork\EventDispatcher\Event\ExerciseCompleted;
use Behat\Testwork\EventDispatcher\Event\BeforeSuiteTested;
use Behat\Testwork\ServiceContainer\Exception\ProcessingException;
use Behat\Testwork\Suite\Exception\SuiteConfigurationException;
use Behat\Testwork\Suite\Suite;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Behat\Gherkin\Specification\LazyFeatureIterator;
use Behat\Gherkin\Gherkin;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Loader\GherkinFileLoader;
use Behat\Gherkin\Lexer;
use Behat\Gherkin\Keywords\ArrayKeywords;
use ApplauseAuto\TestRailExtension\TestRailApiWrapper;

class TestRailListener implements EventSubscriberInterface
{
//    public function __construct(Mink $mink, $defaultSession, $javascriptSession, array $availableJavascriptSessions = array())
    public function __construct($testrail_username, $testrail_password, $testrail_url, $testrun_basename, $testrun_description, $project_id, $testsuite_id)
    {
        $this->testcases=array();
        $this->testrail_username=$testrail_username;
        $this->testrail_password=$testrail_password;
        $this->testrail_url=$testrail_url;
        $this->testrun_basename=$testrun_basename;
        $this->testrun_description=$testrun_description;
        $this->project_id=$project_id;
        $this->testsuite_id=$testsuite_id;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            BeforeSuiteTested::BEFORE => array('setUpTestRun', -10),
            ScenarioTested::AFTER => array('getScenarioResult', -10)

        );
    }

    public function setUpTestRun(BeforeSuiteTested $event)
    {
        TestRailApiWrapper::set_testrun_context(
            $this->testrail_username,
            $this->testrail_password,
            $this->testrail_url,
            $this->testrun_basename,
            $this->testrun_description,
            $this->project_id,
            $this->testsuite_id
        );

        $suite_id=TestRailApiWrapper::create_new_testsuite(" FOX PHP");
        $gerkin = new Gherkin();
        $base_path = $event->getSuite()->getSetting("paths")["features"];
        $gerkin->setBasePath($base_path);
        $i18nPath = dirname(dirname(dirname(dirname(dirname(__DIR__))))) . DIRECTORY_SEPARATOR . 'behat' . DIRECTORY_SEPARATOR . 'gherkin' . DIRECTORY_SEPARATOR . 'i18n.php';
        $gerkin->addLoader(new GherkinFileLoader(new Parser(new Lexer(new CachedArrayKeywords($i18nPath)))));
        $list = (new FilesystemFeatureLocator($gerkin, $base_path))->locateSpecifications($event->getSuite(), $base_path);
        $list->rewind();

        while($list->valid())
        {
            $suite_section=TestRailApiWrapper::create_new_section($list->current()->getTitle());
            // read scenarious from feaure
            foreach($list->current()->getScenarios() as $scenario){
                $test_case_id=TestRailApiWrapper::create_new_testcase($scenario->getTitle(), $suite_section);
                $this->testcases[$test_case_id] = $scenario->getTitle();
            }
            $list->next();
        }
        TestRailApiWrapper::create_new_testrun();
    }

    public function getScenarioResult(ScenarioTested $event)
    {
        // get scenario id
        foreach($this->testcases as $key => $value){
            if ($value==$event->getScenario()->getTitle()){
                TestRailApiWrapper::log_testcase_result($key, $this->resolvResult($event->getTestResult()), "description");
            }
        }
        print($event->getScenario()->getTitle());
        $testResult = $event->getTestResult();

        if (!$testResult instanceof ExceptionResult) {
            return;
        }
    }

    private function resolvResult($behatResult){
        if($behatResult->isPassed()) {
            return "passed";
        }else{
            return "failed";}
    }


    private $testcases;
}
