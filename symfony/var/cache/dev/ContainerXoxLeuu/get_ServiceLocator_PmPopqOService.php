<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

// This file has been auto-generated by the Symfony Dependency Injection Component for internal use.
// Returns the private '.service_locator.PmPopqO' shared service.

return $this->privates['.service_locator.PmPopqO'] = new \Symfony\Component\DependencyInjection\Argument\ServiceLocator($this->getService, [
    'App\\Controller\\PostsController::index' => ['privates', '.service_locator.oXHHcjB', 'get_ServiceLocator_OXHHcjBService.php', true],
    'App\\Controller\\frontController::index' => ['privates', '.service_locator.oXHHcjB', 'get_ServiceLocator_OXHHcjBService.php', true],
    'App\\Controller\\PostsController:index' => ['privates', '.service_locator.oXHHcjB', 'get_ServiceLocator_OXHHcjBService.php', true],
    'App\\Controller\\frontController:index' => ['privates', '.service_locator.oXHHcjB', 'get_ServiceLocator_OXHHcjBService.php', true],
], [
    'App\\Controller\\PostsController::index' => '?',
    'App\\Controller\\frontController::index' => '?',
    'App\\Controller\\PostsController:index' => '?',
    'App\\Controller\\frontController:index' => '?',
]);
