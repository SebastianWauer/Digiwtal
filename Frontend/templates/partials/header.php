<?php
/**
 * Build navigation tree from flat array
 */
if (!function_exists('buildNavTree')) {
    function buildNavTree(array $items, $parentId = null, array $visitedIds = []): array {
        $branch = [];
        
        foreach ($items as $item) {
            // Normalize parent_id key and convert to int|null
            $rawParentId = $item['parent_id'] ?? $item['parentId'] ?? null;
            $itemParentId = null;
            if ($rawParentId !== null && $rawParentId !== '') {
                $itemParentId = (int)$rawParentId;
            }
            
            // Match logic based on parentId type
            $matches = false;
            if ($parentId === null) {
                // Looking for null parents
                $matches = ($itemParentId === null);
            } elseif ($parentId === 0) {
                // Looking for 0 parents
                $matches = ($itemParentId === 0);
            } else {
                // Looking for specific non-root parent
                $matches = ($itemParentId === (int)$parentId);
            }
            
            if (!$matches) {
                continue;
            }
            
            // Normalize URL
            $url = $item['url'] ?? null;
            if ($url === null) {
                $itemSlug = (string)($item['slug'] ?? '');
                $url = in_array($itemSlug, ['start', 'home'], true) ? '/' : '/' . $itemSlug;
            }
            
            // Skip absolute URLs (security)
            if (preg_match('#^https?://#i', $url)) {
                continue;
            }
            
            // Skip protocol-relative URLs (security)
            if (str_starts_with($url, '//')) {
                continue;
            }
            
            // Ensure URL starts with /
            if ($url === '' || $url[0] !== '/') {
                $url = '/' . $url;
            }
            
            $nodeId = (int)($item['id'] ?? 0);
            
            // Recursion protection: check if this node was already visited
            if (in_array($nodeId, $visitedIds, true)) {
                continue;
            }
            
            $node = [
                'id' => $nodeId,
                'title' => (string)($item['title'] ?? 'Untitled'),
                'url' => $url,
                'slug' => (string)($item['slug'] ?? ''),
                'nav_order' => (int)($item['nav_order'] ?? 9999),
                'children' => buildNavTree($items, $nodeId, array_merge($visitedIds, [$nodeId]))
            ];
            
            $branch[] = $node;
        }
        
        // Sort by nav_order ASC, then by title
        usort($branch, function($a, $b) {
            if ($a['nav_order'] !== $b['nav_order']) {
                return $a['nav_order'] <=> $b['nav_order'];
            }
            return $a['title'] <=> $b['title'];
        });
        
        return $branch;
    }
}

/**
 * Mark active states in navigation tree
 * Returns updated tree with active_self and active_any flags
 */
if (!function_exists('markNavActive')) {
    function markNavActive(array $tree, string $currentSlug): array {
        foreach ($tree as &$node) {
            // Check if this node is the current page
            $selfActive = false;
            if (in_array($currentSlug, ['start', 'home'], true) && $node['url'] === '/') {
                $selfActive = true;
            } elseif (!in_array($currentSlug, ['start', 'home'], true) && $node['slug'] === $currentSlug) {
                $selfActive = true;
            }
            
            // Mark children recursively
            $childActive = false;
            if (!empty($node['children'])) {
                $node['children'] = markNavActive($node['children'], $currentSlug);
                // Check if any child is active
                foreach ($node['children'] as $child) {
                    if ($child['active_any'] ?? false) {
                        $childActive = true;
                        break;
                    }
                }
            }
            
            $node['active_self'] = $selfActive;
            $node['active_any'] = $selfActive || $childActive;
        }
        unset($node);
        
        return $tree;
    }
}

/**
 * Render navigation tree recursively
 */
if (!function_exists('renderNavTree')) {
    function renderNavTree(array $tree): void {
        if (empty($tree)) {
            return;
        }
        
        echo '<ul>';
        foreach ($tree as $node) {
            echo '<li>';
            echo '<a href="' . e($node['url']) . '"';
            if ($node['active_any'] ?? false) {
                echo ' class="active"';
            }
            if ($node['active_self'] ?? false) {
                echo ' aria-current="page"';
            }
            echo '>' . e($node['title']) . '</a>';
            
            if (!empty($node['children'])) {
                renderNavTree($node['children']);
            }
            
            echo '</li>';
        }
        echo '</ul>';
    }
}

// Auto-detect root parent value
$navItems = $headerNavItems ?? $navItems ?? [];
$rootParent = null;
foreach ($navItems as $item) {
    $rawParentId = $item['parent_id'] ?? $item['parentId'] ?? null;
    if ($rawParentId !== null && trim((string)$rawParentId) === '0') {
        $rootParent = 0;
        break;
    }
}

// Build tree, mark active states, and render navigation
$tree = buildNavTree($navItems, $rootParent);
$tree = markNavActive($tree, $slug ?? 'home');
?>
<header>
    <div><a href="/"><?php echo e($siteName ?? 'Website'); ?></a></div>
    <?php if (!empty($tree)): ?>
    <nav>
        <?php renderNavTree($tree); ?>
    </nav>
    <?php endif; ?>
</header>
