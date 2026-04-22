<?php
require_once 'includes/config.php';

echo "<h2>Algerian Dinar Migration</h2>";

// Update subscriptions table with Algerian Dinar plans
echo "<h3>Updating subscriptions table...</h3>";

try {
    // Delete all existing subscription plans
    $pdo->exec("DELETE FROM subscriptions");
    echo "Deleted all existing subscription plans<br>";
    
    // Insert the three Algerian Dinar plans
    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (name, name_ar, price, features, duration_days) VALUES
        ('monthly', 'DZD', 2000.00, 'Full access to all features', 30),
        ('yearly', 'DZD', 15000.00, 'Full access to all features + 72% savings', 365),
        ('lifetime', 'DZD', 35000.00, 'Lifetime access + best value', 36500)
    ");
    $stmt->execute();
    echo "Inserted Algerian Dinar subscription plans<br>";
    
} catch (Exception $e) {
    echo "Error updating subscriptions table: " . $e->getMessage() . "<br>";
}

// Update wallet balance defaults
echo "<h3>Updating wallet balances...</h3>";

try {
    // Update default wallet balance for existing users
    $pdo->exec("UPDATE users SET wallet_balance = 5000.00 WHERE wallet_balance IS NULL OR wallet_balance = 100.00");
    echo "Updated wallet balances to 5,000 DZD<br>";
    
    // Update wallet_balance column default
    $pdo->exec("ALTER TABLE users ALTER COLUMN wallet_balance SET DEFAULT 5000.00");
    echo "Updated wallet_balance column default to 5,000 DZD<br>";
    
} catch (Exception $e) {
    echo "Error updating wallet balances: " . $e->getMessage() . "<br>";
}

// Update any existing transactions to use DZD
echo "<h3>Updating transaction descriptions...</h3>";

try {
    $pdo->exec("UPDATE transactions SET description = REPLACE(description, 'units', 'DZD')");
    $pdo->exec("UPDATE transactions SET description = REPLACE(description, 'USD', 'DZD')");
    echo "Updated transaction descriptions to use DZD<br>";
    
} catch (Exception $e) {
    echo "Error updating transactions: " . $e->getMessage() . "<br>";
}

echo "<h3>Migration completed successfully!</h3>";
echo "<a href='dashboard.php'>Go to Dashboard</a>";
?>
