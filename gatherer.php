<?php
//Little Buggy, but moves all files that are used (like a web crawler) to another directory. Still doesn't pick up about 30% of the files.

function resolveFilePath($baseDir, $path) {
    // Resolve relative paths to absolute paths
    if (strpos($path, '://') !== false) {
        return $path; // Skip URLs or external links
    }
    return realpath($baseDir . DIRECTORY_SEPARATOR . $path);
}

function moveFile($source, $targetDir) {
    // Ensure the target directory exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true); // Create the directory if it doesn't exist
    }

    $fileName = basename($source); // Get the base name of the file
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName; // Build the destination path

    if (!is_dir($source)) {
            // Try to move the file
            if (rename($source, $targetPath)) {
                echo "Moved file: $source to $targetPath\n";
            } else {
                echo "Failed to move file: $source\n";
            }
    }
}

// Function to recursively scan for dependencies in a file
function scanFileForDependencies($filePath, $baseDir, &$processedFiles) {
    if (in_array($filePath, $processedFiles)) {
        // Avoid processing the same file twice to prevent infinite loops
        return [];
    }

    // Add this file to the processed files list to avoid infinite recursion
    $processedFiles[] = $filePath;

    // Read the contents of the file
    $content = file_get_contents($filePath);

    // checks for patterns of file usage like fetch and XMLHTTPRequests and many others like css.
  $patterns = [
    // HTML file inclusions
    '/src=["\']([^"\']+)["\']|`([^`]+)`/',              // Matches src="..." or `...` (image, script, etc.)
    '/href=["\']([^"\']+)["\']|`([^`]+)`/',             // Matches href="..." or `...` (link, stylesheet)
    '/action=["\']([^"\']+)["\']|`([^`]+)`/',           // Matches action="..." or `...` (form action)
    '/data-src=["\']([^"\']+)["\']|`([^`]+)`/',         // Matches data-src="..." or `...` (lazy-loaded image or content)
    '/poster=["\']([^"\']+)["\']|`([^`]+)`/',           // Matches poster="..." or `...` (video poster image)
    '/rel=["\']stylesheet["\']\s+href=["\']([^"\']+)["\']|`([^`]+)`/', // Matches <link rel="stylesheet" href="..."> or `...`
    '/rel=["\']import["\']\s+href=["\']([^"\']+)["\']|`([^`]+)`/',    // Matches <link rel="import" href="..."> or `...`

    // JavaScript inclusions
    '/import\s+[\'"]([^\'"]+)[\'"]|`([^`]+)`/',         // Matches import '...' or `...` (ES6 Module imports)
    '/fetch\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/',         // Matches fetch('...') or `...`
    '/new\s+Request\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/', // Matches new Request('...') or `...`
    '/XMLHttpRequest\s*\(\)\s*\.\s*open\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/', // Matches XMLHttpRequest('...') or `...`
    '/ajax\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/',           // Matches ajax('...') or `...` (custom JS library or jQuery)
    '/\$\("\w+"\)\.load\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/',  // Matches jQuery .load('...') or `...`
    '/\$\("\w+"\)\.get\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/',   // Matches jQuery .get('...') or `...`
    '/\$\("\w+"\)\.post\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/',  // Matches jQuery .post('...') or `...`
    '/\$\("\w+"\)\.ajax\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/',  // Matches jQuery .ajax('...') or `...`

    // DotPipe AJAX inclusion
    '/ajax=["\']([^"\']+)["\']|`([^`]+)`/',             // Matches ajax="..." or `...` in DotPipe nodes

    // PHP includes and requires
    '/include[_once]*\s*[\'"]([^\'"]+)[\'"]|`([^`]+)`/',   // Matches include or include_once (PHP) or `...`
    '/require[_once]*\s*[\'"]([^\'"]+)[\'"]|`([^`]+)`/',   // Matches require or require_once (PHP) or `...`

    // Angular Includes
    '/templateUrl\s*:\s*[\'"]([^\'"]+)[\'"]|`([^`]+)`/',   // Matches Angular templateUrl or `...`
    '/styleUrls\s*:\s*\[([^\]]+)\]|`([^`]+)`/',             // Matches Angular styleUrls array (multiple files) or `...`

    // React Includes (JSX)
    '/src\s*=\s*[\'"]([^\'"]+)[\'"]|`([^`]+)`/',            // Matches React component `src` or `...`
    '/href\s*=\s*[\'"]([^\'"]+)[\'"]|`([^`]+)`/',           // Matches React component `href` or `...`
    '/import\s+\{[^}]*\}\s*from\s*[\'"]([^\'"]+)[\'"]|`([^`]+)`/', // Matches React import { ... } from '...' or `...`

    // Webpack / Bundlers (dynamic imports)
    '/import\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/',            // Matches dynamic imports like `import('file')` or `...`
    '/__webpack_public_path__\s*=\s*[\'"]([^\'"]+)[\'"]|`([^`]+)`/', // Matches webpack public path variable or `...`

    // CSS @import
    '/@import\s+url\(["\']([^"\']+)["\']\)|`([^`]+)`/',     // Matches @import url("...") or `...`
    '/@import\s+["\']([^"\']+)["\']|`([^`]+)`/',            // Matches @import '...' or `...`

    // Node.js require
    '/require\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/',           // Matches Node.js require('...') or `...`

    // JSONP and other dynamic requests
    '/\$\(["\']([^"\']+)["\']\)\.json\(\)|`([^`]+)`/',     // Matches jQuery .json() method or `...`
    '/callback\s*\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/',      // Matches JSONP callback('...') or `...`
    '/jsonp\([\'"]([^\'"]+)[\'"]\)|`([^`]+)`/',            // Matches jsonp('...') or `...`

    // Other dynamic JS file loads
    '/<script\s+src=["\']([^"\']+)["\']|`([^`]+)`/i',     // Matches <script src="..."> or `...`
    '/<link\s+rel=["\']stylesheet["\']\s+href=["\']([^"\']+)["\']|`([^`]+)`/i', // Matches <link rel="stylesheet" href="..."> or `...`
    '/<object\s+data=["\']([^"\']+)["\']|`([^`]+)`/i',     // Matches <object data="..."> or `...`
    '/<iframe\s+src=["\']([^"\']+)["\']|`([^`]+)`/i',      // Matches <iframe src="..."> or `...`
    '/<embed\s+src=["\']([^"\']+)["\']|`([^`]+)`/i',       // Matches <embed src="..."> or `...`

    // Miscellaneous inclusions and resources
    '/<audio\s+src=["\']([^"\']+)["\']|`([^`]+)`/i',      // Matches <audio src="..."> or `...`
    '/<video\s+src=["\']([^"\']+)["\']|`([^`]+)`/i',      // Matches <video src="..."> or `...`
    '/<source\s+src=["\']([^"\']+)["\']|`([^`]+)`/i',     // Matches <source src="..."> or `...`
    '/<img\s+src=["\']([^"\']+)["\']|`([^`]+)`/i',        // Matches <img src="..."> or `...`
    '/<link\s+rel=["\']icon["\']\s+href=["\']([^"\']+)["\']|`([^`]+)`/i',  // Matches <link rel="icon" href="..."> or `...`

    // Node.js dynamic module import
    '/require\([\'"]([^\'"]+)[\'"]|`([^`]+)`/',            // Matches Node.js `require()` or `...`

    // Vue.js
    '/vue\s*=\s*new\s+Vue\([^\)]*\)\.component\([\'"]([^\'"]+)[\'"]|`([^`]+)`/',  // Matches Vue component imports or `...`
    '/import\s+[\'"]([^\'"]+)[\'"]\s*from\s+[\'"]([^\'"]+)[\'"]|`([^`]+)`/', // Matches Vue imports or `...`

    // Dynamic file inclusions or resources (via JS)
    '/import\([\'"]([^\'"]+)[\'"]|`([^`]+)`/',          // Matches dynamic import('...') or `...`
    '/require\([\'"]([^\'"]+)[\'"]|`([^`]+)`/',         // Matches require('...') or `...`
];

    // Store all found dependencies in an array
    $dependencies = [];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches)) {
            // Collect all dependencies found in the file
            foreach ($matches[1] as $dependency) {
                // Resolve the dependency path and add it to the dependencies list
                $resolvedPath = resolveFilePath($baseDir, $dependency);
                if ($resolvedPath && file_exists($resolvedPath)) {
                    $dependencies[] = $resolvedPath;
                }
            }
        }
    }

    return $dependencies;
}

// Recursive function to scan all files for their dependencies and move them
function moveResolvedFiles($filePath, $baseDir, $targetDir, &$processedFiles) {
    // Get all the dependencies for the current file
    $dependencies = scanFileForDependencies($filePath, $baseDir, $processedFiles);

    // Move the current file
    if (file_exists($filePath)) {
        moveFile($filePath, $targetDir);
    }

    // Now recursively move the dependencies
    foreach ($dependencies as $dependency) {
        moveResolvedFiles($dependency, $baseDir, $targetDir, $processedFiles);
    }
}

// Entry point
function generateSiteMap($baseDir, $targetDir, $startFile) {
    $processedFiles = [];
    moveResolvedFiles($startFile, $baseDir, $targetDir, $processedFiles);
}

// Example usage:
$baseDir = '.'; // Base directory to start scanning
$targetDir = './moved_files'; // The directory to move files to
$startFile = './index.php'; // The starting file (index.html or index.php)
generateSiteMap($baseDir, $targetDir, $startFile);

?>
