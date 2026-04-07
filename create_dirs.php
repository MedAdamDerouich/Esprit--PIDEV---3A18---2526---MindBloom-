<?php
$dirs = [
    'templates/admin/event',
    'templates/event'
];

foreach ($dirs as $dir) {
    @mkdir($dir, 0755, true);
    echo "Created: $dir\n";
}

echo "\nDirectory structure:\n";
system('dir templates /s /b');
?>
