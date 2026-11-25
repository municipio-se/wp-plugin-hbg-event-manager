<?php

namespace {
    $acfFieldGroups = array();
    $restFields = array();
    $testAcfValues = array();

    function __($text, $domain = null)
    {
        return $text;
    }

    function acf_add_local_field_group($group)
    {
        $GLOBALS['acfFieldGroups'][] = $group;
    }

    function add_action()
    {
    }

    function add_filter()
    {
    }

    function register_rest_field($postType, $fieldName, $args)
    {
        $GLOBALS['restFields'][$postType][$fieldName] = $args;
    }

    function get_field($fieldName, $postId)
    {
        return $GLOBALS['testAcfValues'][$fieldName] ?? null;
    }
}

namespace {
    $root = dirname(__DIR__);

    require_once $root . '/source/php/Api/Fields.php';
    require_once $root . '/source/php/Api/EventFields.php';
    require $root . '/source/php/AcfFields/php/event.php';

    $assertions = 0;

    function assertSameValue($expected, $actual, $message)
    {
        global $assertions;
        $assertions++;

        if ($expected !== $actual) {
            throw new RuntimeException(
                $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
            );
        }
    }

    function findFieldByName($fields, $name)
    {
        foreach ($fields as $field) {
            if (($field['name'] ?? null) === $name) {
                return $field;
            }
        }

        throw new RuntimeException('Could not find ACF field: ' . $name);
    }

    try {
        assertSameValue(1, count($acfFieldGroups), 'The PHP ACF definition should register one field group.');

        $phpRetailers = findFieldByName($acfFieldGroups[0]['fields'], 'additional_ticket_retailers');
        $phpSubFields = array();
        foreach ($phpRetailers['sub_fields'] as $field) {
            $phpSubFields[$field['name']] = $field;
        }

        $json = json_decode(file_get_contents($root . '/source/php/AcfFields/json/event.json'), true, 512, JSON_THROW_ON_ERROR);
        $jsonRetailers = findFieldByName($json[0]['fields'], 'additional_ticket_retailers');
        $jsonSubFields = array();
        foreach ($jsonRetailers['sub_fields'] as $field) {
            $jsonSubFields[$field['name']] = $field;
        }

        $expectedExistingFields = array(
            'retailer_name',
            'booking_url',
            'ticket_release_date',
            'ticket_stop_date',
        );
        foreach ($expectedExistingFields as $fieldName) {
            assertSameValue(true, isset($phpSubFields[$fieldName]), 'PHP definition should preserve ' . $fieldName . '.');
            assertSameValue(true, isset($jsonSubFields[$fieldName]), 'JSON definition should preserve ' . $fieldName . '.');
        }

        $expectedContactFields = array(
            'retailer_phone' => array('key' => 'field_6926da9c0f821', 'type' => 'text'),
            'retailer_email' => array('key' => 'field_6926da9c0f822', 'type' => 'email'),
        );
        foreach ($expectedContactFields as $fieldName => $expected) {
            assertSameValue($expected['key'], $phpSubFields[$fieldName]['key'], 'PHP field key should match for ' . $fieldName . '.');
            assertSameValue($expected['type'], $phpSubFields[$fieldName]['type'], 'PHP field type should match for ' . $fieldName . '.');
            assertSameValue(0, $phpSubFields[$fieldName]['required'], 'PHP field should be optional for ' . $fieldName . '.');
            assertSameValue($expected['key'], $jsonSubFields[$fieldName]['key'], 'JSON field key should match for ' . $fieldName . '.');
            assertSameValue($expected['type'], $jsonSubFields[$fieldName]['type'], 'JSON field type should match for ' . $fieldName . '.');
            assertSameValue(0, $jsonSubFields[$fieldName]['required'], 'JSON field should be optional for ' . $fieldName . '.');
        }

        $eventFields = new \HbgEventImporter\Api\EventFields();
        $eventFields->registerRestFields();
        $retailerRestField = $restFields['event']['additional_ticket_retailers'];
        assertSameValue('objectGetCallBack', $retailerRestField['get_callback'][1], 'REST field should use the ACF-aware object callback.');

        $currentRows = array(
            array(
                'retailer_name' => 'Box office',
                'booking_url' => 'https://example.com/tickets',
                'ticket_release_date' => '2026-12-01 10:00',
                'ticket_stop_date' => '2026-12-12 19:00',
                'retailer_phone' => '+46 42 10 50 00',
                'retailer_email' => 'tickets@example.com',
            ),
        );
        $testAcfValues['additional_ticket_retailers'] = $currentRows;
        assertSameValue(
            $currentRows,
            $eventFields->objectGetCallBack(array('id' => 123), 'additional_ticket_retailers', null),
            'REST serialization should expose all existing and new retailer fields.'
        );

        $legacyRows = array(
            array(
                'retailer_name' => 'Legacy box office',
                'booking_url' => 'https://example.com/legacy',
                'ticket_release_date' => '2025-01-01 10:00',
                'ticket_stop_date' => '2025-01-31 19:00',
            ),
        );
        $testAcfValues['additional_ticket_retailers'] = $legacyRows;
        assertSameValue(
            $legacyRows,
            $eventFields->objectGetCallBack(array('id' => 456), 'additional_ticket_retailers', null),
            'Legacy rows without retailer contact fields should remain valid.'
        );

        echo 'OK (' . $assertions . " assertions)\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
        exit(1);
    }
}
