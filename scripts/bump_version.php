<?php
$versionFile = __DIR__ . '/../VERSION';
$changelogFile = __DIR__ . '/../CHANGELOG.md';

if (!file_exists($versionFile)) {
    $current = '1.0.0';
} else {
    $current = trim(file_get_contents($versionFile));
}

preg_match('/^(\d+)\.(\d+)\.(\d+)/', $current, $m);
$major = (int)$m[1];
$minor = (int)$m[2];
$patch = (int)$m[3];
$patch++; // default bump patch
$newVersion = "$major.$minor.$patch";

$commit = trim(shell_exec('git log -1 --pretty=format:"%h - %s"'));
$date = date('Y-m-d');

$entry = "\n## [$newVersion] - $date\n- $commit\n";

if (file_exists($changelogFile)) {
    $contents = file_get_contents($changelogFile);
    $contents .= $entry;
} else {
    $contents = "# Changelog\n" . $entry;
}
file_put_contents($changelogFile, $contents);
file_put_contents($versionFile, $newVersion . "\n");

echo "Bumped to $newVersion\n";
?>
