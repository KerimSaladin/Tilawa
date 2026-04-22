<?php
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
if (password_verify('password', $hash)) {
    echo "Match: password";
} elseif (password_verify('admin', $hash)) {
    echo "Match: admin";
} elseif (password_verify('123456', $hash)) {
    echo "Match: 123456";
} else {
    echo "No match found";
}
?>
