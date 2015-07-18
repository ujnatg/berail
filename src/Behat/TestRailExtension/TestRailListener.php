<?php
/**
 * Created by PhpStorm.
 * User: yevhen
 * Date: 7/14/15
 * Time: 10:08 PM
 */
namespace Behat\TestRailExtension;

use Behat\Behat\EventDispatcher\Event\AfterScenarioTested;
use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\Gherkin\Specification\Locator\FilesystemFeatureLocator;
use Behat\Behat\Gherkin\Specification\Locator\FilesystemScenariosListLocator;
use Behat\Behat\Hook\Call\AfterScenario;
use Behat\Behat\Hook\Call\BeforeScenario;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
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
use Behat\TestRailExtension\TestRailApiWrapper;

class TestRailListener implements EventSubscriberInterface
{
//    public function __construct(Mink $mink, $defaultSession, $javascriptSession, array $availableJavascriptSessions = array())
    public function __construct($testrail_username, $testrail_password, $testrail_url, $testrun_basename, $testrun_description, $project_id, $testsuite_id, $create_new_suite)
    {
        $this->testcases=array();
        $this->testrail_username=$testrail_username;
        $this->testrail_password=$testrail_password;
        $this->testrail_url=$testrail_url;
        $this->testrun_basename=$testrun_basename;
        $this->testrun_description=$testrun_description;
        $this->project_id=$project_id;
        $this->testsuite_id=$testsuite_id;
        $this->results_array=[];
        TestRailListener::$create_new_suite=$create_new_suite;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $subscribers=[];
        // configure subscribers
        if (TestRailListener::$create_new_suite) {
            $subscribers[BeforeSuiteTested::BEFORE] = array('setUpTestRun', -10);
            $subscribers[ScenarioTested::AFTER] = array('getScenarioResult', -10);
        }
        if (!TestRailListener::$create_new_suite) {
            $subscribers[BeforeSuiteTested::BEFORE] = array('setUpTestRunBasedOnExistingTestSuite', -10);
            $subscribers[AfterStepTested::AFTER] = array('getStepResult', -10);
        }
        return $subscribers;
    }


    private $results_array;

    public function getStepResult(AfterStepTested $event)
    {
        array_push($this->results_array, $event->getTestResult()->getResultCode());
        if (preg_match("/I report case result \"([0-9]+)\"$/", $event->getStep()->getText(), $output_array))
            {
                $key = $output_array[1];
                print("Scenario result for case id #" . $key . " ->" . $this->get_result_by_array() . "\n");
                TestRailApiWrapper::log_testcase_result($key, $this->get_result_by_array(), "description");

                // Clean results
                $this->results_array=[];
            }
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

    private function get_result_by_array(){
        if (in_array(99, $this->results_array)){
            return "failed";
        }
        if (in_array(20, $this->results_array)||in_array(2, $this->results_array)) {
            return "retest";
        }
        if (in_array(10, $this->results_array)) {
            return "skipped";
        }
        foreach($this->results_array as $step_result)
        {
            if ($step_result!=0) return "failed";
        }
        return "passed";
    }

    public function setUpTestRunBasedOnExistingTestSuite(BeforeSuiteTested $event){
        print("Rails logger initialised to use " . $this->testsuite_id . " suite id\n");
        $this->initRails();
        print("Testrun #" . TestRailApiWrapper::create_new_testrun() . " created\n");
    }

    public function setUpTestRun(BeforeSuiteTested $event)
    {
        print("Rails logger initialised to use new suite id\n");
        $this->initRails();

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

    public function showStepResponse(ScenarioTested $event)
    {
        var_dump($event);
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

    private function initRails(){
        TestRailApiWrapper::set_testrun_context(
            $this->testrail_username,
            $this->testrail_password,
            $this->testrail_url,
            $this->testrun_basename,
            $this->testrun_description,
            $this->project_id,
            $this->testsuite_id
        );
    }

    private function resolvResult($behatResult){
        if($behatResult->isPassed()) {
            return "passed";
        }else{
            return "failed";}
    }


    private static $create_new_suite;

    private $testcases;
}