<?php
/**
 * 分類管理頁面
 */

define('QUOTABASE_SYSTEM', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../partials/ui.php';

if (!is_logged_in()) {
    header('Location: /login.php');
    exit;
}

$allowed_types = [
    'product' => '產品分類',
    'service' => '服務分類'
];

$type = $_GET['type'] ?? 'product';
if (!array_key_exists($type, $allowed_types)) {
    $type = 'product';
}

$error = '';
$success = $_GET['success'] ?? '';
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$edit_category = $edit_id ? get_catalog_category($edit_id) : null;
if ($edit_category && $edit_category['type'] !== $type) {
    $edit_category = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '無效的請求，請重新整理頁面後重試。';
    } else {
        $action = $_POST['action'] ?? '';
        $form_type = $_POST['type'] ?? $type;
        if (!array_key_exists($form_type, $allowed_types)) {
            $form_type = 'product';
        }

        if ($action === 'create') {
            $result = create_catalog_category([
                'type' => $form_type,
                'parent_id' => $_POST['parent_id'] ?? null,
                'name' => $_POST['name'] ?? '',
                'sort_order' => $_POST['sort_order'] ?? 0
            ]);

            if ($result['success']) {
                header('Location: /categories/index.php?type=' . $form_type . '&success=' . urlencode($result['message']));
                exit;
            }
            $error = $result['error'];

        } elseif ($action === 'update') {
            $category_id = intval($_POST['category_id'] ?? 0);
            $category = get_catalog_category($category_id);

            if (!$category) {
                $error = '分類不存在或已被刪除';
            } else {
                $result = update_catalog_category($category_id, [
                    'name' => $_POST['name'] ?? '',
                    'sort_order' => $_POST['sort_order'] ?? $category['sort_order']
                ]);

                if ($result['success']) {
                    header('Location: /categories/index.php?type=' . $category['type'] . '&success=' . urlencode($result['message']));
                    exit;
                }
                $error = $result['error'];
                $edit_id = $category_id;
                $edit_category = $category;
                $type = $category['type'];
            }

        } elseif ($action === 'delete') {
            $category_id = intval($_POST['category_id'] ?? 0);
            $category = get_catalog_category($category_id);

            if (!$category) {
                $error = '分類不存在或已被刪除';
            } else {
                $result = delete_catalog_category($category_id);
                if ($result['success']) {
                    header('Location: /categories/index.php?type=' . $category['type'] . '&success=' . urlencode($result['message']));
                    exit;
                }
                $error = $result['error'];
                $type = $category['type'];
            }
        }
    }
}

$category_tree = get_catalog_categories_tree($type);
$category_flat = get_catalog_category_flat_list($type);
$default_parent_id = isset($_GET['parent']) ? intval($_GET['parent']) : null;

html_start('分類管理');

page_header('產品與服務', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '產品與服務', 'url' => '/products/'],
    ['label' => $allowed_types[$type], 'url' => '/categories/index.php?type=' . $type]
]);

function render_category_tree(array $nodes, string $type): void {
    if (empty($nodes)) {
        echo '<p class="text-tertiary" style="margin: 12px 0;">暫無分類</p>';
        return;
    }

    echo '<ul class="category-tree">';
    foreach ($nodes as $node) {
        $has_children = !empty($node['children']);
        $level = intval($node['level'] ?? 1);
        $is_collapsible = $has_children && $level <= 2;
        $default_collapsed = $is_collapsible && $level > 1;
        $li_classes = ['category-tree-item'];
        if ($has_children) {
            $li_classes[] = 'has-children';
        }
        if ($is_collapsible) {
            $li_classes[] = 'is-collapsible';
        }
        $item_id = 'category-node-' . $node['id'];
        echo '<li id="' . h($item_id) . '" class="' . implode(' ', $li_classes) . '" data-category-id="' . $node['id'] . '" data-level="' . $level . '"';
        if ($is_collapsible) {
            echo ' data-collapsed="' . ($default_collapsed ? 'true' : 'false') . '"';
        }
        echo '>';
        echo '<div class="category-node">';
        if ($is_collapsible) {
            $initial_label = $default_collapsed ? '展開子分類' : '收合子分類';
            $initial_expanded = $default_collapsed ? 'false' : 'true';
            echo '<button type="button" class="category-toggle" aria-expanded="' . $initial_expanded . '" aria-label="' . $initial_label . '" data-category-toggle data-target-id="' . h($item_id) . '" onclick="return window.toggleCategoryNode ? window.toggleCategoryNode(this) : false;">';
            echo '<span class="category-toggle-icon" aria-hidden="true"></span>';
            echo '</button>';
        } elseif ($has_children) {
            echo '<span class="category-toggle category-toggle--spacer" aria-hidden="true"></span>';
        }
        echo '<div class="category-info">';
        echo '<div class="category-name">' . h($node['name']) . '</div>';
        echo '<div class="category-meta">第 ' . $node['level'] . ' 級 · 排序 ' . intval($node['sort_order']) . '</div>';
        echo '</div>';
        echo '<div class="category-actions">';
        echo '<a class="btn btn-sm btn-outline" href="/categories/index.php?type=' . $type . '&edit=' . $node['id'] . '">編輯</a>';
        if ($node['level'] < 3) {
            echo '<a class="btn btn-sm btn-outline" href="/categories/index.php?type=' . $type . '&parent=' . $node['id'] . '#create">新增子分類</a>';
        }
        echo '<form method="POST" action="/categories/index.php?type=' . $type . '" onsubmit="return confirm(\'確認刪除該分類及其子分類嗎？相關產品將變為未分類。\');">';
        echo csrf_input();
        echo '<input type="hidden" name="action" value="delete">';
        echo '<input type="hidden" name="type" value="' . h($type) . '">';
        echo '<input type="hidden" name="category_id" value="' . $node['id'] . '">';
        echo '<button type="submit" class="btn btn-sm btn-outline btn-danger">刪除</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';

        if (!empty($node['children'])) {
            echo '<div class="category-children">';
            render_category_tree($node['children'], $type);
            echo '</div>';
        }

        echo '</li>';
    }
    echo '</ul>';
}

?>

<div class="main-content">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <span class="alert-message"><?php echo h($success); ?></span>
        </div>
    <?php endif; ?>

    <?php card_start('分類管理', [
        ['label' => '產品分類', 'url' => '/categories/index.php?type=product', 'class' => $type === 'product' ? 'btn-primary' : 'btn-outline'],
        ['label' => '服務分類', 'url' => '/categories/index.php?type=service', 'class' => $type === 'service' ? 'btn-primary' : 'btn-outline'],
    ]); ?>

    <div class="category-management-grid">
        <div>
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 12px; color: var(--text-primary);">分類結構</h3>
            <p style="font-size: 13px; color: var(--text-tertiary); margin-bottom: 16px;">最多支援三級分類。刪除分類會清除其子分類，並將相關產品/服務的分類置為空。</p>
            <div class="category-tree-wrapper" data-category-tree data-category-type="<?php echo h($type); ?>">
                <?php render_category_tree($category_tree, $type); ?>
            </div>
        </div>

        <div id="create">
            <div class="category-form-card">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">
                    <?php echo $edit_category ? '編輯分類' : '新增分類'; ?>
                </h3>

                <form method="POST" action="/categories/index.php?type=<?php echo h($type); ?>">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="type" value="<?php echo h($type); ?>">
                    <input type="hidden" name="action" value="<?php echo $edit_category ? 'update' : 'create'; ?>">
                    <?php if ($edit_category): ?>
                        <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
                    <?php endif; ?>

                    <?php if (!$edit_category): ?>
                        <label class="form-label" for="parent_id">上級分類</label>
                        <select name="parent_id" id="parent_id" class="form-select">
                            <option value="">頂級分類</option>
                            <?php foreach ($category_flat as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($default_parent_id && $default_parent_id == $cat['id']) ? 'selected' : ''; ?> <?php echo $cat['level'] >= 3 ? 'disabled' : ''; ?>>
                                    <?php echo str_repeat('— ', max(0, $cat['level'] - 1)) . h($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-help">最多支援三級分類，選擇上級分類可建立子分類。</p>
                    <?php else: ?>
                        <label class="form-label">上級分類</label>
                        <div class="readonly-field">
                            <?php echo $edit_category['parent_id'] ? h(get_catalog_category_path($edit_category['parent_id'])) : '頂級分類'; ?>
                        </div>
                    <?php endif; ?>

                    <label class="form-label" for="category_name">分類名稱</label>
                    <input type="text" id="category_name" name="name" class="form-input" value="<?php echo h($edit_category['name'] ?? ''); ?>" required maxlength="100" placeholder="請輸入分類名稱">

                    <label class="form-label" for="category_sort">排序值</label>
                    <input type="number" id="category_sort" name="sort_order" class="form-input" value="<?php echo h($edit_category['sort_order'] ?? 0); ?>" placeholder="0">
                    <p class="form-help">數字越小越靠前，可用來控制同層級分類的顯示順序。</p>

                    <div style="margin-top: 16px; display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary"><?php echo $edit_category ? '儲存分類' : '新增分類'; ?></button>
                        <?php if ($edit_category): ?>
                            <a href="/categories/index.php?type=<?php echo h($type); ?>" class="btn btn-outline">取消編輯</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php card_end(); ?>

    <script>
        (function () {
            var treeWrapper = null;
            var storageKey = 'category-collapse-product';
            var savedState = {};

            function applyState(item, collapsed) {
                if (!item) {
                    return;
                }
                item.setAttribute('data-collapsed', collapsed ? 'true' : 'false');
                var toggle = item.querySelector('[data-category-toggle]');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    toggle.setAttribute('aria-label', collapsed ? '展開子分類' : '收合子分類');
                }
            }

            function initTree() {
                treeWrapper = document.querySelector('[data-category-tree]');
                if (!treeWrapper) {
                    return;
                }
                var type = treeWrapper.getAttribute('data-category-type') || 'product';
                storageKey = 'category-collapse-' + type;
                savedState = {};
                try {
                    var stored = localStorage.getItem(storageKey);
                    savedState = stored ? JSON.parse(stored) : {};
                } catch (err) {
                    savedState = {};
                }

                var items = treeWrapper.querySelectorAll('.category-tree-item.is-collapsible');
                for (var i = 0; i < items.length; i++) {
                    var item = items[i];
                    var id = item.getAttribute('data-category-id');
                    if (!id) {
                        continue;
                    }
                    var desired = savedState[id];
                    if (typeof desired === 'boolean') {
                        applyState(item, desired);
                    }
                }
            }

            window.toggleCategoryNode = function (button) {
                if (!treeWrapper) {
                    initTree();
                }
                if (!treeWrapper || !button) {
                    return false;
                }

                var targetId = button.getAttribute('data-target-id');
                var item = targetId ? document.getElementById(targetId) : null;
                if (!item) {
                    item = button;
                    while (item && (!item.className || item.className.indexOf('category-tree-item') === -1)) {
                        item = item.parentNode;
                    }
                }
                if (!item) {
                    return false;
                }

                var collapsed = item.getAttribute('data-collapsed') === 'true';
                var nextState = !collapsed;
                applyState(item, nextState);

                var categoryId = item.getAttribute('data-category-id');
                if (categoryId) {
                    savedState[categoryId] = nextState;
                    try {
                        localStorage.setItem(storageKey, JSON.stringify(savedState));
                    } catch (err) {
                        // ignore storage errors
                    }
                }
                return false;
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initTree);
            } else {
                initTree();
            }
        })();
    </script>
</div>

<?php
bottom_tab_navigation();
html_end();
?>
