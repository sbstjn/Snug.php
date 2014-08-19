<?php

/**
 * Add new key with value to array
 *
 * @param $array array Array
 * @param $key string New key name
 * @param $value string New key value
 * @return array
 */
function array_add_key($array, $key, $value) {
	$array[$key] = $value;
	
	return $array;
}