<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * PropertyHive Demo Data Address
 *
 * The PropertyHive Demo Address class stores functions around generating demo data addresses.
 *
 * @class       PH_Demo_Data_Address
 * @version     1.0.0
 * @package     PropertyHive/Classes
 * @category    Class
 * @author      PropertyHive
 */
class PH_Demo_Data_Address {

    public function generate_demo_address_meta_fields()
    {
        $address = array();

        $address['_address_name_number'] = $this->generate_address_name_number();

        $street_prefix_array = PH_Demo_Data_Banks::$forenames;
        $street_suffix_array = PH_Demo_Data_Banks::$street_suffixes;
        $address['_address_street'] = $street_prefix_array[array_rand($street_prefix_array)] . ' ' . $street_suffix_array[array_rand($street_suffix_array)];

        $address_array = PH_Demo_Data_Banks::$post_towns;
        $town = array_rand($address_array);
        $postcodes_array = $address_array[$town];
        $postcode_one = $postcodes_array[array_rand($postcodes_array)];
        $postcode_two = $this->generate_postcode_suffix();

        $address['_address_two'] = '';
        $address['_address_three'] = $town;
        $address['_address_four'] = '';
        $address['_address_postcode'] = $postcode_one . ' ' . $postcode_two;

        return $address;
    }

    private function generate_address_name_number()
    {
        $name_number = rand(1, 150);

        $rand_num = rand(1, 100);
        if ($rand_num % 10 === 0)
        {
            $prefix_array = array(
                'Flat' => 'Flat',
                'Apartment' => 'Apartment',
                'Unit' => 'Unit',
            );
            $name_number = array_rand($prefix_array) . ' ' . rand(1, 20) . ', ' . $name_number;
        }

        return $name_number;
    }

    private function generate_postcode_suffix()
    {
        $letters_array = array('A', 'B', 'D', 'E', 'F', 'G', 'H', 'J', 'L', 'N', 'P', 'Q', 'R', 'S', 'T', 'U', 'W', 'X', 'Y', 'Z');
        return rand(0, 9) . $letters_array[array_rand($letters_array)] . $letters_array[array_rand($letters_array)];
    }
}