<?php

// This file has been auto-generated by the Symfony Dependency Injection Component for internal use.

if (\class_exists(\ContainerZnCJuNp\srcApp_KernelDevDebugContainer::class, false)) {
    // no-op
} elseif (!include __DIR__.'/ContainerZnCJuNp/srcApp_KernelDevDebugContainer.php') {
    touch(__DIR__.'/ContainerZnCJuNp.legacy');

    return;
}

if (!\class_exists(srcApp_KernelDevDebugContainer::class, false)) {
    \class_alias(\ContainerZnCJuNp\srcApp_KernelDevDebugContainer::class, srcApp_KernelDevDebugContainer::class, false);
}

return new \ContainerZnCJuNp\srcApp_KernelDevDebugContainer([
    'container.build_hash' => 'ZnCJuNp',
    'container.build_id' => 'fc15d05d',
    'container.build_time' => 1574865280,
], __DIR__.\DIRECTORY_SEPARATOR.'ContainerZnCJuNp');
