<?php

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'array_syntax' => ['syntax' => 'short'],
        'single_quote' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'no_extra_blank_lines' => true
    ])
    ->setFinder($finder);
