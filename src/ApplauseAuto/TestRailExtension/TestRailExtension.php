<?php
namespace ApplauseAuto\TestRailExtension;

use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Behat\Testwork\EventDispatcher\ServiceContainer\EventDispatcherExtension;
use ApplauseAuto\TestRailExtension\TestRailAPIException;

class TestRailExtension implements Extension
{
    const BERAIL_ID = 'berail';
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        // TODO: Implement process() method.
    }

    /**
     * Returns the extension config key.
     *
     * @return string
     */
    public function getConfigKey()
    {
        // TODO: Implement getConfigKey() method.
        return self::BERAIL_ID;
    }

    /**
     * Initializes other extensions.
     *
     * This method is called immediately after all extensions are activated but
     * before any extension `configure()` method is called. This allows extensions
     * to hook into the configuration of other extensions providing such an
     * extension point.
     *
     * @param ExtensionManager $extensionManager
     */
    public function initialize(ExtensionManager $extensionManager)
    {
        // TODO: Implement initialize() method.
    }

    /**
     * Setups configuration for the extension.
     *
     * @param ArrayNodeDefinition $builder
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        // TODO: Implement configure() method.
        {
            $builder
                ->children()
                ->scalarNode('testrail_username')->end()
                ->scalarNode('testrail_password')->end()
                ->scalarNode('testrail_url')->end()
                ->scalarNode('testrun_basename')->end()
                ->scalarNode('testrun_description')->end()
                ->scalarNode('testrun_location')->end()
                ->scalarNode('project_id')->end()
                ->scalarNode('testsuite_id')->end()
                ->booleanNode('create_new_suite')->end()
                ->end();

        }
    }

    /**
     * Loads extension services into temporary container.
     *
     * @param ContainerBuilder $container
     * @param array $config
     */
    public function load(ContainerBuilder $container, array $config)
    {
        try{
            $log_testrail_results=getenv('LOG_TESTRAIL_RESULTS');
        }
        catch (Exception $e)
        {
            $log_testrail_results='FALSE';
        }
        if ($log_testrail_results=='TRUE') {
            echo "TestRail logger Enabled\n";
//
//            if ($config['log_results']) {
            echo "Starting berail plugin\n";
            echo $config['testsuite_id'];
            $definition = new Definition('ApplauseAuto\TestRailExtension\TestRailListener',array(
                $config['testrail_username'],
                $config['testrail_password'],
                $config['testrail_url'],
                $config['testrun_basename'],
                $config['testrun_description'],
                $config['testrun_location'],
                $config['project_id'],
                $config['testsuite_id'],
                $config['create_new_suite'],
                $container
            ));
            $definition->addTag(EventDispatcherExtension::SUBSCRIBER_TAG, array('priority' => 0));
            $container->setDefinition('behat.listener.sessions', $definition);
        } else {
            echo "TestRail logger Disabled by env variable\n";
        }
    }
}