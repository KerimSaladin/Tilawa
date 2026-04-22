<?php
require_once 'includes/config.php';

echo "<h1>🕌 Tilawa Platform - Complete Migration</h1>";
echo "<p>This will set up the entire Tilawa platform with all features and payment system.</p>";

try {
    // Read and execute the complete SQL migration
    $sql_file = __DIR__ . '/complete_migration.sql';
    $sql_content = file_get_contents($sql_file);
    
    if ($sql_content === false) {
        throw new Exception("Could not read migration SQL file");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));
    
    echo "<div style='background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;'>";
    echo "<h3>🔄 Executing Migration Statements...</h3>";
    
    foreach ($statements as $index => $statement) {
        if (empty($statement) || 
            strpos(trim($statement), '--') === 0 || 
            strpos(trim($statement), '/*') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "<p style='color: #28a745; margin: 0.25rem 0;'>✅ Statement " . ($index + 1) . " executed successfully</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "<p style='color: #ffc107; margin: 0.25rem 0;'>⚠️ Statement " . ($index + 1) . " skipped (already exists): " . htmlspecialchars($e->getMessage()) . "</p>";
            } else {
                echo "<p style='color: #dc3545; margin: 0.25rem 0;'>❌ Statement " . ($index + 1) . " failed: " . htmlspecialchars($e->getMessage()) . "</p>";
                throw $e; // Stop on real errors
            }
        }
    }
    
    echo "</div>";
    
    // Verify key tables were created
    echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;'>";
    echo "<h3>🔍 Verifying Migration...</h3>";
    
    $tables_to_check = [
        'users' => 'Users table with all features',
        'resources' => 'Free learning resources',
        'ratings' => 'Teacher rating system', 
        'group_sessions' => 'Group sessions',
        'group_session_participants' => 'Group session participants',
        'subscriptions_platform' => 'Platform subscriptions',
        'transactions' => 'Payment transactions',
        'enrollments' => 'Student-teacher enrollments'
    ];
    
    foreach ($tables_to_check as $table => $description) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $stmt->fetch()['count'];
            echo "<p style='color: #007bff;'>✅ $description: $count records</p>";
        } catch (PDOException $e) {
            echo "<p style='color: #dc3545;'>❌ $description: Table missing - " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "</div>";
    
    // Check if users have wallet balance
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;'>";
    echo "<h3>💰 Checking Wallet Balances...</h3>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count, AVG(wallet_balance) as avg_balance FROM users WHERE wallet_balance IS NOT NULL");
    $wallet_info = $stmt->fetch();
    
    if ($wallet_info['count'] > 0) {
        echo "<p style='color: #155724;'>✅ {$wallet_info['count']} users with wallet balances</p>";
        echo "<p style='color: #155724;'>✅ Average balance: " . number_format($wallet_info['avg_balance'], 2) . " units</p>";
    } else {
        echo "<p style='color: #dc3545;'>⚠️ No users with wallet balances found</p>";
    }
    
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 2rem; border-radius: 8px; text-align: center; border: 2px solid #ffc107;'>";
    echo "<h2 style='color: #856404; margin-bottom: 1rem;'>🎉 Migration Completed Successfully!</h2>";
    echo "<p style='font-size: 1.1rem; margin-bottom: 1.5rem;'>Your Tilawa platform is now fully set up with:</p>";
    
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; text-align: right;'>";
    echo "<div style='background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
    echo "<h4 style='color: #007bff; margin-bottom: 0.5rem;'>✨ Features 1-7</h4>";
    echo "<ul style='list-style: none; padding: 0;'>";
    echo "<li style='padding: 0.25rem 0;'>👨‍🏫 Teacher Online Status</li>";
    echo "<li style='padding: 0.25rem 0;'>📚 Free Learning Resources</li>";
    echo "<li style='padding: 0.25rem 0;'>⭐ Student Rating System</li>";
    echo "<li style='padding: 0.25rem 0;'>👥 Live Group Sessions</li>";
    echo "<li style='padding: 0.25rem 0;'>🔓 Any Student Can Join</li>";
    echo "<li style='padding: 0.25rem 0;'>📅 Extended Trial (30 days)</li>";
    echo "<li style='padding: 0.25rem 0;'>🏆 Admin Star Awards</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
    echo "<h4 style='color: #28a745; margin-bottom: 0.5rem;'>💳 Payment System</h4>";
    echo "<ul style='list-style: none; padding: 0;'>";
    echo "<li style='padding: 0.25rem 0;'>👛 Teacher Pricing Setup</li>";
    echo "<li style='padding: 0.25rem 0;'>💰 Wallet System (100 units)</li>";
    echo "<li style='padding: 0.25rem 0;'>🎟 Platform Subscription (15% discount)</li>";
    echo "<li style='padding: 0.25rem 0;'>📊 Transaction History</li>";
    echo "<li style='padding: 0.25rem 0;'>🏦 Admin Finance Dashboard</li>";
    echo "<li style='padding: 0.25rem 0;'>⚡ 15% Platform Fee</li>";
    echo "</ul>";
    echo "</div>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin-top: 2rem;'>";
    echo "<a href='dashboard.php' style='background: #007bff; color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 1.1rem;'>🚀 Go to Dashboard</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 2rem; border-radius: 8px; text-align: center;'>";
    echo "<h2>❌ Migration Failed</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
    echo "</div>";
}
?>
