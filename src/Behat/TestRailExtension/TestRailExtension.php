<?php
/**
 * Created by PhpStorm.
 * User: yevhen
 * Date: 7/14/15
 * Time: 4:35 PM
 */

namespace Behat\TestRailExtension;

use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Behat\Testwork\EventDispatcher\ServiceContainer\EventDispatcherExtension;

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
        echo "process";
    }

    /**
     * Returns the extension config key.
     *
     * @return string
     */
    public function getConfigKey()
    {
        // TODO: Implement getConfigKey() method.
        echo "getConfigKey";
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
        echo "initialize";

    }

    /**
     * Setups configuration for the extension.
     *
     * @param ArrayNodeDefinition $builder
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        // TODO: Implement configure() method.
        echo "configure";
    }

    /**
     * Loads extension services into temporary container.
     *
     * @param ContainerBuilder $container
     * @param array $config
     */
    public function load(ContainerBuilder $container, array $config)
    {
        // TODO: Implement load() method.
        echo "load\n";

        $definition = new Definition('Behat\TestRailExtension\TestRailListener', array(
            new Reference('mink'),
            '%mink.default_session%',
            '%mink.javascript_session%',
            '%mink.available_javascript_sessions%',
        ));
        $definition->addTag(EventDispatcherExtension::SUBSCRIBER_TAG, array('priority' => 0));
        $container->setDefinition('behat.listener.sessions', $definition);
    }
}