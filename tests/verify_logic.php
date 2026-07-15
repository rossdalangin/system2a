<?php
/**
 * Verification script for Agency Nexus Gating and Upload Capability
 */

// Mock WordPress functions if needed, but here we'll try to simulate the class logic.
require_once 'includes/class-license-manager.php';
require_once 'includes/class-permissions.php';

function test_gating() {
    echo "Testing Gating Logic...\n";
    $lm = Agency_Nexus_License_Manager::get_instance();

    // Simulate Free Tier
    update_option('an_license_data', ['status' => 'active', 'tier' => 'free']);
    echo "Tier: Free\n";
    echo "money_flow enabled: " . ($lm->is_feature_enabled('money_flow') ? 'YES' : 'NO') . " (Expected: NO)\n";
    echo "project_management enabled: " . ($lm->is_feature_enabled('project_management') ? 'YES' : 'NO') . " (Expected: YES)\n";

    // Simulate Pro Tier
    update_option('an_license_data', ['status' => 'active', 'tier' => 'pro']);
    echo "\nTier: Pro\n";
    echo "money_flow enabled: " . ($lm->is_feature_enabled('money_flow') ? 'YES' : 'NO') . " (Expected: YES)\n";
    echo "white_label enabled: " . ($lm->is_feature_enabled('white_label') ? 'YES' : 'NO') . " (Expected: NO)\n";

    // Simulate Agency Tier
    update_option('an_license_data', ['status' => 'active', 'tier' => 'agency']);
    echo "\nTier: Agency\n";
    echo "white_label enabled: " . ($lm->is_feature_enabled('white_label') ? 'YES' : 'NO') . " (Expected: YES)\n";
}

// Since I can't run full WP environment easily, I'll just check the logic in the files.
test_gating();
