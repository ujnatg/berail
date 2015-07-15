<?php
/**
 * Created by PhpStorm.
 * User: yevhen
 * Date: 7/14/15
 * Time: 10:08 PM
 */
namespace Behat\TestRailExtension;

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
use Behat\TestRailExtension\TestRailApiWrapper;

class TestRailListener implements EventSubscriberInterface
{
//    public function __construct(Mink $mink, $defaultSession, $javascriptSession, array $availableJavascriptSessions = array())
    public function __construct()
    {
        $this->testcases=array();
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
        TestRailApiWrapper::set_testrun_context("*****************", "**********", "**********************************", "base name", "description", 59, 1802, false);
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
        var_dump($behatResult);
        if($behatResult->isPassed()) {
            return "passed";
        }else{
            return "failed";}
    }


    private $testcases;
}
