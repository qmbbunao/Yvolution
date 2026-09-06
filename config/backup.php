<?php
/**
 * Paths to the MySQL command-line tools used for backup/restore.
 * XAMPP typically bundles these — adjust if `mysqldump`/`mysql` aren't
 * already on your system PATH.
 *
 * Windows (XAMPP default):  C:\\xampp\\mysql\\bin\\mysqldump.exe
 * macOS (XAMPP default):    /Applications/XAMPP/xamppfiles/bin/mysqldump
 * Linux:                    usually just `mysqldump` (already on PATH)
 */
return [
    'mysqldump_path' => 'mysqldump', // e.g. 'C:\\xampp\\mysql\\bin\\mysqldump.exe'
    'mysql_path'      => 'mysql',     // e.g. 'C:\\xampp\\mysql\\bin\\mysql.exe'
    'backup_dir'      => BASE_PATH . '/database/backups',
];
