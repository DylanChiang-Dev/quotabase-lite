<?php
/**
 * 首页重定向
 * Index Page - Redirect to Quotes List
 *
 * @version v2.0.0
 * @description 系统首页，重定向到报价列表
 */

// 防止直接访问（如果需要）
define('QUOTABASE_SYSTEM', true);

// 加载配置
require_once __DIR__ . '/config.php';

// 系統尚未初始化則自動導向初始化精靈
redirect_to_init_if_needed();

// 检查是否已登录
if (!is_logged_in()) {
    // 未登录，重定向到登录页
    header('Location: /login.php');
    exit;
}

// 已登录，重定向到报价列表（首页）
header('Location: /quotes/');
exit;
