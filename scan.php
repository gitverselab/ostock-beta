<?php

function scanDirectory($dir, $level = 0) {
    // Check if the directory exists
    if (!is_dir($dir)) {
        echo "The specified path is not a directory." . PHP_EOL;
        return;
    }

    // Create a RecursiveDirectoryIterator
    $iterator = new RecursiveDirectoryIterator($dir);
    $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);

    // Create a RecursiveIteratorIterator to traverse the directory
    $recursiveIterator = new RecursiveIteratorIterator($iterator);

    // Loop through each item in the directory
    foreach ($recursiveIterator as $fileInfo) {
        // Get the indentation based on the level
        $indentation = str_repeat('    ', $level);
        
        // Print the file or directory name
        if ($fileInfo->isDir()) {
            echo $indentation . "[DIR] " . $fileInfo->getFilename() . PHP_EOL;
        } else {
            echo $indentation . "[FILE] " . $fileInfo->getFilename() . PHP_EOL;
        }
        
        // Increase the level for subdirectories
        if ($fileInfo->isDir()) {
            scanDirectory($fileInfo->getPathname(), $level + 1);
        }
    }
}

// Specify the directory you want to scan
$directoryToScan = 'test'; // Change this to your target directory

// Call the function to scan the directory
scanDirectory($directoryToScan);
?>