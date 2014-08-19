<?php

# Load Snug
require_once 'Snug.class.php';

# Initialize Snug
$Snug = new Snug();

# Try handling
try {
	die($Snug->handleAsset($_SERVER['SCRIPT_URL']));
} catch (Exception $e) {
	$Snug->error($e->getMessage(), $e->getCode());
}