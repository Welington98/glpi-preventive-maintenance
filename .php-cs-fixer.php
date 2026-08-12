<?php

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/inc', __DIR__ . '/front', __DIR__ . '/hook'])
    ->append([__DIR__ . '/setup.php', __DIR__ . '/hook.php']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS2.0' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(false);
