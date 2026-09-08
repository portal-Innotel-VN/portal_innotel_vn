<?php

defined('BASEPATH') or define('BASEPATH', dirname(__DIR__));

require_once dirname(__DIR__) . '/libraries/Quote_currency_resolver.php';

function cashflow_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$resolver = new Quote_currency_resolver();

$base = $resolver->resolve(1, 1, '123.456', null);
cashflow_assert_same(1.0, $base['exchange_rate_to_base'], 'Base currency must use rate 1');
cashflow_assert_same(123.46, $base['base_total'], 'Base currency amount must be rounded to two decimals');

$foreign = $resolver->resolve(2, 1, '10000.00', '26000');
cashflow_assert_same(26000.0, $foreign['exchange_rate_to_base'], 'Foreign currency must use the supplied rate');
cashflow_assert_same(260000000.0, $foreign['base_total'], 'Foreign amount must convert to base currency');
cashflow_assert_same(
    'base_currency_per_source_currency',
    $foreign['rate_unit'],
    'Rate unit must state that one source unit equals N base units'
);

$missing = $resolver->resolve(2, 1, '10000.00', null);
cashflow_assert_same(null, $missing['exchange_rate_to_base'], 'Missing foreign rate must remain null');
cashflow_assert_same(null, $missing['base_total'], 'Missing foreign rate must not create a base amount');

$invalid = $resolver->resolve(2, 1, '10000.00', '0');
cashflow_assert_same(null, $invalid['base_total'], 'Non-positive foreign rate must fail closed');

fwrite(STDOUT, "PASS: Cashflow currency resolver\n");
