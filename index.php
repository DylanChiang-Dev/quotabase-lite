<?php
/**
 * 首頁重定向
 * Index Page - Redirect to Quotes List
 *
 * @version v2.0.0
 * @description 系統首頁，重定向到報價列表
 */

// 防止直接訪問（如果需要）
define('QUOTABASE_SYSTEM', true);

// 載入配置
require_once __DIR__ . '/config.php';

// 系統尚未初始化則自動導向初始化精靈
redirect_to_init_if_needed();

// 檢查是否已登入
if (!is_logged_in()) {
    // 未登入，重定向到登入頁
    header('Location: /login.php');
    exit;
}

// 已登入，重定向到報價列表（首頁）
header('Location: /quotes/');
exit;
