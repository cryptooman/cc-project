<?php
/**
 *
 */
spl_autoload_register(function(string $class) {
    $paths = [
        'direct' => ["components/src/$class.php"],
        'scan' => []
    ];

    if (strncmp($class, 'Controller', 10) === 0) {
        $paths['direct'][] = "controllers/$class.php";
        $paths['scan'][] = "controllers/*/$class.php";
    }
    elseif (strncmp($class, 'Model', 5) === 0) {
        $paths['direct'][] = "models/$class.php";
        $paths['scan'][] = "models/*/$class.php";
    }
    elseif (strncmp($class, 'Class', 5) === 0) {
        $paths['direct'][] = "classes/$class.php";
        $paths['scan'][] = "classes/*/$class.php";
    }

    $included = false;

    $baseDir = dirname(__FILE__);
    foreach ($paths['direct'] as $path) {
        $file = $baseDir . '/' . $path;
        if (is_file($file)) {
            include_once $file;
            $included = true;
            break;
        }
    }

    if (!$included) {
        foreach ($paths['scan'] as $path) {
            $pattern = $baseDir . '/' . $path;
            if (($files = glob($pattern, GLOB_ERR)) === false) {
                throw new Err("Failed to scan path [$pattern]");
            }
            if ($files) {
                // Class files must be different even if different sub-folders are used
                if (count($files) != 1) {
                    throw new Err("Found equal class files: ", $files);
                }
                include_once $files[0];
                $included = true;
                break;
            }
        }
    }

    if (!$included) {
        throw new Err(
            "Class [$class] not found: Tried paths: ", join(', ', array_merge($paths['direct'], $paths['scan']))
        );
    }
});