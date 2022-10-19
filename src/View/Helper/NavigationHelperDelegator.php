<?php

namespace LmcBootstrapMenu\View\Helper;

use Laminas\View\Helper\Navigation as NavigationHelper;
use Laminas\View\Helper\Navigation\Menu;
use Psr\Container\ContainerInterface;
use LmcBootstrapMenu\View\Helper\Navigation\BootstrapSimpleMenu;

class NavigationHelperDelegator
{
    public function __invoke(
        ContainerInterface $container,
        string $name,
        callable $callback,
        array $options = null
        ): NavigationHelper {
            /** @var NavigationHelper $helper */
            $helper = $callback();
            
            // Add new helper
            $helper->getPluginManager()->setInvokableClass(
                BootstrapSimpleMenu::class,
                BootstrapSimpleMenu::class
            );
            $helper->getPluginManager()->setAlias(
                'bootstrapMenu',
                BootstrapSimpleMenu::class
            );
            
            return $helper;
    }
}
