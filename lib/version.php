<?php
/**
 * Version helper functions for "What's New" popup
 */

/**
 * Get the current application version from VERSION file
 */
function getCurrentVersion() {
    $versionFile = __DIR__ . '/../VERSION';
    if (file_exists($versionFile)) {
        return trim(file_get_contents($versionFile));
    }
    return '0.0.0';
}

/**
 * Check if we should show the What's New popup
 */
function shouldShowWhatsNew($lastSeenVersion) {
    $currentVersion = getCurrentVersion();
    return version_compare($lastSeenVersion, $currentVersion, '<');
}

/**
 * Mark a version as seen for a user
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param string $userType 'admin' or 'store'
 * @param string $version Version to mark as seen
 */
function markVersionSeen($pdo, $userId, $userType, $version = null) {
    if ($version === null) {
        $version = getCurrentVersion();
    }

    $table = ($userType === 'admin') ? 'users' : 'store_users';

    try {
        // Check if column exists first
        $colCheck = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'last_seen_version'");
        if ($colCheck->fetch() === false) {
            // Column doesn't exist yet, skip silently
            return true;
        }

        $stmt = $pdo->prepare("UPDATE {$table} SET last_seen_version = ? WHERE id = ?");
        $stmt->execute([$version, $userId]);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to mark version seen: " . $e->getMessage());
        return false;
    }
}

/**
 * Parse the CHANGELOG.md and get entries for a specific version
 * @param string $version Version to get changelog for (e.g., "2.2.1")
 * @return array Parsed changelog sections (Added, Changed, Fixed, Removed)
 */
function getChangelogForVersion($version) {
    $changelogFile = __DIR__ . '/../CHANGELOG.md';
    if (!file_exists($changelogFile)) {
        return null;
    }

    $content = file_get_contents($changelogFile);
    $lines = explode("\n", $content);

    $inTargetVersion = false;
    $currentSection = null;
    $changelog = [
        'version' => $version,
        'date' => '',
        'Added' => [],
        'Changed' => [],
        'Fixed' => [],
        'Removed' => []
    ];

    foreach ($lines as $line) {
        // Check for version header: ## [2.2.1] - 2025-12-27
        if (preg_match('/^## \[(' . preg_quote($version, '/') . ')\] - (.+)$/', $line, $matches)) {
            $inTargetVersion = true;
            $changelog['date'] = trim($matches[2]);
            continue;
        }

        // Check if we've hit the next version (stop parsing)
        if ($inTargetVersion && preg_match('/^## \[/', $line)) {
            break;
        }

        if ($inTargetVersion) {
            // Check for section headers: ### Added, ### Changed, etc.
            if (preg_match('/^### (Added|Changed|Fixed|Removed)/', $line, $matches)) {
                $currentSection = $matches[1];
                continue;
            }

            // Check for list items: - Some change here
            if ($currentSection && preg_match('/^- (.+)$/', $line, $matches)) {
                $changelog[$currentSection][] = trim($matches[1]);
            }
        }
    }

    return $inTargetVersion ? $changelog : null;
}

/**
 * Get all changelog entries since a given version
 * Returns all versions newer than $lastSeenVersion
 * @param string $lastSeenVersion The last version the user saw
 * @return array Array of changelog entries for all missed versions
 */
function getChangelogSinceVersion($lastSeenVersion) {
    $changelogFile = __DIR__ . '/../CHANGELOG.md';
    if (!file_exists($changelogFile)) {
        return [];
    }

    $content = file_get_contents($changelogFile);
    $lines = explode("\n", $content);

    $versions = [];
    $currentVersionIndex = -1;
    $currentSection = null;

    foreach ($lines as $line) {
        // Check for version header: ## [2.2.1] - 2025-12-27
        if (preg_match('/^## \[([^\]]+)\] - (.+)$/', $line, $matches)) {
            $version = $matches[1];
            // Only include versions newer than lastSeenVersion
            if (version_compare($version, $lastSeenVersion, '>')) {
                $versions[] = [
                    'version' => $version,
                    'date' => trim($matches[2]),
                    'Added' => [],
                    'Changed' => [],
                    'Fixed' => [],
                    'Removed' => []
                ];
                $currentVersionIndex = count($versions) - 1;
            } else {
                // Stop parsing once we hit an older version
                break;
            }
            $currentSection = null;
            continue;
        }

        if ($currentVersionIndex >= 0) {
            // Check for section headers: ### Added, ### Changed, etc.
            if (preg_match('/^### (Added|Changed|Fixed|Removed)/', $line, $matches)) {
                $currentSection = $matches[1];
                continue;
            }

            // Check for list items: - Some change here
            if ($currentSection && preg_match('/^- (.+)$/', $line, $matches)) {
                $versions[$currentVersionIndex][$currentSection][] = trim($matches[1]);
            }
        }
    }

    return $versions;
}

/**
 * Generate HTML for multiple changelog versions (What's New modal with all missed updates)
 * @param array $changelogs Array of changelog entries
 * @return string HTML content
 */
function generateMultiVersionWhatsNewHTML($changelogs) {
    if (empty($changelogs)) {
        return '<p>Thanks for updating! Check the changelog for details.</p>';
    }

    $html = '';
    $sections = [
        'Added' => ['icon' => 'bi-plus-circle-fill', 'color' => '#198754'],
        'Changed' => ['icon' => 'bi-arrow-repeat', 'color' => '#0dcaf0'],
        'Fixed' => ['icon' => 'bi-check-circle-fill', 'color' => '#667eea'],
        'Removed' => ['icon' => 'bi-trash-fill', 'color' => '#dc3545']
    ];

    foreach ($changelogs as $index => $changelog) {
        $html .= '<div class="whats-new-version' . ($index > 0 ? ' mt-4 pt-3 border-top' : '') . '">';
        $html .= '<h6 class="d-flex align-items-center justify-content-between mb-3">';
        $html .= '<span class="fw-bold"><i class="bi bi-tag-fill me-2" style="color: #667eea;"></i>v' . htmlspecialchars($changelog['version']) . '</span>';
        $html .= '<small class="text-muted">' . htmlspecialchars($changelog['date']) . '</small>';
        $html .= '</h6>';

        $hasContent = false;
        foreach ($sections as $section => $style) {
            if (!empty($changelog[$section])) {
                $hasContent = true;
                $html .= '<div class="whats-new-section mb-2">';
                $html .= '<h6 class="d-flex align-items-center mb-2" style="font-size: 0.85rem;">';
                $html .= '<i class="bi ' . $style['icon'] . ' me-2" style="color: ' . $style['color'] . '"></i>';
                $html .= $section;
                $html .= '</h6>';
                $html .= '<ul class="whats-new-list mb-0">';
                foreach ($changelog[$section] as $item) {
                    $html .= '<li>' . htmlspecialchars($item) . '</li>';
                }
                $html .= '</ul>';
                $html .= '</div>';
            }
        }

        if (!$hasContent) {
            $html .= '<p class="text-muted mb-0" style="font-size: 0.9rem;">Minor improvements and bug fixes.</p>';
        }

        $html .= '</div>';
    }

    return $html;
}

/**
 * Generate HTML for the What's New modal content
 * @param array $changelog Parsed changelog data
 * @return string HTML content
 */
function generateWhatsNewHTML($changelog) {
    if (!$changelog) {
        return '<p>No changelog available for this version.</p>';
    }

    $html = '';

    $sections = [
        'Added' => ['icon' => 'bi-plus-circle-fill', 'color' => '#198754'],
        'Changed' => ['icon' => 'bi-arrow-repeat', 'color' => '#0dcaf0'],
        'Fixed' => ['icon' => 'bi-check-circle-fill', 'color' => '#667eea'],
        'Removed' => ['icon' => 'bi-trash-fill', 'color' => '#dc3545']
    ];

    foreach ($sections as $section => $style) {
        if (!empty($changelog[$section])) {
            $html .= '<div class="whats-new-section mb-3">';
            $html .= '<h6 class="d-flex align-items-center mb-2">';
            $html .= '<i class="bi ' . $style['icon'] . ' me-2" style="color: ' . $style['color'] . '"></i>';
            $html .= $section;
            $html .= '</h6>';
            $html .= '<ul class="whats-new-list mb-0">';
            foreach ($changelog[$section] as $item) {
                $html .= '<li>' . htmlspecialchars($item) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
    }

    return $html ?: '<p>Minor improvements and bug fixes.</p>';
}

/**
 * Parse the full CHANGELOG.md and generate HTML for all versions
 * @return string HTML content for the full changelog
 */
function generateFullChangelogHTML() {
    $changelogFile = __DIR__ . '/../CHANGELOG.md';
    if (!file_exists($changelogFile)) {
        return '<p>No changelog available.</p>';
    }

    $content = file_get_contents($changelogFile);
    $lines = explode("\n", $content);

    $html = '';
    $currentSection = null;
    $versions = [];

    $sections = [
        'Added' => ['icon' => 'bi-plus-circle-fill', 'color' => '#198754'],
        'Changed' => ['icon' => 'bi-arrow-repeat', 'color' => '#0dcaf0'],
        'Fixed' => ['icon' => 'bi-check-circle-fill', 'color' => '#667eea'],
        'Removed' => ['icon' => 'bi-trash-fill', 'color' => '#dc3545']
    ];

    $currentVersionIndex = -1;

    foreach ($lines as $line) {
        // Check for version header: ## [2.2.1] - 2025-12-27
        if (preg_match('/^## \[([^\]]+)\] - (.+)$/', $line, $matches)) {
            $versions[] = [
                'version' => $matches[1],
                'date' => trim($matches[2]),
                'Added' => [],
                'Changed' => [],
                'Fixed' => [],
                'Removed' => []
            ];
            $currentVersionIndex = count($versions) - 1;
            $currentSection = null;
            continue;
        }

        if ($currentVersionIndex >= 0) {
            // Check for section headers: ### Added, ### Changed, etc.
            if (preg_match('/^### (Added|Changed|Fixed|Removed)/', $line, $matches)) {
                $currentSection = $matches[1];
                continue;
            }

            // Check for list items: - Some change here
            if ($currentSection && preg_match('/^- (.+)$/', $line, $matches)) {
                $versions[$currentVersionIndex][$currentSection][] = trim($matches[1]);
            }
        }
    }

    // Generate HTML for each version
    foreach ($versions as $ver) {
        $html .= '<div class="changelog-version mb-4">';
        $html .= '<h5 class="d-flex align-items-center justify-content-between mb-3">';
        $html .= '<span><i class="bi bi-tag-fill me-2" style="color: #667eea;"></i>v' . htmlspecialchars($ver['version']) . '</span>';
        $html .= '<small class="text-muted">' . htmlspecialchars($ver['date']) . '</small>';
        $html .= '</h5>';

        $hasContent = false;
        foreach ($sections as $section => $style) {
            if (!empty($ver[$section])) {
                $hasContent = true;
                $html .= '<div class="changelog-section mb-2">';
                $html .= '<h6 class="d-flex align-items-center mb-2">';
                $html .= '<i class="bi ' . $style['icon'] . ' me-2" style="color: ' . $style['color'] . '"></i>';
                $html .= $section;
                $html .= '</h6>';
                $html .= '<ul class="changelog-list mb-0">';
                foreach ($ver[$section] as $item) {
                    $html .= '<li>' . htmlspecialchars($item) . '</li>';
                }
                $html .= '</ul>';
                $html .= '</div>';
            }
        }

        if (!$hasContent) {
            $html .= '<p class="text-muted mb-0">Minor improvements and bug fixes.</p>';
        }

        $html .= '</div>';
        $html .= '<hr class="my-3">';
    }

    return $html ?: '<p>No changelog available.</p>';
}
