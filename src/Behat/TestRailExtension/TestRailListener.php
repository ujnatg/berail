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

class TestRailListener implements EventSubscriberInterface
{
    private $mink;
    private $defaultSession;
    private $javascriptSession;

    /**
     * @var string[] The available javascript sessions
     */
    private $availableJavascriptSessions;

    /**
     * Initializes initializer.
     *
     * @param Mink        $mink
     * @param string      $defaultSession
     * @param string|null $javascriptSession
     * @param string[]    $availableJavascriptSessions
     */
    public function __construct(Mink $mink, $defaultSession, $javascriptSession, array $availableJavascriptSessions = array())
    {
//        $this->mink              = $mink;
//        $this->defaultSession    = $defaultSession;
//        $this->javascriptSession = $javascriptSession;
//        $this->availableJavascriptSessions = $availableJavascriptSessions;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
//            ScenarioTested::BEFORE   => array('prepareDefaultMinkSession', 10),
//            ExampleTested::BEFORE    => array('prepareDefaultMinkSession', 10),
            BeforeSuiteTested::BEFORE => array('showStepResponse2', -10),
            StepTested::AFTER => array('showStepResponse', -10)

        );
    }

    public function showStepResponse2(BeforeSuiteTested $event)
    {
        $gerkin = new Gherkin();
        $base_path = $event->getSuite()->getSetting("paths")["features"];
//        $base_path = "";
        //print $base_path . "\n";
        $gerkin->setBasePath($base_path);
        $i18nPath = dirname(dirname(dirname(dirname(dirname(__DIR__))))) . DIRECTORY_SEPARATOR . 'behat' . DIRECTORY_SEPARATOR . 'gherkin' . DIRECTORY_SEPARATOR . 'i18n.php';
        $gerkin->addLoader(new GherkinFileLoader(new Parser(new Lexer(new CachedArrayKeywords($i18nPath)))));
        $list = (new FilesystemFeatureLocator($gerkin, $base_path))->locateSpecifications($event->getSuite(), $base_path);
//        foreach ($item as $list){
//            print $list->next();
//        }
        $list->rewind();
        while($list->valid())
        {
            print("->>>>>>>>>>>>>" . $list->current()->getTitle());
            // read scenarious from feaure
            foreach($list->current()->getScenarios() as $scenario){
                print($scenario->getTitle());
            }
            $list->next();
        }

    }

    /**
     * Shows last response of failed step with preconfigured command.
     *
     * Configuration is based on `behat.yml`:
     *
     * `show_auto` enable this listener (default to false)
     * `show_cmd` command to run (`open %s` to open default browser on Mac)
     * `show_tmp_dir` folder where to store temp files (default is system temp)
     *
     * @param AfterStepTested $event
     *
     * @throws \RuntimeException if show_cmd is not configured
     */
    public function showStepResponse(AfterStepTested $event)
    {
        print($event->getFeature()->getTitle() . "\n");
        print($event->getFeature()->getScenarios()[0]->getTitle() . "\n");
        print($event->getFeature()->getScenarios()[1]->getTitle() . "\n");
        print($event->getStep()->getText() . "\n");
        print("Passed" ? ($event->getTestResult()->isPassed()) : "Failed");
        print($event->getTestResult()->getResultCode() . "\n");
        $testResult = $event->getTestResult();

        if (!$testResult instanceof ExceptionResult) {
            return;
        }

        if (!$testResult->getException() instanceof MinkException) {
            return;
        }

        if (null === $this->parameters['show_cmd']) {
            throw new \RuntimeException('Set "show_cmd" parameter in behat.yml to be able to open page in browser (ex.: "show_cmd: open %s")');
        }

        $filename = rtrim($this->parameters['show_tmp_dir'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.uniqid().'.html';
        file_put_contents($filename, $this->mink->getSession()->getPage()->getContent());
        system(sprintf($this->parameters['show_cmd'], escapeshellarg($filename)));
    }


//    /**
//     * Configures default Mink session before each scenario.
//     * Configuration is based on provided scenario tags:
//     *
//     * `@javascript` tagged scenarios will get `javascript_session` as default session
//     * `@mink:CUSTOM_NAME tagged scenarios will get `CUSTOM_NAME` as default session
//     * Other scenarios get `default_session` as default session
//     *
//     * `@insulated` tag will cause Mink to stop current sessions before scenario
//     * instead of just soft-resetting them
//     *
//     * @param ScenarioLikeTested $event
//     *
//     * @throws ProcessingException when the @javascript tag is used without a javascript session
//     */
//    public function prepareDefaultMinkSession(ScenarioLikeTested $event)
//    {
//        $scenario = $event->getScenario();
//        $feature  = $event->getFeature();
//        $session  = null;
//
//        foreach (array_merge($feature->getTags(), $scenario->getTags()) as $tag) {
//            if ('javascript' === $tag) {
//                $session = $this->getJavascriptSession($event->getSuite());
//            } elseif (preg_match('/^mink\:(.+)/', $tag, $matches)) {
//                $session = $matches[1];
//            }
//        }
//
//        if (null === $session) {
//            $session = $this->getDefaultSession($event->getSuite());
//        }
//
//        if ($scenario->hasTag('insulated') || $feature->hasTag('insulated')) {
//            $this->mink->stopSessions();
//        } else {
//            $this->mink->resetSessions();
//        }
//
//        $this->mink->setDefaultSessionName($session);
//    }

//    /**
//     * Stops all started Mink sessions.
//     */
//    public function tearDownMinkSessions()
//    {
//        $this->mink->stopSessions();
//    }
//
//    private function getDefaultSession(Suite $suite)
//    {
//        if (!$suite->hasSetting('mink_session')) {
//            return $this->defaultSession;
//        }
//
//        $session = $suite->getSetting('mink_session');
//
//        if (!is_string($session)) {
//            throw new SuiteConfigurationException(
//                sprintf(
//                    '`mink_session` setting of the "%s" suite is expected to be a string, %s given.',
//                    $suite->getName(),
//                    gettype($session)
//                ),
//                $suite->getName()
//            );
//        }
//
//        return $session;
//    }

//    private function getJavascriptSession(Suite $suite)
//    {
//        if (!$suite->hasSetting('mink_javascript_session')) {
//            if (null === $this->javascriptSession) {
//                throw new ProcessingException('The @javascript tag cannot be used without enabling a javascript session');
//            }
//
//            return $this->javascriptSession;
//        }
//
//        $session = $suite->getSetting('mink_javascript_session');
//
//        if (!is_string($session)) {
//            throw new SuiteConfigurationException(
//                sprintf(
//                    '`mink_javascript_session` setting of the "%s" suite is expected to be a string, %s given.',
//                    $suite->getName(),
//                    gettype($session)
//                ),
//                $suite->getName()
//            );
//        }
//
//        if (!in_array($session, $this->availableJavascriptSessions)) {
//            throw new SuiteConfigurationException(
//                sprintf(
//                    '`mink_javascript_session` setting of the "%s" suite is not a javascript session. %s given but expected one of %s.',
//                    $suite->getName(),
//                    $session,
//                    implode(', ', $this->availableJavascriptSessions)
//                ),
//                $suite->getName()
//            );
//        }
//
//        return $session;
//    }
}