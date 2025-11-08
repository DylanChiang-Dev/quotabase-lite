<?php
/**
 * Shared UI Components
 * 共享UI元件
 *
 * @version v2.0.0
 * @description 共享的UI元件，包括頁首、底部導航等
 * @遵循憲法原則V: iOS風格使用者體驗
 */

// 防止直接訪問
if (!defined('QUOTABASE_SYSTEM')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../helpers/functions.php';
}

/**
 * 輸出HTML文件開始
 *
 * @param string $title 頁面標題
 * @param array $extra_head 額外的head內容
 */
function html_start($title = '', $extra_head = []) {
    $page_title = $title ? h($title) . ' - ' . APP_NAME : APP_NAME;
    $org_name = isset($org_settings['company_name']) ? h($org_settings['company_name']) : APP_NAME;

    echo '<!DOCTYPE html>';
    echo '<html lang="zh-TW">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="theme-color" content="#007AFF">';
    echo '<title>' . $page_title . '</title>';

    // 載入樣式檔案
    echo '<link rel="stylesheet" href="/assets/style.css">';

    // 輸出額外的head內容
    foreach ($extra_head as $content) {
        echo $content;
    }

    // 主題切換指令碼
    echo '<script>
        (function() {
            try {
                var storageKey = "qb-theme";
                var storedTheme = localStorage.getItem(storageKey);
                var theme = storedTheme;
                if (!theme) {
                    var prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
                    theme = prefersDark ? "dark" : "light";
                }
                document.documentElement.setAttribute("data-theme", theme);
                window.__QB_THEME__ = theme;
            } catch (e) {
                document.documentElement.setAttribute("data-theme", "light");
                window.__QB_THEME__ = "light";
            }
        })();
    </script>';

    echo '</head>';
    echo '<body>';
    echo '<div class="app-container">';
}

/**
 * 輸出頁首
 *
 * @param string $title 頁面標題，預設使用組織名稱
 */
function page_header($title = '', $breadcrumb_items = []) {
    $header_title = $title ? h($title) : (isset($org_settings['company_name']) ? h($org_settings['company_name']) : APP_NAME);

    echo '<header class="page-header">';
    echo '<div class="header-content">';
    echo '<div class="header-title-group">';
    echo '<h1 class="header-title">' . $header_title . '</h1>';
    if (!empty($breadcrumb_items)) {
        breadcrumb($breadcrumb_items, true);
    }
    echo '</div>';
    echo '<div class="header-actions">';
    echo '<button id="theme-toggle" type="button" aria-label="切換主題">☀️</button>';
    echo '<span class="header-date">' . date('Y-m-d') . '</span>';
    echo '</div>';
    echo '</div>';
    echo '</header>';
}

/**
 * 輸出頁尾
 */
function page_footer() {
    echo '<footer class="page-footer">';
    echo '<p>&copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.</p>';
    echo '</footer>';
}

/**
 * 輸出HTML文件結束
 */
function html_end() {
    echo '<script>
        (function() {
            const storageKey = "qb-theme";
            const doc = document.documentElement;
            const toggle = document.getElementById("theme-toggle");
            if (!toggle) return;

            const resolveTheme = () => {
                const attr = doc.getAttribute("data-theme");
                if (attr) {
                    return attr;
                }
                if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
                    return "dark";
                }
                return "light";
            };

            const applyTheme = (theme, persist = false) => {
                doc.setAttribute("data-theme", theme);
                if (persist) {
                    localStorage.setItem(storageKey, theme);
                }
                window.__QB_THEME__ = theme;
                toggle.textContent = theme === "dark" ? "🌙" : "☀️";
                toggle.setAttribute("aria-label", theme === "dark" ? "切換到淺色模式" : "切換到深色模式");
                console.debug("[QB] theme applied", theme);
            };

            applyTheme(resolveTheme());

            toggle.addEventListener("click", () => {
                const current = resolveTheme();
                const next = current === "dark" ? "light" : "dark";
                applyTheme(next, true);
            });
        })();
    </script>';
    echo '</div>'; // app-container
    echo '</body>';
    echo '</html>';
}

/**
 * 輸出麵包屑導航
 *
 * @param array $items 導航項陣列，格式：['label' => '首頁', 'url' => '/']
 */
function breadcrumb($items = [], $inline = false) {
    if (empty($items)) {
        return;
    }

    $total = count($items);

    $class = 'breadcrumb' . ($inline ? ' breadcrumb-inline' : '');

    echo '<nav class="' . $class . '" aria-label="Breadcrumb">';
    echo '<ul class="breadcrumb-list">';

    foreach ($items as $index => $item) {
        $is_last = ($index === $total - 1);

        if ($is_last) {
            echo '<li class="breadcrumb-item active"><span aria-current="page">' . h($item['label']) . '</span></li>';
        } else {
            echo '<li class="breadcrumb-item">';
            echo '<a href="' . h($item['url']) . '">' . h($item['label']) . '</a>';
            echo '<span class="breadcrumb-separator">›</span>';
            echo '</li>';
        }
    }

    echo '</ul>';
    echo '</nav>';
}

/**
 * 輸出卡片容器
 *
 * @param string $title 卡片標題
 * @param array $actions 操作按鈕陣列
 */
function card_start($title = '', $actions = []) {
    echo '<div class="card">';

    if ($title || !empty($actions)) {
        echo '<div class="card-header">';

        if ($title) {
            echo '<h2 class="card-title">' . h($title) . '</h2>';
        }

        if (!empty($actions)) {
            echo '<div class="card-actions">';
            foreach ($actions as $action) {
                $class = $action['class'] ?? 'btn-primary';
                $label = $action['label'] ?? '';
                $url = $action['url'] ?? '';
                $onclick = $action['onclick'] ?? '';
                $element = $url ? 'a' : 'button';
                $attrs = [
                    'class' => 'btn ' . $class,
                ];
                if ($url) {
                    $attrs['href'] = $url;
                } else {
                    $attrs['type'] = $action['type'] ?? 'button';
                }
                if ($onclick) {
                    $attrs['onclick'] = $onclick;
                }

                echo '<' . $element;
                foreach ($attrs as $attr => $value) {
                    echo ' ' . h($attr) . '="' . h($value) . '"';
                }
                echo '>' . h($label) . '</' . $element . '>';
            }
            echo '</div>';
        }

        echo '</div>';
    }

    echo '<div class="card-body">';
}

/**
 * 輸出卡片結束
 */
function card_end() {
    echo '</div>'; // card-body
    echo '</div>'; // card
}

/**
 * 輸出表單開始
 *
 * @param string $action 表單提交地址
 * @param string $method 表單方法，預設POST
 * @param array $extra_attributes 額外屬性
 */
function form_start($action = '', $method = 'POST', $extra_attributes = []) {
    echo '<form';
    if ($action) {
        echo ' action="' . h($action) . '"';
    }
    echo ' method="' . h($method) . '"';

    foreach ($extra_attributes as $attr => $value) {
        echo ' ' . h($attr) . '="' . h($value) . '"';
    }

    echo '>';

    // 自動新增CSRF令牌
    echo csrf_input();
}

/**
 * 輸出表單結束
 */
function form_end() {
    echo '</form>';
}

/**
 * 輸出表單欄位
 *
 * @param string $name 欄位名
 * @param string $label 欄位標籤
 * @param string $type 欄位型別，預設text
 * @param array $options 選項（select型別用）
 * @param array $attributes 額外屬性
 */
function form_field($name, $label, $type = 'text', $options = [], $attributes = []) {
    $id = $attributes['id'] ?? 'field_' . $name;
    $value = $attributes['value'] ?? '';
    if ($value === '' && isset($attributes['selected'])) {
        $value = $attributes['selected'];
    }
    $placeholder = $attributes['placeholder'] ?? '';
    $required = isset($attributes['required']) && $attributes['required'];
    $error = $attributes['error'] ?? '';
    $help = $attributes['help'] ?? '';

    echo '<div class="form-group' . ($error ? ' has-error' : '') . '">';

    // 標籤
    if ($label !== '') {
        echo '<label for="' . h($id) . '" class="form-label">';
        echo h($label);
        if ($required) {
            echo ' <span class="required-mark">*</span>';
        }
        echo '</label>';
    }

    // 輸入欄位
    switch ($type) {
        case 'textarea':
            echo '<textarea';
            echo ' id="' . h($id) . '"';
            echo ' name="' . h($name) . '"';
            echo ' class="form-control"';
            if ($placeholder) {
                echo ' placeholder="' . h($placeholder) . '"';
            }
            if ($required) {
                echo ' required';
            }
            $rows = $attributes['rows'] ?? 4;
            echo ' rows="' . $rows . '"';
            echo '>' . h($value) . '</textarea>';
            break;

        case 'select':
            echo '<select';
            echo ' id="' . h($id) . '"';
            echo ' name="' . h($name) . '"';
            echo ' class="form-control"';
            if ($required) {
                echo ' required';
            }
            echo '>';

            foreach ($options as $opt_value => $opt_label) {
                $selected = ($value == $opt_value) ? ' selected' : '';
                echo '<option value="' . h($opt_value) . '"' . $selected . '>';
                echo h($opt_label);
                echo '</option>';
            }

            echo '</select>';
            break;

        default:
            echo '<input';
            echo ' type="' . h($type) . '"';
            echo ' id="' . h($id) . '"';
            echo ' name="' . h($name) . '"';
            echo ' value="' . h($value) . '"';
            echo ' class="form-control"';
            if ($placeholder) {
                echo ' placeholder="' . h($placeholder) . '"';
            }
            if ($required) {
                echo ' required';
            }
            if (!empty($attributes['readonly'])) {
                echo ' readonly';
            }
            echo '>';
            break;
    }

    // 錯誤資訊
    if ($error) {
        echo '<div class="error-message">' . h($error) . '</div>';
    }

    if ($help) {
        echo '<p class="form-help">' . h($help) . '</p>';
    }

    echo '</div>';
}

/**
 * 輸出三級分類選擇器
 *
 * @param string $type 分類型別（product/service）
 * @param array $category_tree 分類樹結構
 * @param array $category_map 分類字典
 * @param int|null $selected_id 當前選中分類ID
 * @param array $options 額外配置（input_name, id_prefix, manage_url, manage_label, help_text, empty_text）
 */
function render_category_selector($type, array $category_tree, array $category_map, $selected_id = null, array $options = []) {
    $input_name = $options['input_name'] ?? 'category_id';
    if (isset($options['id_prefix'])) {
        $id_prefix = $options['id_prefix'];
    } else {
        try {
            $id_prefix = 'category_' . bin2hex(random_bytes(4));
        } catch (Exception $e) {
            $id_prefix = 'category_' . uniqid();
        }
    }
    $manage_url = $options['manage_url'] ?? '/categories/index.php?type=' . $type;
    $manage_label = $options['manage_label'] ?? '分類管理';
    $help_text = $options['help_text'] ?? '分類最多三級，可在分類管理中維護。';
    $empty_text = $options['empty_text'] ?? '未選擇分類';

    if (empty($category_tree)) {
        echo '<input type="hidden" name="' . h($input_name) . '" value="">';
        echo '<div class="alert alert-warning" style="margin-top: 8px;">';
        echo '<span class="alert-message">尚未設定分類。<a href="' . h($manage_url) . '">前往分類管理</a></span>';
        echo '</div>';
        return;
    }

    $level1_id = $id_prefix . '_level1';
    $level2_id = $id_prefix . '_level2';
    $level3_id = $id_prefix . '_level3';
    $input_id = $id_prefix . '_input';
    $path_id = $id_prefix . '_path';

    $selected_id = intval($selected_id);
    if ($selected_id <= 0) {
        $selected_id = null;
    }

    $path_ids = $selected_id ? get_catalog_category_path_ids($selected_id) : [];
    $path_text = $selected_id ? get_catalog_category_path($selected_id) : $empty_text;

    echo '<div class="category-select-row">';
    echo '<select id="' . h($level1_id) . '" class="form-select"><option value="">選擇一級分類</option></select>';
    echo '<select id="' . h($level2_id) . '" class="form-select" disabled><option value="">選擇二級分類</option></select>';
    echo '<select id="' . h($level3_id) . '" class="form-select" disabled><option value="">選擇三級分類</option></select>';
    echo '</div>';

    echo '<input type="hidden" name="' . h($input_name) . '" id="' . h($input_id) . '" value="' . ($selected_id ? h($selected_id) : '') . '">';
    echo '<div id="' . h($path_id) . '" class="category-selected-path">' . h($path_text ?: $empty_text) . '</div>';

    if ($help_text || $manage_url) {
        echo '<p class="form-help">';
        if ($help_text) {
            echo h($help_text);
        }
        if ($manage_url) {
            echo ' <a href="' . h($manage_url) . '">' . h($manage_label) . '</a>';
        }
        echo '</p>';
    }

    $tree_json = json_encode(array_values($category_tree), JSON_UNESCAPED_UNICODE);
    $map_json = json_encode($category_map, JSON_UNESCAPED_UNICODE);
    $initial_json = json_encode(array_values(array_filter($path_ids)), JSON_UNESCAPED_UNICODE);
    $empty_text_js = json_encode($empty_text, JSON_UNESCAPED_UNICODE);

    echo '<script>(function(){';
    echo 'const tree = ' . $tree_json . ';';
    echo 'const map = ' . $map_json . ';';
    echo 'const initial = ' . $initial_json . ';';
    echo 'const level1 = document.getElementById("' . $level1_id . '");';
    echo 'const level2 = document.getElementById("' . $level2_id . '");';
    echo 'const level3 = document.getElementById("' . $level3_id . '");';
    echo 'const hidden = document.getElementById("' . $input_id . '");';
    echo 'const pathEl = document.getElementById("' . $path_id . '");';
    echo 'if (!level1 || !hidden) return;';
    echo 'const childrenMap = {};';
    echo 'const buildMap = function(nodes){(nodes||[]).forEach(function(node){childrenMap[node.id] = node.children || []; if (node.children && node.children.length){buildMap(node.children);}});};';
    echo 'buildMap(tree);';
    echo 'const placeholders = {1:"選擇一級分類",2:"選擇二級分類",3:"選擇三級分類"};';
    echo 'const populate = function(select, nodes, level){if(!select)return; select.innerHTML=""; const opt=document.createElement("option"); opt.value=""; opt.textContent=placeholders[level]||"請選擇"; select.appendChild(opt); (nodes||[]).forEach(function(node){const option=document.createElement("option"); option.value=node.id; option.textContent=node.name; select.appendChild(option);}); select.disabled = !(nodes && nodes.length);};';
    echo 'const getChildren = function(id){ if(!id){ return tree; } return childrenMap[id] || []; };';
    echo 'const buildPath = function(id){ const names=[]; let current = map[id]; while(current){ names.unshift(current.name); if(!current.parent_id) break; current = map[current.parent_id]; } return names.join(" / "); };';
    echo 'const updateHidden = function(){ let selected=""; if (level3 && level3.value) { selected = level3.value; } else if (level2 && level2.value) { selected = level2.value; } else if (level1 && level1.value) { selected = level1.value; } hidden.value = selected; if (pathEl) { pathEl.textContent = selected ? buildPath(selected) : ' . $empty_text_js . '; } };';
    echo 'if (level1) level1.addEventListener("change", function(){ populate(level2, getChildren(level1.value), 2); populate(level3, [], 3); updateHidden(); });';
    echo 'if (level2) level2.addEventListener("change", function(){ populate(level3, getChildren(level2.value), 3); updateHidden(); });';
    echo 'if (level3) level3.addEventListener("change", updateHidden);';
    echo 'populate(level1, tree, 1); populate(level2, [], 2); populate(level3, [], 3);';
    echo 'if (Array.isArray(initial) && initial.length) { if (initial[0]) { level1.value = initial[0]; if (typeof Event === "function") level1.dispatchEvent(new Event("change")); } if (initial[1]) { level2.value = initial[1]; if (typeof Event === "function") level2.dispatchEvent(new Event("change")); } if (initial[2]) { level3.value = initial[2]; if (typeof Event === "function") level3.dispatchEvent(new Event("change")); } updateHidden(); } else { updateHidden(); }';
    echo '})();</script>';
}

/**
 * 輸出按鈕
 *
 * @param string $label 按鈕文字
 * @param string $type 按鈕型別，預設button
 * @param array $attributes 額外屬性
 */
function button($label, $type = 'button', $attributes = []) {
    $class = $attributes['class'] ?? 'btn-primary';
    $onclick = $attributes['onclick'] ?? '';
    $disabled = isset($attributes['disabled']) && $attributes['disabled'];

    echo '<button';
    echo ' type="' . h($type) . '"';
    echo ' class="btn ' . h($class) . '"';
    if ($onclick) {
        echo ' onclick="' . h($onclick) . '"';
    }
    if ($disabled) {
        echo ' disabled';
    }
    echo '>' . h($label) . '</button>';
}

/**
 * 輸出底部Tab導航（iOS風格）
 *
 * @遵循憲法原則V: iOS風格使用者體驗
 */
function bottom_tab_navigation() {
    // 列印頁面隱藏導航
    if (is_print_page()) {
        return;
    }

    $current_page = get_current_page();

    echo '<nav class="bottom-tabs">';
    echo '<a href="/quotes/" class="tab-item';
    if ($current_page === 'quotes') echo ' active';
    echo '">';
    echo '<svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />';
    echo '</svg>';
    echo '<span class="tab-label">報價</span>';
    echo '</a>';

    echo '<a href="/products/" class="tab-item';
    if ($current_page === 'products') echo ' active';
    echo '">';
    echo '<svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />';
    echo '</svg>';
    echo '<span class="tab-label">產品</span>';
    echo '</a>';

    echo '<a href="/services/" class="tab-item';
    if ($current_page === 'services') echo ' active';
    echo '">';
    echo '<svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />';
    echo '</svg>';
    echo '<span class="tab-label">服務</span>';
    echo '</a>';

    echo '<a href="/customers/" class="tab-item';
    if ($current_page === 'customers') echo ' active';
    echo '">';
    echo '<svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />';
    echo '</svg>';
    echo '<span class="tab-label">客戶</span>';
    echo '</a>';

    echo '<a href="/settings/" class="tab-item';
    if ($current_page === 'settings') echo ' active';
    echo '">';
    echo '<svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
    echo '</svg>';
    echo '<span class="tab-label">設定</span>';
    echo '</a>';

    echo '</nav>';
}

/**
 * 輸出安全區域適配的底部導航
 *
 * @遵循憲法原則V: iOS風格使用者體驗 - Safe-Area適配
 */
function safe_area_bottom_navigation() {
    bottom_tab_navigation();
}

/**
 * 輸出警告提示
 *
 * @param string $message 訊息內容
 * @param string $type 訊息型別（success, error, warning, info）
 */
function alert($message, $type = 'info') {
    $class_map = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];

    $class = $class_map[$type] ?? 'alert-info';

    echo '<div class="alert ' . $class . '">';
    echo '<span class="alert-message">' . h($message) . '</span>';
    echo '<button type="button" class="alert-close" onclick="this.parentElement.style.display=\'none\'">&times;</button>';
    echo '</div>';
}

/**
 * 輸出載入指示器
 */
function loading_indicator() {
    echo '<div class="loading-indicator">';
    echo '<div class="spinner"></div>';
    echo '<span>載入中...</span>';
    echo '</div>';
}

/**
 * 輸出空狀態
 *
 * @param string $message 空狀態訊息
 * @param string $action_text 操作按鈕文字
 * @param string $action_url 操作按鈕連結
 */
function empty_state($message, $action_text = '', $action_url = '') {
    echo '<div class="empty-state">';
    echo '<div class="empty-icon">📄</div>';
    echo '<p class="empty-message">' . h($message) . '</p>';
    if ($action_text && $action_url) {
        echo '<a href="' . h($action_url) . '" class="btn btn-primary">' . h($action_text) . '</a>';
    }
    echo '</div>';
}

/**
 * 輸出確認對話方塊HTML和JS
 *
 * @param string $message 確認訊息
 * @param string $confirm_text 確認按鈕文字
 * @param string $cancel_text 取消按鈕文字
 */
function confirm_dialog($message, $confirm_text = '確認', $cancel_text = '取消') {
    echo '<div id="confirmDialog" class="modal" style="display: none;">';
    echo '<div class="modal-content">';
    echo '<h3>確認操作</h3>';
    echo '<p>' . h($message) . '</p>';
    echo '<div class="modal-actions">';
    echo '<button type="button" class="btn btn-secondary" onclick="closeConfirmDialog()">' . h($cancel_text) . '</button>';
    echo '<button type="button" class="btn btn-danger" id="confirmButton">' . h($confirm_text) . '</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<script>';
    echo 'function showConfirmDialog(onConfirm) {';
    echo '  var dialog = document.getElementById("confirmDialog");';
    echo '  var button = document.getElementById("confirmButton");';
    echo '  button.onclick = function() { onConfirm(); closeConfirmDialog(); };';
    echo '  dialog.style.display = "flex";';
    echo '}';
    echo 'function closeConfirmDialog() {';
    echo '  document.getElementById("confirmDialog").style.display = "none";';
    echo '}';
    echo '</script>';
}

/**
 * 檢查使用者是否已登入（用於顯示/隱藏導航）
 *
 * @return bool
 */
function user_is_logged_in() {
    return is_logged_in();
}

/**
 * 獲取當前使用者顯示名稱
 *
 * @return string
 */
function get_current_user_display_name() {
    return $_SESSION['user_name'] ?? '使用者';
}

?>
