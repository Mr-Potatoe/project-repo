<?php
$projectInfo = json_decode(file_get_contents(__DIR__ . '/project-info.json'), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($projectInfo['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-4">
                <?= htmlspecialchars($projectInfo['name']) ?>
            </h1>
            
            <div class="mb-4">
                <p class="text-gray-600">
                    <?= htmlspecialchars($projectInfo['description']) ?>
                </p>
            </div>
            
            <div class="border-t pt-4">
                <h2 class="text-xl font-semibold text-gray-700 mb-2">Project Details</h2>
                <dl class="grid grid-cols-1 gap-2">
                    <div class="flex">
                        <dt class="font-medium text-gray-500 w-24">Type:</dt>
                        <dd class="text-gray-800"><?= htmlspecialchars($projectInfo['type']) ?></dd>
                    </div>
                    <div class="flex">
                        <dt class="font-medium text-gray-500 w-24">Status:</dt>
                        <dd class="text-gray-800"><?= htmlspecialchars($projectInfo['status']) ?></dd>
                    </div>
                    <div class="flex">
                        <dt class="font-medium text-gray-500 w-24">Created:</dt>
                        <dd class="text-gray-800"><?= htmlspecialchars($projectInfo['created_at']) ?></dd>
                    </div>
                </dl>
            </div>
            
            <div class="mt-6 bg-gray-50 p-4 rounded-md">
                <h2 class="text-lg font-semibold text-gray-700 mb-2">Project Structure</h2>
                <pre class="text-sm text-gray-600 overflow-auto">
<?php
function listDirectory($dir, $prefix = '') {
    $files = scandir($dir);
    $output = '';
    foreach ($files as $file) {
        if ($file != "." && $file != "..") {
            $output .= $prefix . ($prefix ? '└── ' : '') . $file . "\n";
            if (is_dir($dir . '/' . $file)) {
                $output .= listDirectory($dir . '/' . $file, $prefix . '    ');
            }
        }
    }
    return $output;
}
echo htmlspecialchars(listDirectory(__DIR__));
?>
                </pre>
            </div>
        </div>
    </div>
</body>
</html>
