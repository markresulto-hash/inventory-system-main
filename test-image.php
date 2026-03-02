<?php
$imagePath = '/inventory-system-main/uploads/products/';
$files = glob('uploads/products/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

echo "<h2>Uploaded Images:</h2>";
if (count($files) > 0) {
    foreach($files as $file) {
        $filename = basename($file);
        echo "<div>";
        echo "<p>Filename: $filename</p>";
        echo "<p>Full path: /inventory-system-main/uploads/products/$filename</p>";
        echo "<img src='/inventory-system-main/uploads/products/$filename' style='max-width:200px;' onerror='this.style.display=\"none\"; this.parentElement.innerHTML+=\" ❌ IMAGE NOT FOUND\";'>";
        echo "</div><hr>";
    }
} else {
    echo "No images found in uploads/products/";
}

echo "<h2>Check if directory exists:</h2>";
$dir = __DIR__ . '/uploads/products/';
if (is_dir($dir)) {
    echo "✅ Directory exists: $dir<br>";
    echo "Is writable: " . (is_writable($dir) ? "✅ Yes" : "❌ No");
} else {
    echo "❌ Directory does NOT exist: $dir";
}
?>