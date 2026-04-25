<?php
/**
 * YTBlog Theme
 *
 * @package YTBlog Theme
 * @author Codex
 * @version 1.0.0
 * @link https://openai.com
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

// 模块:shortcode / github / 加密 全部在 inc/ 下
require_once __DIR__ . '/inc/shortcodes.php';
require_once __DIR__ . '/inc/github.php';
require_once __DIR__ . '/inc/lock.php';

function themeInit($archive)
{
    $options = Typecho_Widget::widget('Widget_Options');

    // 加密分派:解锁 POST → 全站 → 分类(单文章/分类页) → 页面
    fluxgrid_lock_handle_unlock_post($options);
    fluxgrid_lock_guard_site($options, $archive);
    fluxgrid_lock_guard_category($options, $archive);
    fluxgrid_lock_guard_page($options, $archive);

    if ($archive->is('index') || $archive->is('archive')) {
        $pageSize = 9;
        if ($archive->is('index') && fluxgrid_archive_current_page($archive) === 1) {
            $pageSize -= fluxgrid_sticky_count($archive);
        }

        $archive->parameter->pageSize = max(1, $pageSize);
    }

    // 钩子不可靠，保留但主路径改走模板里 fluxgrid_render_content()
    if (class_exists('Typecho_Plugin')) {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = 'fluxgrid_shortcode_filter';
    }
}

function fluxgrid_option($options, $name, $default = '')
{
    if (!is_object($options)) {
        return $default;
    }

    if (isset($options->$name)) {
        $value = trim((string) $options->$name);
        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}

function fluxgrid_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fluxgrid_widget_output($widget, $method)
{
    $args = func_get_args();
    $widget = array_shift($args);
    $method = array_shift($args);

    if (!is_object($widget) || !method_exists($widget, $method)) {
        return '';
    }

    ob_start();
    call_user_func_array(array($widget, $method), $args);
    return trim(ob_get_clean());
}

function fluxgrid_string_value($value)
{
    if (is_scalar($value)) {
        return trim((string) $value);
    }

    if (is_object($value) && method_exists($value, '__toString')) {
        return trim((string) $value);
    }

    return '';
}

function fluxgrid_widget_text($widget, $method, $property = null)
{
    $value = fluxgrid_widget_output($widget, $method);
    if ($value !== '') {
        return $value;
    }

    if ($property !== null && is_object($widget) && isset($widget->$property)) {
        return fluxgrid_string_value($widget->$property);
    }

    return '';
}

function fluxgrid_asset_url($options, $asset)
{
    $options = fluxgrid_resolve_options($options);
    if (!is_object($options) || !method_exists($options, 'themeUrl')) {
        return ltrim($asset, '/');
    }

    ob_start();
    $options->themeUrl($asset);
    $url = trim(ob_get_clean());
    $path = __DIR__ . '/' . ltrim($asset, '/');

    if (is_file($path)) {
        $url .= '?v=' . filemtime($path);
    }

    return $url;
}

function fluxgrid_archive_current_page($archive)
{
    if (is_object($archive) && isset($archive->_currentPage)) {
        return max(1, (int) $archive->_currentPage);
    }

    if (is_object($archive) && isset($archive->currentPage)) {
        return max(1, (int) $archive->currentPage);
    }

    return 1;
}

function fluxgrid_resolve_options($context = null)
{
    if (is_object($context) && isset($context->options) && is_object($context->options)) {
        return $context->options;
    }

    if (is_object($context)) {
        return $context;
    }

    if (class_exists('Utils\\Helper') && method_exists('Utils\\Helper', 'options')) {
        return \Utils\Helper::options();
    }

    if (class_exists('Helper') && method_exists('Helper', 'options')) {
        return \Helper::options();
    }

    return null;
}

function fluxgrid_sticky_count($context = null)
{
    $options = fluxgrid_resolve_options($context);
    if (!is_object($options) || !method_exists($options, 'plugin')) {
        return 0;
    }

    $stickyOptions = null;
    foreach (array('Sticky', 'sticky') as $pluginName) {
        $candidate = $options->plugin($pluginName);
        if (is_object($candidate)) {
            $stickyOptions = $candidate;
            break;
        }
    }

    if (!is_object($stickyOptions)) {
        return 0;
    }

    $stickyCids = '';
    foreach (array('cid', 'cids', 'sticky', 'stickyIds', 'stickyCid', 'stickyCids') as $field) {
        if (isset($stickyOptions->$field)) {
            $stickyCids = fluxgrid_string_value($stickyOptions->$field);
            if ($stickyCids !== '') {
                break;
            }
        }
    }

    if ($stickyCids === '') {
        return 0;
    }

    $items = preg_split('/[\s,\|]+/', $stickyCids);
    $items = array_filter($items, function ($item) {
        return $item !== '' && ctype_digit((string) $item);
    });

    return count($items);
}

function fluxgrid_normalize_url_like_value($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (strpos($value, '//') === 0) {
        return $value;
    }

    if ($value[0] === '/' || strpos($value, './') === 0 || strpos($value, '../') === 0) {
        return $value;
    }

    $parts = @parse_url($value);
    if (is_array($parts) && isset($parts['scheme'])) {
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme === 'http' || $scheme === 'https') {
            return $value;
        }

        if ($scheme === 'data' && strpos(strtolower($value), 'data:image/') === 0) {
            return $value;
        }

        return '';
    }

    return preg_match('/^[A-Za-z0-9_\\.\\-\\/~%\\?#=&:+,]+$/', $value) ? $value : '';
}

function fluxgrid_js_string($value)
{
    return str_replace(
        array('\\', "'", "\r", "\n"),
        array('\\\\', "\\'", '', ''),
        (string) $value
    );
}

function fluxgrid_debug_enabled($options = null)
{
    return isset($_GET['fluxgrid_debug']) && (string) $_GET['fluxgrid_debug'] === '1';
}

function fluxgrid_debug_value($value, $limit = 220)
{
    if (is_array($value) || is_object($value)) {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $value = $json === false ? '' : $json;
    }

    $value = preg_replace('/\s+/u', ' ', fluxgrid_string_value($value));
    $value = str_replace(array('--', '<', '>'), array('==', '[', ']'), $value);

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value, 'UTF-8') > $limit) {
            return mb_substr($value, 0, $limit - 3, 'UTF-8') . '...';
        }
        return $value;
    }

    if (strlen($value) > $limit) {
        return substr($value, 0, $limit - 3) . '...';
    }

    return $value;
}

function fluxgrid_cover_debug_comment($archive, $location = '')
{
    $options = is_object($archive) && isset($archive->options) ? $archive->options : null;
    if (!fluxgrid_debug_enabled($options)) {
        return '';
    }

    $rawCover = fluxgrid_post_field($archive, 'cover');
    $finalCover = fluxgrid_post_cover($archive);
    $title = trim(strip_tags(fluxgrid_widget_text($archive, 'title', 'title')));
    $cid = isset($archive->cid) ? (string) $archive->cid : '';

    $parts = array(
        'location=' . fluxgrid_debug_value($location, 40),
        'cid=' . fluxgrid_debug_value($cid, 20),
        'title=' . fluxgrid_debug_value($title, 120),
        'raw_cover=' . fluxgrid_debug_value($rawCover, 220),
        'sanitized_cover=' . fluxgrid_debug_value(fluxgrid_sanitize_image_source($rawCover), 220),
        'final_cover=' . fluxgrid_debug_value($finalCover, 220),
    );

    $message = '[YTBlog] ' . implode(' | ', $parts);
    error_log($message);

    return "\n<!-- " . fluxgrid_debug_value($message, 1200) . " -->\n";
}

function fluxgrid_image_fallback_script($options)
{
    $options = fluxgrid_resolve_options($options);
    $fallback = fluxgrid_asset_url($options, 'assets/images/hero-tech.svg');
    return "this.onerror=null;this.src='" . fluxgrid_js_string($fallback) . "';";
}

function fluxgrid_archive_heading($archive)
{
    if ($archive->is('category')) {
        ob_start();
        $archive->archiveTitle(array('category' => _t('%s')), '', '');
        return trim(strip_tags(ob_get_clean()));
    }

    if ($archive->is('tag')) {
        ob_start();
        $archive->archiveTitle(array('tag' => _t('%s')), '', '');
        return '#' . trim(strip_tags(ob_get_clean()));
    }

    if ($archive->is('search')) {
        ob_start();
        $archive->archiveTitle(array('search' => _t('%s')), '', '');
        $keyword = trim(strip_tags(ob_get_clean()));
        return $keyword === '' ? _t('搜索结果') : _t('搜索：%s', $keyword);
    }

    if ($archive->is('author')) {
        ob_start();
        $archive->archiveTitle(array('author' => _t('%s')), '', '');
        $author = trim(strip_tags(ob_get_clean()));
        return $author === '' ? _t('作者文章') : _t('%s 的文章', $author);
    }

    if ($archive->is('date')) {
        return _t('时间归档');
    }

    return _t('内容归档');
}

function fluxgrid_fallback_image($options, $seed = 0)
{
    $options = fluxgrid_resolve_options($options);
    $raw = fluxgrid_option($options, 'fallbackImage', '');

    if ($raw === '') {
        $raw = 'https://picsum.photos/seed/{seed}/1200/800';
    }

    if (strpos($raw, '{seed}') !== false) {
        $seedValue = $seed > 0 ? (string) $seed : (string) mt_rand(1000, 99999);
        $raw = str_replace('{seed}', $seedValue, $raw);
    }

    $configured = fluxgrid_sanitize_image_source($raw);
    if ($configured !== '') {
        return $configured;
    }

    return fluxgrid_asset_url($options, 'assets/images/hero-tech.svg');
}

function fluxgrid_sanitize_image_source($value)
{
    $value = fluxgrid_string_value($value);
    for ($i = 0; $i < 3; $i++) {
        $decoded = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        if ($decoded === $value) {
            break;
        }
        $value = $decoded;
    }

    if ($value === '') {
        return '';
    }

    if (preg_match('/(?:&lt;|&gt;|&#0*60;|&#x0*3c;|&#0*62;|&#x0*3e;)/i', $value)) {
        return '';
    }

    if (preg_match('/[<>"\'\r\n]/', $value)) {
        return '';
    }

    if (preg_match('/(?:<!doctype|<html|<body|<head|server error)/i', $value)) {
        return '';
    }

    if (preg_match('/^\s*(?:javascript|vbscript|data):(?!image\/)/i', $value)) {
        return '';
    }

    return fluxgrid_normalize_url_like_value($value);
}

function fluxgrid_safe_image_url($value, $options = null)
{
    $image = fluxgrid_sanitize_image_source($value);
    if ($image !== '') {
        return $image;
    }

    return fluxgrid_fallback_image($options);
}

function fluxgrid_post_field($archive, $name)
{
    if (!is_object($archive) || !isset($archive->fields) || !is_object($archive->fields) || !isset($archive->fields->$name)) {
        return '';
    }

    return fluxgrid_string_value($archive->fields->$name);
}

function fluxgrid_post_text($archive)
{
    if (!is_object($archive)) {
        return '';
    }

    foreach (array('text', 'content') as $prop) {
        $value = null;
        try {
            $value = @$archive->$prop;
        } catch (Throwable $e) {
            $value = null;
        } catch (Exception $e) {
            $value = null;
        }
        if (is_string($value) && $value !== '') {
            return fluxgrid_string_value($value);
        }
    }

    if (isset($archive->row) && is_array($archive->row)) {
        foreach (array('text', 'content') as $key) {
            if (isset($archive->row[$key]) && is_string($archive->row[$key]) && $archive->row[$key] !== '') {
                return fluxgrid_string_value($archive->row[$key]);
            }
        }
    }

    return '';
}

function fluxgrid_first_image($content)
{
    if ($content === '') {
        return '';
    }

    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
        return fluxgrid_sanitize_image_source($matches[1]);
    }

    if (preg_match('/!\[[^\]]*\]\((?:<)?([^)\s>]+)(?:>)?(?:\s+["\'][^"\']*["\'])?\)/u', $content, $matches)) {
        return fluxgrid_sanitize_image_source($matches[1]);
    }

    return '';
}

function fluxgrid_post_image($archive)
{
    $cover = fluxgrid_sanitize_image_source(fluxgrid_post_field($archive, 'cover'));
    if ($cover !== '') {
        return $cover;
    }

    $firstImage = fluxgrid_first_image(fluxgrid_post_text($archive));
    if ($firstImage !== '') {
        return $firstImage;
    }

    return '';
}

function fluxgrid_post_cover($archive)
{
    $image = fluxgrid_sanitize_image_source(fluxgrid_post_image($archive));
    if ($image !== '') {
        return $image;
    }

    $options = fluxgrid_resolve_options($archive);
    $cid = isset($archive->cid) ? (int) $archive->cid : 0;
    return fluxgrid_fallback_image($options, $cid);
}

function fluxgrid_excerpt($archive, $length = 140)
{
    $summary = fluxgrid_post_field($archive, 'summary');
    if ($summary !== '') {
        return preg_replace('/\s+/u', ' ', $summary);
    }

    if (is_object($archive) && method_exists($archive, 'excerpt')) {
        ob_start();
        @$archive->excerpt($length, '...');
        $excerpt = trim(strip_tags(ob_get_clean()));
        $excerpt = preg_replace('/\s+/u', ' ', $excerpt);
        if ($excerpt !== '') {
            return $excerpt;
        }
    }

    $text = fluxgrid_post_text($archive);
    if ($text === '') {
        return '';
    }

    $plain = preg_replace('/<!--.*?-->/us', '', $text);
    $plain = preg_replace('/```[\s\S]*?```/u', ' ', $plain);
    $plain = preg_replace('/`[^`]*`/u', ' ', $plain);
    $plain = strip_tags($plain);
    $plain = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', $plain);
    $plain = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $plain);
    $plain = preg_replace('/\[\/?[A-Za-z][^\]]*\]/u', '', $plain);
    $plain = preg_replace('/\b[A-Za-z-]+="[^"]*"/u', '', $plain);
    $plain = preg_replace("/\b[A-Za-z-]+='[^']*'/u", '', $plain);
    $plain = preg_replace('/[-=*]{3,}/u', ' ', $plain);
    $plain = preg_replace('/[`*_~>#]+/u', ' ', $plain);
    $plain = trim(preg_replace('/\s+/u', ' ', $plain));

    if ($plain === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($plain, 'UTF-8') > $length) {
            return mb_substr($plain, 0, $length, 'UTF-8') . '...';
        }
        return $plain;
    }

    if (strlen($plain) > $length) {
        return substr($plain, 0, $length) . '...';
    }
    return $plain;
}

function fluxgrid_badge($archive)
{
    $badge = fluxgrid_post_field($archive, 'badge');
    if ($badge !== '') {
        return $badge;
    }

    if (isset($archive->sticky) && $archive->sticky) {
        return 'PINNED';
    }

    return 'LATEST';
}

function themeConfig($form)
{
    $options  = Typecho_Widget::widget('Widget_Options');
    $themeUrl = rtrim($options->themeUrl, '/');

    /* ═══════════════════════════════════════════════════════════
     * 顶部 Banner + 全局 CSS 注入
     * Typecho 把 label 渲染为 <label>，把 description 渲染为 <p class="typecho-help">
     * 把 <style> 放在 description 里即可在 body 中注入样式（现代浏览器均支持）
     * input[id^="__fg_"] 选择器统一隐藏所有占位控件的 input 本体
     * ══════════════════════════════════════════════════════════ */
    $hd = new Typecho_Widget_Helper_Form_Element_Text(
        '__fg_hd', null, '',
        /* label ↓ */
        '<div class="fg-banner">'
        . '<div class="fg-banner-thumb">'
        .   '<img src="' . htmlspecialchars($themeUrl, ENT_QUOTES) . '/screenshot.png"'
        .        ' alt="YTBlog Theme Preview"'
        .        ' onerror="this.src=\'' . htmlspecialchars($themeUrl, ENT_QUOTES) . '/screenshot.svg\'">'
        . '</div>'
        . '<div class="fg-banner-info">'
        .   '<h2>YTBlog Theme <span class="fg-v">v1.0</span></h2>'
        .   '<p>现代风格 Typecho 博客主题 · 粒子背景 · 代码窗口 Hero · 短代码系统 · 多层内容加密</p>'
        . '</div>'
        . '</div>',
        /* description ↓ — 用于注入 CSS */
        '<style>
/* ── YTBlog Theme admin settings panel ── */

/* 隐藏所有占位 input 本体，保留 label（即分组标题）可见 */
input[id^="__fg_"] {
  position: absolute !important;
  width: 1px !important; height: 1px !important;
  opacity: 0 !important; pointer-events: none !important;
}

/* Banner */
.fg-banner {
  display: flex; align-items: center; gap: 18px;
  padding: 16px 20px;
  background: linear-gradient(135deg, #0d1b36 0%, #1a3a6e 60%, #1e3a5f 100%);
  border-radius: 10px; margin-bottom: 4px;
}
.fg-banner-thumb {
  flex-shrink: 0; width: 200px; height: 70px;
  border-radius: 8px; overflow: hidden;
  background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
  display: flex; align-items: center; justify-content: center;
}
.fg-banner-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.fg-banner-info h2 {
  margin: 0 0 5px; font-size: 19px; font-weight: 700; color: #f0f4ff;
  line-height: 1.2; padding: 0; border: none; background: none;
}
.fg-banner-info p { margin: 0; color: rgba(255,255,255,.55); font-size: 13px; line-height: 1.5; }
.fg-v {
  display: inline-block;
  background: rgba(59,130,246,.25); border: 1px solid rgba(59,130,246,.4);
  color: #93c5fd; font-size: 11px; padding: 1px 7px; border-radius: 20px;
  margin-left: 5px; font-family: monospace; font-weight: normal; vertical-align: middle;
}

/* Section headings */
.fg-sec {
  display: flex; align-items: center; gap: 8px;
  font-size: 13px; font-weight: 600; color: #1d4ed8;
  padding: 6px 10px; margin: 0;
  border-left: 3px solid #2563eb;
  background: linear-gradient(90deg, rgba(37,99,235,.08) 0%, transparent 100%);
  border-radius: 0 6px 6px 0;
}
.fg-sec svg { flex-shrink: 0; }

/* Tweak the <li> rows that hold section headings */
li:has(> label > .fg-sec) {
  padding-top: 24px !important;
  padding-bottom: 2px !important;
  border-bottom: none !important;
}
li:has(> label > .fg-banner) {
  padding: 0 !important;
  border-bottom: none !important;
}
/* Fallback for browsers without :has() — section inputs just vanish silently */

/* Input polish */
.typecho-option input.text { border-radius: 7px; }
.typecho-option input.text:focus { box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
</style>'
    );

    /* ═══ 1 基础设置 ═══════════════════════════════════════════ */
    $s1 = new Typecho_Widget_Helper_Form_Element_Text('__fg_s1', null, '',
        '<div class="fg-sec">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>'
        . '基础设置</div>', ''
    );

    $logoText = new Typecho_Widget_Helper_Form_Element_Text(
        'logoText', null, '',
        _t('Logo 文案'),
        _t('左上角品牌文字。留空则使用「站点标题」。')
    );
    $footerText = new Typecho_Widget_Helper_Form_Element_Textarea(
        'footerText', null, '',
        _t('页脚文案'),
        _t('页脚左侧副标题，留空则使用「站点描述」。支持多行。')
    );
    $fallbackImage = new Typecho_Widget_Helper_Form_Element_Text(
        'fallbackImage', null, '',
        _t('占位图 / 随机图 API'),
        _t('文章无封面时的兜底图。支持 {seed} 占位符（替换为文章 cid）。<br>示例：<code>https://picsum.photos/seed/{seed}/1200/800</code>')
    );
    $faviconUrl = new Typecho_Widget_Helper_Form_Element_Text(
        'faviconUrl', null, '',
        _t('Favicon'),
        _t('标签页小图标。留空使用主题内置蓝色方块 SVG。支持 ICO / PNG / SVG 绝对地址。')
    );

    /* ═══ 2 个人卡片 ════════════════════════════════════════════ */
    $s2 = new Typecho_Widget_Helper_Form_Element_Text('__fg_s2', null, '',
        '<div class="fg-sec">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
        . '个人卡片</div>', ''
    );

    $avatarUrl = new Typecho_Widget_Helper_Form_Element_Text(
        'avatarUrl', null, '',
        _t('头像'),
        _t('侧边栏个人卡片头像图片地址，建议正方形。留空显示默认渐变色占位。')
    );
    $bannerUrl = new Typecho_Widget_Helper_Form_Element_Text(
        'bannerUrl', null, '',
        _t('卡片横幅图'),
        _t('个人卡片顶部横幅背景图地址。留空使用默认深蓝渐变。')
    );

    /* ═══ 3 内容加密 ════════════════════════════════════════════ */
    $s3 = new Typecho_Widget_Helper_Form_Element_Text('__fg_s3', null, '',
        '<div class="fg-sec">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
        . '内容加密</div>', ''
    );

    $sitePassword = new Typecho_Widget_Helper_Form_Element_Text(
        'sitePassword', null, '',
        _t('全站密码'),
        _t('非空则全站需密码才能访问（含 RSS）。慎用，适合私密日记站。')
    );
    $lockedCategories = new Typecho_Widget_Helper_Form_Element_Text(
        'lockedCategories', null, '',
        _t('加密分类 mid'),
        _t('多个用英文逗号分隔。对应分类的「描述」需填写 <code>{"lock":true,"password":"xxx"}</code>。')
    );
    $lockHideTitle = new Typecho_Widget_Helper_Form_Element_Radio(
        'lockHideTitle',
        array('0' => '显示标题', '1' => '隐藏标题'),
        '0',
        _t('加密文章在列表中的显示'),
        _t('首页 / 归档 / 分类列表中，加密文章的标题是否一并隐藏。')
    );

    /* ═══ 4 短代码 ══════════════════════════════════════════════ */
    $s4 = new Typecho_Widget_Helper_Form_Element_Text('__fg_s4', null, '',
        '<div class="fg-sec">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>'
        . '短代码</div>', ''
    );

    $loginHideText = new Typecho_Widget_Helper_Form_Element_Text(
        'loginHideText', null, '登录可见',
        _t('[login] 未登录占位文案'), ''
    );
    $replyHideText = new Typecho_Widget_Helper_Form_Element_Text(
        'replyHideText', null, '评论后可见(需审核通过)',
        _t('[reply] 未评论占位文案'), ''
    );

    /* ═══ 添加到 form ════════════════════════════════════════════ */
    $form->addInput($hd);

    $form->addInput($s1);
    $form->addInput($logoText);
    $form->addInput($footerText);
    $form->addInput($fallbackImage);
    $form->addInput($faviconUrl);

    $form->addInput($s2);
    $form->addInput($avatarUrl);
    $form->addInput($bannerUrl);

    $form->addInput($s3);
    $form->addInput($sitePassword);
    $form->addInput($lockedCategories);
    $form->addInput($lockHideTitle);

    $form->addInput($s4);
    $form->addInput($loginHideText);
    $form->addInput($replyHideText);
}

function themeFields($layout)
{
    $cover = new Typecho_Widget_Helper_Form_Element_Text(
        'cover',
        null,
        '',
        _t('封面图地址'),
        _t('文章卡片和详情页头图使用。建议 16:9 或更宽。')
    );

    $summary = new Typecho_Widget_Helper_Form_Element_Textarea(
        'summary',
        null,
        '',
        _t('摘要文案'),
        _t('用于首页卡片和详情页导语，留空则自动截取摘要。')
    );

    $badge = new Typecho_Widget_Helper_Form_Element_Text(
        'badge',
        null,
        '',
        _t('角标文案'),
        _t('如 PRODUCT、AI、ENGINEERING 等，显示在文章卡片左上角。')
    );

    $layout->addItem($cover);
    $layout->addItem($summary);
    $layout->addItem($badge);
}




