<?php
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }

function fluxgrid_shortcode_filter($text, $widget = null, $last = null)
{
    $source = ($last !== null && $last !== '') ? $last : $text;
    if (!is_string($source)) { return $source; }

    // 加密分类保护(主要用于 feed/RSS 等绕过模板的场景)
    if (is_object($widget) && isset($widget->cid)) {
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $catLock = fluxgrid_lock_row_category_info($widget);
            if ($catLock && !fluxgrid_lock_category_unlocked($catLock['mid'], $catLock['password'], $options)) {
                $title = isset($widget->title) ? (string) $widget->title : '';
                $link  = isset($widget->permalink) ? (string) $widget->permalink : '';
                return '<p>🔒 此内容属于加密分类,请前往 '
                    . '<a href="' . fluxgrid_escape($link) . '">' . fluxgrid_escape($title) . '</a> 输入密码查看。</p>';
            }
        } catch (Exception $e) {
            // 静默
        }
    }

    if (strpos($source, '[') === false) { return $source; }
    return fluxgrid_parse_shortcodes($source, $widget);
}

function fluxgrid_render_content($archive)
{
    if (!is_object($archive) || !method_exists($archive, 'content')) {
        return;
    }
    ob_start();
    $archive->content();
    $html = ob_get_clean();

    // Typecho 原生的单篇文章/页面密码表单,换成主题统一样式
    if (is_string($html)
        && strpos($html, 'name="protectPassword"') !== false
        && strpos($html, '<form') !== false) {
        echo fluxgrid_lock_render_inline_form('native', $archive);
        return;
    }

    echo fluxgrid_parse_shortcodes($html, $archive);
}
function fluxgrid_parse_shortcode_attrs($str)
{
    $attrs = array();
    if (!is_string($str) || $str === '') {
        return $attrs;
    }
    // Typecho 的 Markdown 会把裸 URL 自动转成 <a href="URL">URL</a>,
    // 先去掉 HTML 标签,还原出纯 URL 文本,避免 attrs 被污染。
    $str = strip_tags($str);
    // 顺便把 &quot; &#034; 这类实体解码回 " ,方便匹配引号
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    // 支持三种写法:key="value" / key='value' / key=value(值里无空格且不含 [ ])
    preg_match_all(
        '/([A-Za-z_][A-Za-z0-9_\-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\[\]]+))/u',
        $str, $matches, PREG_SET_ORDER
    );
    foreach ($matches as $match) {
        $value = '';
        if (isset($match[2]) && $match[2] !== '') {
            $value = $match[2];
        } elseif (isset($match[3]) && $match[3] !== '') {
            $value = $match[3];
        } elseif (isset($match[4]) && $match[4] !== '') {
            $value = $match[4];
        }
        $attrs[$match[1]] = $value;
    }
    return $attrs;
}

function fluxgrid_sc_enum($value, $allowed, $default)
{
    $value = strtolower((string) $value);
    return in_array($value, $allowed, true) ? $value : $default;
}

function fluxgrid_render_post_card($cid, $attrs = array())
{
    static $cache = array();
    $cid = (int) $cid;
    $size = isset($attrs['size']) && strtolower($attrs['size']) === 'small' ? 'small' : 'large';
    $cacheKey = $cid . ':' . $size;
    if (isset($cache[$cacheKey])) { return $cache[$cacheKey]; }

    if ($cid <= 0 || !class_exists('Typecho_Db')) {
        return $cache[$cacheKey] = '';
    }

    try {
        $options = null;
        if (class_exists('Typecho_Widget')) {
            $options = Typecho_Widget::widget('Widget_Options');
        }

        $db = Typecho_Db::get();
        $row = $db->fetchRow(
            $db->select()
                ->from('table.contents')
                ->where('cid = ?', $cid)
                ->where('status = ?', 'publish')
                ->where('type = ?', 'post')
                ->limit(1)
        );
        if (!$row) {
            return $cache[$cacheKey] = '';
        }

        $permalink = '';
        try {
            $row['date'] = new Typecho_Date((int) $row['created']);
            $row['year'] = $row['date']->year;
            $row['month'] = $row['date']->month;
            $row['day'] = $row['date']->day;
            $row['directory'] = array();
            $row['category'] = '';
            if (class_exists('Typecho_Router') && is_object($options)) {
                $permalink = Typecho_Router::url('post', $row, $options->index);
            }
        } catch (Exception $e) {}
        if ($permalink === '' && is_object($options) && isset($options->siteUrl)) {
            $permalink = rtrim($options->siteUrl, '/') . '/archives/' . $cid . '/';
        }

        $cover = '';
        if (isset($attrs['cover']) && $attrs['cover'] !== '') {
            $cover = fluxgrid_sanitize_image_source(str_replace('\\/', '/', $attrs['cover']));
        }
        if ($cover === '') {
            try {
                $fieldRow = $db->fetchRow(
                    $db->select('str_value')->from('table.fields')
                        ->where('cid = ?', $cid)
                        ->where('name = ?', 'cover')
                        ->limit(1)
                );
                if ($fieldRow && !empty($fieldRow['str_value'])) {
                    $cover = fluxgrid_sanitize_image_source($fieldRow['str_value']);
                }
            } catch (Exception $e) {}
        }
        if ($cover === '' && isset($row['text'])) {
            $cover = fluxgrid_first_image($row['text']);
        }
        if ($cover === '') {
            $cover = fluxgrid_fallback_image($options, $cid);
        }

        $excerpt = '';
        if ($size !== 'small' && isset($row['text'])) {
            $excerpt = preg_replace('/<!--.*?-->/us', '', $row['text']);
            $excerpt = preg_replace('/```[\s\S]*?```/u', ' ', $excerpt);
            $excerpt = strip_tags($excerpt);
            $excerpt = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', $excerpt);
            $excerpt = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $excerpt);
            $excerpt = preg_replace('/\[\/?[A-Za-z][^\]]*\]/u', '', $excerpt);
            $excerpt = preg_replace('/[`*_~>#]+/u', ' ', $excerpt);
            $excerpt = trim(preg_replace('/\s+/u', ' ', $excerpt));
            if (function_exists('mb_substr') && function_exists('mb_strlen') && mb_strlen($excerpt, 'UTF-8') > 84) {
                $excerpt = mb_substr($excerpt, 0, 84, 'UTF-8') . '...';
            }
        }

        $title = fluxgrid_escape($row['title']);
        $dateLabel = date('Y.m.d', (int) $row['created']);

        $html = '<a class="sc-post-card sc-post-card--' . $size . '" href="' . fluxgrid_escape($permalink) . '">';
        if ($size !== 'small') {
            $html .= '<span class="sc-post-card-media"><img src="' . fluxgrid_escape($cover) . '" alt="' . $title . '" loading="lazy"></span>';
        }
        $html .= '<span class="sc-post-card-body">';
        $html .= '<span class="sc-post-card-eyebrow">POST · ' . fluxgrid_escape($dateLabel) . '</span>';
        $html .= '<span class="sc-post-card-title">' . $title . '</span>';
        if ($excerpt !== '') {
            $html .= '<span class="sc-post-card-excerpt">' . fluxgrid_escape($excerpt) . '</span>';
        }
        $html .= '</span></a>';

        return $cache[$cacheKey] = $html;
    } catch (Exception $e) {
        return $cache[$cacheKey] = '';
    } catch (Throwable $e) {
        return $cache[$cacheKey] = '';
    }
}

function fluxgrid_render_external_post_card($attrs)
{
    $url = isset($attrs['url']) ? str_replace('\\/', '/', $attrs['url']) : '';
    $title = isset($attrs['title']) ? $attrs['title'] : '';
    if ($url === '' || $title === '') { return ''; }

    $safeUrl = fluxgrid_normalize_url_like_value($url);
    if ($safeUrl === '') { return ''; }

    $intro = isset($attrs['intro']) ? $attrs['intro'] : '';
    $cover = '';
    if (isset($attrs['cover'])) {
        $cover = fluxgrid_sanitize_image_source(str_replace('\\/', '/', $attrs['cover']));
    }

    $titleSafe = fluxgrid_escape($title);
    $html = '<a class="sc-post-card sc-post-card--external" href="' . fluxgrid_escape($safeUrl) . '" target="_blank" rel="noopener noreferrer">';
    if ($cover !== '') {
        $html .= '<span class="sc-post-card-media"><img src="' . fluxgrid_escape($cover) . '" alt="' . $titleSafe . '" loading="lazy"></span>';
    }
    $html .= '<span class="sc-post-card-body">';
    $html .= '<span class="sc-post-card-eyebrow">EXTERNAL ↗</span>';
    $html .= '<span class="sc-post-card-title">' . $titleSafe . '</span>';
    if ($intro !== '') {
        $html .= '<span class="sc-post-card-excerpt">' . fluxgrid_escape($intro) . '</span>';
    }
    $html .= '</span></a>';
    return $html;
}

function fluxgrid_parse_shortcodes($content, $archive = null)
{
    if (!is_string($content) || $content === '') {
        return $content;
    }

    // 先把 <pre>...</pre> 和 <code>...</code> 的内容挖走占位,
    // 避免内联代码里的 [scode]、[tag] 等文本被当成真的短代码匹配。
    $scPlaceholders = array();
    $content = preg_replace_callback(
        '#<(pre|code)\b[^>]*>.*?</\1>#is',
        function ($m) use (&$scPlaceholders) {
            $ph = "\x01FX_SC_PH_" . count($scPlaceholders) . "_FX\x01";
            $scPlaceholders[$ph] = $m[0];
            return $ph;
        },
        $content
    );

    // Markdown 会把块级 shortcode 标记也包进 <p>,破坏 column/block/tabs/timeline
    // 等结构的父子关系,同时残留 </p><p> 污染后续节点(例如让相邻 flex 子元素顶不齐)。
    // 策略:对每个 <p>...</p>,把"带 body 的短代码(整段 tag+body+/tag)"和单标记全剥掉,
    // 若剩余内容仅是空白/<br>,则认为这段 <p> 只是在包短代码,直接解开外层包装。
    $content = preg_replace_callback(
        '#<p[^>]*>(.*?)</p>#is',
        function ($m) {
            $inner = $m[1];
            $stripped = $inner;
            // 1. 整段移除带 body 的短代码(button/tag/scode/collapse/login/reply)
            $stripped = preg_replace(
                '#\[(button|tag|scode|collapse|login|reply)\b[^\]]*\].*?\[/\1\]#us',
                '',
                $stripped
            );
            // 2. 再移除剩余的成对或独立标记(如 [column] [/block] 等)
            $stripped = preg_replace(
                '#\[/?(?:column|block|tabs|tab|timeline|item|collapse|scode|button|tag|post|login|reply)\b[^\]]*\]#u',
                '',
                $stripped
            );
            // 3. 去掉 <br> 和空白
            $stripped = preg_replace('#<br\s*/?>#i', '', $stripped);
            $stripped = trim($stripped);
            // 没有剩余内容 → 整个 <p> 只是包了短代码,连同 <br> 一起去掉 <p> 包装,
            // 否则多个按钮等短代码会因 <br> 被强制断行。
            if ($stripped === '') {
                return preg_replace('#<br\s*/?>#i', '', $inner);
            }
            return $m[0];
        },
        $content
    );

    // [login]...[/login] — 登录后可见
    $content = preg_replace_callback(
        '/\[login\]((?:(?!\[\/login\]).)*)\[\/login\]/su',
        function ($m) {
            return fluxgrid_lock_render_login_block($m[1]);
        },
        $content
    );

    // [reply]...[/reply] — 评论并通过审核后可见
    $content = preg_replace_callback(
        '/\[reply\]((?:(?!\[\/reply\]).)*)\[\/reply\]/su',
        function ($m) use ($archive) {
            return fluxgrid_lock_render_reply_block($m[1], $archive);
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[tag(?:\s+([^\]]*))?\]((?:(?!\[\/tag\]).)*)\[\/tag\]/su',
        function ($m) {
            $attrs = fluxgrid_parse_shortcode_attrs(isset($m[1]) ? $m[1] : '');
            $type = fluxgrid_sc_enum(isset($attrs['type']) ? $attrs['type'] : '', array('primary','info','warning','danger','success','dark'), '');
            $cls = 'sc-tag' . ($type !== '' ? ' sc-tag--' . $type : '');
            return '<span class="' . $cls . '">' . $m[2] . '</span>';
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[scode(?:\s+([^\]]*))?\](.*?)\[\/scode\]/su',
        function ($m) {
            $attrs = fluxgrid_parse_shortcode_attrs(isset($m[1]) ? $m[1] : '');
            $typeRaw = strtolower(isset($attrs['type']) ? $attrs['type'] : 'share');
            if ($typeRaw === 'blue') { $typeRaw = 'lblue'; }
            $type = fluxgrid_sc_enum($typeRaw, array('share','yellow','red','lblue','green'), 'share');
            $size = fluxgrid_sc_enum(isset($attrs['size']) ? $attrs['size'] : 'default', array('default','simple','small'), 'default');
            $cls = 'sc-callout sc-callout--' . $type;
            if ($size !== 'default') { $cls .= ' sc-callout--' . $size; }
            return '<div class="' . $cls . '"><div class="sc-callout-body">' . $m[2] . '</div></div>';
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[post\b([^\]]*)\]/u',
        function ($m) {
            $attrStr = trim(rtrim(trim($m[1]), '/'));
            $attrs = fluxgrid_parse_shortcode_attrs($attrStr);
            if (isset($attrs['cid']) && (int) $attrs['cid'] > 0) {
                return fluxgrid_render_post_card((int) $attrs['cid'], $attrs);
            }
            if (isset($attrs['url']) && isset($attrs['title'])) {
                return fluxgrid_render_external_post_card($attrs);
            }
            return '';
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[button(?:\s+([^\]]*))?\]((?:(?!\[\/button\]).)*)\[\/button\]/su',
        function ($m) {
            $attrs = fluxgrid_parse_shortcode_attrs(isset($m[1]) ? $m[1] : '');
            $color = fluxgrid_sc_enum(isset($attrs['color']) ? $attrs['color'] : 'success', array('light','info','dark','success','black','warning','primary','danger'), 'success');
            $shape = fluxgrid_sc_enum(isset($attrs['type']) ? $attrs['type'] : '', array('round'), '');
            $rawUrl = isset($attrs['url']) ? str_replace('\\/', '/', $attrs['url']) : '#';
            $safe = fluxgrid_normalize_url_like_value($rawUrl);
            if ($safe === '') { $safe = '#'; }
            $icon = isset($attrs['icon']) ? trim($attrs['icon']) : '';
            $cls = 'sc-button sc-button--' . $color . ($shape === 'round' ? ' sc-button--round' : '');
            $iconHtml = $icon !== '' ? '<span class="sc-button-icon">' . fluxgrid_escape($icon) . '</span>' : '';
            $extRel = '';
            if (preg_match('#^https?://([^/]+)#i', $safe, $hm)) {
                $linkHost = strtolower($hm[1]);
                $siteHost = !empty($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
                if ($siteHost === '' || $linkHost !== $siteHost) {
                    $extRel = ' target="_blank" rel="noopener noreferrer"';
                }
            }
            return '<a class="' . $cls . '" href="' . fluxgrid_escape($safe) . '"' . $extRel . '>' . $iconHtml . '<span class="sc-button-text">' . trim($m[2]) . '</span></a>';
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[collapse(?:\s+([^\]]*))?\](.*?)\[\/collapse\]/su',
        function ($m) {
            $attrs = fluxgrid_parse_shortcode_attrs(isset($m[1]) ? $m[1] : '');
            $title = isset($attrs['title']) ? $attrs['title'] : '展开查看';
            $open = (isset($attrs['status']) && strtolower($attrs['status']) === 'true') ? ' open' : '';
            return '<details class="sc-collapse"' . $open . '>' .
                '<summary class="sc-collapse-summary"><span class="sc-collapse-arrow"></span>' . fluxgrid_escape($title) . '</summary>' .
                '<div class="sc-collapse-body">' . $m[2] . '</div>' .
                '</details>';
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[tabs(?:\s+[^\]]*)?\](.*?)\[\/tabs\]/su',
        function ($m) {
            $inner = $m[1];
            $tabs = array();
            preg_match_all('/\[tab(?:\s+([^\]]*))?\](.*?)\[\/tab\]/su', $inner, $all, PREG_SET_ORDER);
            foreach ($all as $i => $tm) {
                $ta = fluxgrid_parse_shortcode_attrs(isset($tm[1]) ? $tm[1] : '');
                $tabs[] = array(
                    'name' => isset($ta['name']) ? $ta['name'] : ('Tab ' . ($i + 1)),
                    'active' => isset($ta['active']) && strtolower($ta['active']) === 'true',
                    'body' => $tm[2],
                );
            }
            if (empty($tabs)) { return ''; }
            $hasActive = false;
            foreach ($tabs as $t) { if ($t['active']) { $hasActive = true; break; } }
            if (!$hasActive) { $tabs[0]['active'] = true; }

            $nav = '<div class="sc-tabs-nav" role="tablist">';
            $panels = '<div class="sc-tabs-panels">';
            foreach ($tabs as $i => $t) {
                $a = $t['active'] ? ' is-active' : '';
                $aria = $t['active'] ? 'true' : 'false';
                $nav .= '<button type="button" class="sc-tab-trigger' . $a . '" data-sc-tab="' . $i . '" role="tab" aria-selected="' . $aria . '">' . fluxgrid_escape($t['name']) . '</button>';
                $panels .= '<div class="sc-tab-panel' . $a . '" data-sc-panel="' . $i . '" role="tabpanel"' . ($t['active'] ? '' : ' hidden') . '>' . $t['body'] . '</div>';
            }
            $nav .= '</div>';
            $panels .= '</div>';
            return '<div class="sc-tabs" data-sc-tabs>' . $nav . $panels . '</div>';
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[timeline(?:\s+([^\]]*))?\](.*?)\[\/timeline\]/su',
        function ($m) {
            $attrs = fluxgrid_parse_shortcode_attrs(isset($m[1]) ? $m[1] : '');
            $title = isset($attrs['title']) ? $attrs['title'] : '';
            $type = fluxgrid_sc_enum(isset($attrs['type']) ? $attrs['type'] : 'small', array('small','large'), 'small');
            $body = preg_replace_callback(
                '/\[item(?:\s+([^\]]*))?\](.*?)\[\/item\]/su',
                function ($im) {
                    $ia = fluxgrid_parse_shortcode_attrs(isset($im[1]) ? $im[1] : '');
                    $date = isset($ia['date']) ? fluxgrid_escape($ia['date']) : '';
                    $color = fluxgrid_sc_enum(isset($ia['color']) ? $ia['color'] : 'primary', array('light','info','dark','success','black','warning','primary','danger'), 'primary');
                    return '<li class="sc-timeline-item sc-timeline-item--' . $color . '">' .
                        ($date !== '' ? '<span class="sc-timeline-date">' . $date . '</span>' : '') .
                        '<div class="sc-timeline-body">' . $im[2] . '</div></li>';
                },
                $m[2]
            );
            return '<div class="sc-timeline sc-timeline--' . $type . '">' .
                ($title !== '' ? '<h4 class="sc-timeline-title">' . fluxgrid_escape($title) . '</h4>' : '') .
                '<ul class="sc-timeline-list">' . $body . '</ul>' .
                '</div>';
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[column(?:\s+[^\]]*)?\](.*?)\[\/column\]/su',
        function ($m) {
            $body = preg_replace_callback(
                '/\[block(?:\s+([^\]]*))?\](.*?)\[\/block\]/su',
                function ($bm) {
                    $ba = fluxgrid_parse_shortcode_attrs(isset($bm[1]) ? $bm[1] : '');
                    $size = isset($ba['size']) ? $ba['size'] : '';
                    $style = '';
                    if ($size !== '' && preg_match('/^[0-9]+(\.[0-9]+)?(%|px|em|rem|fr)?$/', $size)) {
                        // fr 不是合法 flex-basis,留空让 .sc-block 的默认 flex:1 生效
                        if (substr($size, -2) !== 'fr') {
                            // 用 flex: 0 1 让块允许收缩,消化父级 gap,避免 50/50+gap 溢出换行
                            $style = ' style="flex: 0 1 ' . $size . '; max-width: ' . $size . '; min-width: 0;"';
                        }
                    }
                    return '<div class="sc-block"' . $style . '>' . $bm[2] . '</div>';
                },
                $m[1]
            );
            return '<div class="sc-column">' . $body . '</div>';
        },
        $content
    );

    // 恢复先前挖走的 <pre>/<code> 内容
    if (!empty($scPlaceholders)) {
        $content = strtr($content, $scPlaceholders);
    }

    return $content;
}

/* ============================================================================
 * 内容加密 (Lock)
 * 全站 / 分类 / 页面(含自定义字段) / 单篇原生 / [login] / [reply]
 * 参考 handsome 主题的设计。
 * ============================================================================
 */
