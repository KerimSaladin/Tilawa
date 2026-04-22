<?php
// Copy schema layout
$res = copy('c:/xampp/htdocs/Tilawa/database/schema.sql', 'c:/xampp/htdocs/Tilawa/database/final_schema.sql');
echo "Copy result: " . ($res ? "success" : "failed") . "\n";

// Delete old migration files
$files = [
    'fix_missing_tables.sql',
    'migrations.sql',
    'apply_alter.sql',
    'check_tables.sql'
];
foreach($files as $f) {
    if (file_exists('c:/xampp/htdocs/Tilawa/database/'.$f)) {
        unlink('c:/xampp/htdocs/Tilawa/database/'.$f);
    }
}
echo "Cleanup done.\n";
