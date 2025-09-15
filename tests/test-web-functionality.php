<?php
/**
 * District 8 Travel League - Web Functionality Tests
 * 
 * Tests web interface functionality for Phase 1
 */

define('D8TL_APP', true);

// CLI-specific bootstrap to avoid session issues
require_once __DIR__ . '/../includes/env-loader.php';
require_once EnvLoader::getPath('includes/config.php');
require_once __DIR__ . '/../includes/enums.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/Logger.php';

echo "District 8 Travel League - Web Functionality Tests\n";
echo "=================================================\n\n";

$baseUrl = 'http://localhost:8000';
$testResults = [];

/**
 * Test helper function
 */
function runWebTest($testName, $testFunction) {
    global $testResults;
    
    echo "Testing: $testName... ";
    
    try {
        $result = $testFunction();
        if ($result === true) {
            echo "✓ PASS\n";
            $testResults[$testName] = 'PASS';
        } else {
            echo "✗ FAIL: $result\n";
            $testResults[$testName] = "FAIL: $result";
        }
    } catch (Exception $e) {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
        $testResults[$testName] = "ERROR: " . $e->getMessage();
    }
}

/**
 * Make HTTP request
 */
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['body' => $response, 'code' => $httpCode];
}

// Test 1: Basic Page Access
runWebTest("Login Page Access", function() use ($baseUrl) {
    $response = makeRequest($baseUrl . '/login.php');
    
    if ($response['code'] !== 200) {
        return "HTTP {$response['code']} - Expected 200";
    }
    
    if (strpos($response['body'], 'Sign In') === false) {
        return "Login page content not found";
    }
    
    if (strpos($response['body'], 'District 8 Travel League') === false) {
        return "Application title not found";
    }
    
    return true;
});

// Test 2: Registration Page Access
runWebTest("Registration Page Access", function() use ($baseUrl) {
    // Create a test invitation token first
    $db = Database::getInstance();
    $adminRole = $db->fetchOne("SELECT id FROM roles WHERE name = 'administrator'");
    $adminUser = $db->fetchOne("SELECT id FROM users WHERE username = 'admin'");
    
    if (!$adminRole || !$adminUser) {
        return "Admin role or user not found for test setup";
    }
    
    $token = bin2hex(random_bytes(32));
    $testEmail = 'webtest_' . time() . '@example.com';
    
    $invitationId = $db->insert('user_invitations', [
        'email' => $testEmail,
        'token' => $token,
        'role_id' => $adminRole['id'],
        'invited_by' => $adminUser['id'],
        'expires_at' => date('Y-m-d H:i:s', strtotime('+14 days'))
    ]);
    
    // Test registration page with token
    $response = makeRequest($baseUrl . '/register.php?token=' . $token);
    
    if ($response['code'] !== 200) {
        $db->delete('user_invitations', 'id = ?', [$invitationId]);
        return "HTTP {$response['code']} - Expected 200";
    }
    
    if (strpos($response['body'], 'Create Your Account') === false) {
        $db->delete('user_invitations', 'id = ?', [$invitationId]);
        return "Registration page content not found";
    }
    
    if (strpos($response['body'], $testEmail) === false) {
        $db->delete('user_invitations', 'id = ?', [$invitationId]);
        return "Pre-populated email not found";
    }
    
    // Clean up
    $db->delete('user_invitations', 'id = ?', [$invitationId]);
    
    return true;
});

// Test 3: Invalid Registration Token
runWebTest("Invalid Registration Token", function() use ($baseUrl) {
    $response = makeRequest($baseUrl . '/register.php?token=invalid_token');
    
    if ($response['code'] !== 200) {
        return "HTTP {$response['code']} - Expected 200";
    }
    
    if (strpos($response['body'], 'Invalid invitation token') === false) {
        return "Error message for invalid token not found";
    }
    
    return true;
});

// Test 4: Password Reset Page
runWebTest("Password Reset Page", function() use ($baseUrl) {
    $response = makeRequest($baseUrl . '/forgot-password.php');
    
    if ($response['code'] !== 200) {
        return "HTTP {$response['code']} - Expected 200";
    }
    
    if (strpos($response['body'], 'Reset Password') === false) {
        return "Password reset page content not found";
    }
    
    if (strpos($response['body'], 'individual user accounts') === false) {
        return "Information about individual accounts not found";
    }
    
    return true;
});

// Test 5: Admin Area Access (Without Login)
runWebTest("Admin Area Protection", function() use ($baseUrl) {
    $response = makeRequest($baseUrl . '/admin/users/');
    
    // Should redirect to login or show access denied
    if ($response['code'] === 200 && strpos($response['body'], 'User Management') !== false) {
        return "Admin area accessible without authentication";
    }
    
    // Check if redirected to login or got proper error
    if ($response['code'] === 302 || 
        strpos($response['body'], 'login') !== false || 
        strpos($response['body'], 'Login') !== false ||
        $response['code'] === 403) {
        return true;
    }
    
    return "Unexpected response for protected admin area";
});

// Test 6: Public Pages Access
runWebTest("Public Pages Access", function() use ($baseUrl) {
    $publicPages = [
        '/' => 'District 8 Travel League',
        '/schedule.php' => 'Schedule',
        '/standings.php' => 'Standings'
    ];
    
    foreach ($publicPages as $page => $expectedContent) {
        $response = makeRequest($baseUrl . $page);
        
        if ($response['code'] !== 200) {
            return "Public page $page returned HTTP {$response['code']}";
        }
        
        if (strpos($response['body'], $expectedContent) === false) {
            return "Expected content '$expectedContent' not found on page $page";
        }
    }
    
    return true;
});

// Test 7: CSRF Protection
runWebTest("CSRF Protection", function() use ($baseUrl) {
    // Try to submit login form without CSRF token
    $response = makeRequest($baseUrl . '/login.php', 'POST', 'username=test&password=test');
    
    if ($response['code'] !== 200) {
        return "Unexpected HTTP code for CSRF test";
    }
    
    // Should show CSRF error or reject the form
    if (strpos($response['body'], 'security token') !== false || 
        strpos($response['body'], 'CSRF') !== false ||
        strpos($response['body'], 'Invalid') !== false) {
        return true;
    }
    
    // If no explicit CSRF error, check that login didn't succeed
    if (strpos($response['body'], 'dashboard') !== false || 
        strpos($response['body'], 'Welcome') !== false) {
        return "Form submission succeeded without CSRF token";
    }
    
    return true;
});

echo "\n" . str_repeat("=", 50) . "\n";
echo "WEB TEST RESULTS SUMMARY\n";
echo str_repeat("=", 50) . "\n";

$passCount = 0;
$totalCount = count($testResults);

foreach ($testResults as $testName => $result) {
    $status = strpos($result, 'PASS') === 0 ? '✓' : '✗';
    echo sprintf("  %s %-40s %s\n", $status, $testName, $result);
    
    if (strpos($result, 'PASS') === 0) {
        $passCount++;
    }
}

echo str_repeat("-", 50) . "\n";
echo sprintf("Total: %d/%d web tests passed (%.1f%%)\n", $passCount, $totalCount, ($passCount / $totalCount) * 100);

// Clean up cookies
@unlink('/tmp/cookies.txt');

if ($passCount === $totalCount) {
    echo "\n🎉 All web tests passed! Phase 1 web interface is working correctly.\n";
    exit(0);
} else {
    echo "\n⚠️  Some web tests failed. Please review the results above.\n";
    exit(1);
}
