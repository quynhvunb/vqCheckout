<?php
/**
 * Quick test script to verify autoload
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "Testing VQCheckout autoload...\n\n";

// Test 1: Can we load Settings_Page?
if ( class_exists( 'VQCheckout\\Admin\\Settings_Page' ) ) {
	echo "✓ Settings_Page class exists\n";
} else {
	echo "✗ Settings_Page class NOT found\n";
	exit( 1 );
}

// Test 2: Can we load Plugin?
if ( class_exists( 'VQCheckout\\Core\\Plugin' ) ) {
	echo "✓ Plugin class exists\n";
} else {
	echo "✗ Plugin class NOT found\n";
	exit( 1 );
}

// Test 3: Can we load Hooks?
if ( class_exists( 'VQCheckout\\Core\\Hooks' ) ) {
	echo "✓ Hooks class exists\n";
} else {
	echo "✗ Hooks class NOT found\n";
	exit( 1 );
}

echo "\n✓ All autoload tests passed!\n";
