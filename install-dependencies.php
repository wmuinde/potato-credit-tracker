<?php
echo "Installing dependencies...\n";

// Check if Composer is installed
$composerCommand = 'composer';
exec("$composerCommand --version", $output, $returnVar);

if ($returnVar !== 0) {
    echo "Error: Composer is not installed or not in your PATH.\n";
    echo "Please install Composer from https://getcomposer.org/download/\n";
    exit(1);
}

// Run composer install
echo "Running composer install...\n";
exec("$composerCommand install", $output, $returnVar);

if ($returnVar !== 0) {
    echo "Error: Failed to install dependencies.\n";
    echo "Please check the error messages above.\n";
    exit(1);
}

echo "Dependencies installed successfully!\n";
echo "You can now use the PDF export functionality.\n";
?>
