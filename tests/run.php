<?php

namespace {
    $acfFieldGroups = array();
    $restFields = array();
    $restRoutes = array();
    $testAcfValues = array();

    class WP_REST_Server
    {
        const READABLE = 'GET';
    }

    class WP_REST_Posts_Controller
    {
        private $postType;

        public function __construct($postType)
        {
            $this->postType = $postType;
        }

        public function get_public_item_schema()
        {
            return array(
                'type' => 'object',
                'properties' => array_map(function ($field) {
                    return $field['schema'];
                }, $GLOBALS['restFields'][$this->postType]),
            );
        }
    }

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

    function register_rest_route($namespace, $path, $args)
    {
        $GLOBALS['restRoutes'][$namespace . $path] = $args;
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
        $eventFields->registerRestRoute();
        $eventFields->registerRestFields();
        $retailerRestField = $restFields['event']['additional_ticket_retailers'];
        $getRetailers = $retailerRestField['get_callback'];
        $timeRoute = $restRoutes['wp/v2/event/time'];
        assertSameValue(array($eventFields, 'getEventsByTimestamp'), $timeRoute[0]['callback'], 'The time endpoint should retain its callback.');
        $timeSchema = call_user_func($timeRoute['schema']);
        assertSameValue('array', $timeSchema['type'], 'The time route should advertise a collection of events.');
        $retailerSchema = $timeSchema['items']['properties']['additional_ticket_retailers'];
        assertSameValue($retailerRestField['schema'], $retailerSchema, 'The time route should advertise the registered retailer schema.');
        assertSameValue(array('array', 'null'), $retailerSchema['type'], 'The retailer field should support rows or no data.');
        $rowSchema = $retailerSchema['items'];
        assertSameValue(array_merge($expectedExistingFields, array_keys($expectedContactFields)), array_keys($rowSchema['properties']), 'The schema should describe all retailer fields.');
        foreach ($expectedContactFields as $name => $field) {
            assertSameValue('string', $rowSchema['properties'][$name]['type'], 'Contact values should be strings.');
            assertSameValue(false, in_array($name, $rowSchema['required'] ?? array(), true), 'Contact values should be optional.');
        }

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
            $getRetailers(array('id' => 123), 'additional_ticket_retailers', null),
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
            $getRetailers(array('id' => 456), 'additional_ticket_retailers', null),
            'Legacy rows without retailer contact fields should remain valid.'
        );

        $phoneOnly = $legacyRows[0] + array('retailer_phone' => '070 001 02 03');
        $emailOnly = $legacyRows[0] + array('retailer_email' => 'tickets@example.com');
        $testAcfValues['additional_ticket_retailers'] = array(
            $phoneOnly + array('retailer_email' => false),
            $emailOnly + array('retailer_phone' => ''),
            $legacyRows[0] + array('retailer_phone' => null, 'retailer_email' => ''),
        );
        assertSameValue(
            array($phoneOnly, $emailOnly, $legacyRows[0]),
            $getRetailers(array('id' => 789), 'additional_ticket_retailers', null),
            'Blank contacts should be omitted independently and phone formatting must survive.'
        );
        $testAcfValues['additional_ticket_retailers'] = false;
        assertSameValue(null, $getRetailers(array('id' => 789), 'additional_ticket_retailers', null), 'No repeater rows should still return null.');

        echo 'OK (' . $assertions . " assertions)\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
        exit(1);
    }
}
