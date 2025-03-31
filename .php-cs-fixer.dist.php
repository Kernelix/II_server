<?php

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        // Дополнительные правила
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__.'/src')
            ->exclude('vendor')
    );
