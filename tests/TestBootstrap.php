<?php
// ./custom/plugins/SwagCrowdPreOrder/tests/TestBootstrap.php
declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

$loader = (new TestBootstrapper())
    ->addCallingPlugin()
    ->addActivePlugins('SwagCrowdPreOrder')
    ->setForceInstallPlugins(true)
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('Swag\\CrowdPreOrder\\Tests\\', __DIR__);