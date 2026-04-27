<?php
/**
 * 现代风格 Typecho 博客主题。粒子背景、代码窗口 Hero、Hero 轮播、短代码系统、多层内容加密、GitHub 个人页。
 *
 * @package YTBlog Theme
 * @author YanTao
 * @version 1.0.0
 * @link https://yantao.wiki
 */
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }
$this->need('header.php');

$heroSlides = array();
$this->widget('Widget_Contents_Post_Recent@fluxgridHomeHero', 'pageSize=5')->to($heroPosts);
while ($heroPosts->next()) {
    $permalink = fluxgrid_normalize_url_like_value(fluxgrid_widget_text($heroPosts, 'permalink', 'permalink'));
    $title = trim(strip_tags(fluxgrid_widget_text($heroPosts, 'title', 'title')));

    if ($permalink === '' || $title === '') {
        continue;
    }

    $heroSlides[] = array(
        'permalink' => $permalink,
        'title' => $title,
        'badge' => fluxgrid_badge($heroPosts),
        'excerpt' => fluxgrid_excerpt($heroPosts, 160),
        'date' => fluxgrid_widget_output($heroPosts, 'date', 'Y.m.d'),
        'image' => fluxgrid_post_cover($heroPosts),
    );

    if (count($heroSlides) >= 5) {
        break;
    }
}

$sideLatest = array();
$this->widget('Widget_Contents_Post_Recent@fluxgridSideLatest', 'pageSize=5')->to($sideLatestPosts);
while ($sideLatestPosts->next()) {
    $permalink = fluxgrid_normalize_url_like_value(fluxgrid_widget_text($sideLatestPosts, 'permalink', 'permalink'));
    $title = trim(strip_tags(fluxgrid_widget_text($sideLatestPosts, 'title', 'title')));
    if ($permalink === '' || $title === '') {
        continue;
    }
    $sideLatest[] = array(
        'permalink' => $permalink,
        'title' => $title,
        'date' => fluxgrid_widget_output($sideLatestPosts, 'date', 'Y.m.d'),
    );
}

$navCategoryChips = array();
foreach ($navCategories as $navCat) {
    $navCategoryChips[] = $navCat;
    if (count($navCategoryChips) >= 8) {
        break;
    }
}

$siteTitleEscaped = fluxgrid_escape($siteTitle);
$siteDescEscaped = fluxgrid_escape(fluxgrid_option($this->options, 'footerText', (string) $this->options->description));

$this->widget('Widget_Stat')->to($siteStat);
$statPosts = isset($siteStat->publishedPostsNum) ? (int) $siteStat->publishedPostsNum : 0;
$statComments = isset($siteStat->publishedCommentsNum) ? (int) $siteStat->publishedCommentsNum : 0;
$statCategories = isset($siteStat->categoriesNum) ? (int) $siteStat->categoriesNum : 0;

$latestComments = array();
try {
    $this->widget('Widget_Comments_Recent', 'pageSize=3')->to($recentComments);
    while ($recentComments->next()) {
        $commentAuthor = fluxgrid_string_value(isset($recentComments->author) ? $recentComments->author : '');
        $commentText = '';
        if (isset($recentComments->text)) {
            $commentText = trim(strip_tags(fluxgrid_string_value($recentComments->text)));
        }
        if ($commentText !== '' && function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($commentText, 'UTF-8') > 60) {
                $commentText = mb_substr($commentText, 0, 60, 'UTF-8') . '...';
            }
        }
        $commentPermalink = fluxgrid_normalize_url_like_value(fluxgrid_widget_text($recentComments, 'permalink', 'permalink'));
        $commentPostTitle = fluxgrid_string_value(isset($recentComments->title) ? $recentComments->title : '');
        $commentDate = fluxgrid_widget_output($recentComments, 'date', 'm.d');
        if ($commentAuthor === '' || $commentPermalink === '') {
            continue;
        }
        $latestComments[] = array(
            'author' => $commentAuthor,
            'text' => $commentText,
            'permalink' => $commentPermalink,
            'title' => $commentPostTitle,
            'date' => $commentDate,
        );
    }
} catch (Exception $e) {
    $latestComments = array();
} catch (Throwable $e) {
    $latestComments = array();
}

// 置顶文章: 直接按 cid 查 DB, 不依赖文章新旧顺序, 老文章也能正确显示。
$stickyPosts = array();
$stickyCids = fluxgrid_sticky_cids($this->options);
if (!empty($stickyCids) && class_exists('Typecho_Db')) {
    try {
        $db = Typecho_Db::get();
        // int-cast + 拼字面值; 因为已经全是整数,无 SQL 注入风险,
        // 也避免 Typecho_Db_Query 的 IN 多绑定在不同版本表现不一致。
        $cidList = implode(',', array_map('intval', $stickyCids));

        $rows = $db->fetchAll(
            $db->select()->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('cid IN (' . $cidList . ')')
        );

        // 一次性取出所有 sticky 文章的自定义字段 (banner / cover / summary / badge)
        $fieldsByCid = array();
        if (!empty($rows)) {
            $fieldRows = $db->fetchAll(
                $db->select()->from('table.fields')->where('cid IN (' . $cidList . ')')
            );
            foreach ($fieldRows as $f) {
                $fieldsByCid[(int) $f['cid']][$f['name']] = (string) $f['str_value'];
            }
        }

        $byCid = array();
        foreach ($rows as $row) {
            $cid = (int) $row['cid'];
            $fields = isset($fieldsByCid[$cid]) ? $fieldsByCid[$cid] : array();

            // 图片: banner → cover → 文章首图 → 随机图
            $image = '';
            foreach (array('banner', 'cover') as $fname) {
                if (!empty($fields[$fname])) {
                    $image = fluxgrid_sanitize_image_source($fields[$fname]);
                    if ($image !== '') { break; }
                }
            }
            if ($image === '') {
                $image = fluxgrid_first_image((string) $row['text']);
            }
            if ($image === '') {
                $image = fluxgrid_fallback_image($this->options, $cid);
            }

            // 摘要: summary 字段优先, 否则用文章正文截取
            if (!empty($fields['summary'])) {
                $excerpt = preg_replace('/\s+/u', ' ', $fields['summary']);
            } else {
                $plain = preg_replace('/<!--.*?-->/us', '', (string) $row['text']);
                $plain = preg_replace('/```[\s\S]*?```/u', ' ', $plain);
                $plain = strip_tags($plain);
                $plain = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', $plain);
                $plain = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $plain);
                $plain = trim(preg_replace('/\s+/u', ' ', $plain));
                if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                    $excerpt = mb_strlen($plain, 'UTF-8') > 140
                        ? mb_substr($plain, 0, 140, 'UTF-8') . '...'
                        : $plain;
                } else {
                    $excerpt = strlen($plain) > 140 ? substr($plain, 0, 140) . '...' : $plain;
                }
            }

            // 用 Typecho_Router 生成永久链接
            $row['type'] = 'post';
            $row['slug'] = isset($row['slug']) ? $row['slug'] : '';
            $permalink = '';
            try {
                if (class_exists('Typecho_Router')) {
                    $permalink = Typecho_Router::url('post', $row, $this->options->index);
                }
            } catch (Exception $e) {
                $permalink = '';
            } catch (Throwable $e) {
                $permalink = '';
            }
            if ($permalink === '' || $permalink === null) {
                $permalink = rtrim((string) $this->options->siteUrl, '/') . '/?p=' . $cid;
            }

            $byCid[$cid] = array(
                'permalink' => fluxgrid_normalize_url_like_value($permalink),
                'title'     => trim(strip_tags((string) $row['title'])),
                'excerpt'   => $excerpt,
                'date'      => date('Y.m.d', (int) $row['created']),
                'image'     => $image,
                'badge'     => !empty($fields['badge']) ? $fields['badge'] : 'PINNED',
            );
        }

        // 按 sticky 列表的顺序输出 (主题设置里手填的 cid 顺序优先)
        foreach ($stickyCids as $cid) {
            if (isset($byCid[$cid])) {
                $stickyPosts[] = $byCid[$cid];
            }
        }
    } catch (Exception $e) {
        $stickyPosts = array();
    } catch (Throwable $e) {
        // PHP 7+ Error 兜底 (Typecho_Db 类找不到、SQL 语法错误等)
        $stickyPosts = array();
    }
}
?>
<main class="site-main">
    <?php if (!empty($heroSlides)): ?>
        <section class="hero-section">
            <div class="hero-carousel" data-hero-carousel>
                <?php foreach ($heroSlides as $heroIndex => $heroSlide): ?>
                    <article class="hero-slide <?php echo $heroIndex === 0 ? 'is-active' : ''; ?>" data-hero-slide>
                        <div class="hero-media">
                            <img
                                src="<?php echo fluxgrid_escape($heroSlide['image']); ?>"
                                alt=""
                                loading="<?php echo $heroIndex === 0 ? 'eager' : 'lazy'; ?>"
                                onerror="<?php echo fluxgrid_escape(fluxgrid_image_fallback_script($this->options)); ?>"
                            >
                        </div>
                        <div class="hero-surface"></div>
                        <div class="flux-container">
                            <div class="hero-grid">
                                <div class="hero-copy">
                                    <span class="eyebrow">// <?php echo fluxgrid_escape($heroSlide['badge']); ?></span>
                                    <h1><a href="<?php echo fluxgrid_escape($heroSlide['permalink']); ?>"><?php echo fluxgrid_escape($heroSlide['title']); ?></a></h1>
                                    <?php if ($heroSlide['excerpt'] !== ''): ?>
                                        <p><?php echo fluxgrid_escape($heroSlide['excerpt']); ?></p>
                                    <?php endif; ?>
                                    <div class="hero-actions">
                                        <a class="button button-primary" href="<?php echo fluxgrid_escape($heroSlide['permalink']); ?>">阅读全文 →</a>
                                        <a class="button button-secondary" href="#post-stream">浏览文章</a>
                                    </div>
                                </div>

                                <div class="hero-side-visual">
                                    <div class="code-window">
                                        <div class="code-window-top">
                                            <span class="code-dot"></span>
                                            <span class="code-dot"></span>
                                            <span class="code-dot"></span>
                                        </div>
<pre><span class="cw-kw">const</span> blog = {
  title: <span class="cw-str">'<?php echo fluxgrid_escape($siteTitle); ?>'</span>,
  focus: <span class="cw-str">'<?php echo fluxgrid_escape($heroSlide['badge']); ?>'</span>,
  updated: <span class="cw-str">'<?php echo fluxgrid_escape($heroSlide['date']); ?>'</span>
}

<span class="cw-kw">function</span> <span class="cw-fn">latest</span>() {
  <span class="cw-kw">return</span> <span class="cw-str">'<?php echo fluxgrid_escape(mb_substr($heroSlide['title'], 0, 24, 'UTF-8')); ?>'</span>
}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>

                <?php if (count($heroSlides) > 1): ?>
                    <div class="hero-controls">
                        <button class="hero-arrow hero-arrow--prev" type="button" data-hero-prev aria-label="上一张">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 6 9 12 15 18"/></svg>
                        </button>
                        <button class="hero-arrow hero-arrow--next" type="button" data-hero-next aria-label="下一张">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg>
                        </button>
                        <div class="hero-dots">
                            <?php for ($dotIndex = 0; $dotIndex < count($heroSlides); $dotIndex++): ?>
                                <button
                                    class="hero-dot <?php echo $dotIndex === 0 ? 'is-active' : ''; ?>"
                                    type="button"
                                    data-hero-dot="<?php echo $dotIndex; ?>"
                                    aria-label="切换到第 <?php echo $dotIndex + 1; ?> 张"
                                ></button>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($stickyPosts)): ?>
        <section class="sticky-section">
            <div class="flux-container">
                <div class="section-heading section-heading--row">
                    <div>
                        <span class="section-tag">PINNED</span>
                        <h2>置顶推荐</h2>
                    </div>
                    <p><?php echo count($stickyPosts); ?> 篇精选文章</p>
                </div>
                <div class="sticky-grid sticky-grid--<?php echo min(count($stickyPosts), 3); ?>">
                    <?php foreach ($stickyPosts as $sp): ?>
                        <a class="sticky-card" href="<?php echo fluxgrid_escape($sp['permalink']); ?>">
                            <div class="sticky-card-media">
                                <img
                                    src="<?php echo fluxgrid_escape(fluxgrid_safe_image_url($sp['image'], $this->options)); ?>"
                                    alt="<?php echo fluxgrid_escape($sp['title']); ?>"
                                    loading="lazy"
                                    onerror="<?php echo fluxgrid_escape(fluxgrid_image_fallback_script($this->options)); ?>"
                                >
                            </div>
                            <div class="sticky-card-overlay"></div>
                            <div class="sticky-card-body">
                                <span class="sticky-card-badge">// <?php echo fluxgrid_escape($sp['badge']); ?></span>
                                <h3><?php echo fluxgrid_escape($sp['title']); ?></h3>
                                <?php if ($sp['excerpt'] !== ''): ?>
                                    <p><?php echo fluxgrid_escape($sp['excerpt']); ?></p>
                                <?php endif; ?>
                                <span class="sticky-card-date"><?php echo fluxgrid_escape($sp['date']); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="stream-section" id="post-stream">
        <div class="flux-container">
            <div class="home-grid">
                <div class="home-main">
                    <div class="section-heading">
                        <h2>最新文章</h2>
                        <p>持续记录与沉淀</p>
                    </div>

                    <?php if ($this->have()): ?>
                        <div class="post-stack">
                            <?php while ($this->next()): ?>
                                <?php $postCover = fluxgrid_safe_image_url(fluxgrid_post_cover($this), $this->options); ?>
                                <?php echo fluxgrid_cover_debug_comment($this, 'index-stream'); ?>
                                <?php $lockInfo = fluxgrid_lock_list_display($this, $this->options); ?>
                                <article class="post-card<?php echo $lockInfo ? ' post-card--locked' : ''; ?>">
                                    <a class="post-card-media" href="<?php $this->permalink(); ?>">
                                        <img
                                            src="<?php echo fluxgrid_escape($postCover); ?>"
                                            alt="<?php if ($lockInfo && $lockInfo['hideTitle']) echo '加密内容'; else $this->title(); ?>"
                                            loading="lazy"
                                            onerror="<?php echo fluxgrid_escape(fluxgrid_image_fallback_script($this->options)); ?>"
                                        >
                                    </a>
                                    <div class="post-card-body">
                                        <h3><a href="<?php $this->permalink(); ?>"><?php
                                            if ($lockInfo && $lockInfo['hideTitle']) { echo '🔒 加密内容'; }
                                            else { $this->title(); }
                                        ?></a></h3>
                                        <p><?php
                                            if ($lockInfo) { echo fluxgrid_escape($lockInfo['excerpt']); }
                                            else { echo fluxgrid_escape(fluxgrid_excerpt($this, 110)); }
                                        ?></p>
                                        <div class="card-meta">
                                            <div class="tags-list">
                                                <?php $this->category(' ', false); ?>
                                            </div>
                                            <span><?php $this->date('Y.m.d'); ?> · <?php $this->commentsNum('0 评论', '1 评论', '%d 评论'); ?></span>
                                        </div>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>

                        <div class="pagination-wrap">
                            <?php $this->pageNav('上一页', '下一页', 1, '...'); ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-panel">
                            <h3>内容正在准备中</h3>
                            <p>发布文章后，这里会自动显示最新内容。</p>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="sidebar">
                    <?php
                    $profileAvatar = fluxgrid_option($this->options, 'avatarUrl', '');
                    $profileBanner = fluxgrid_option($this->options, 'bannerUrl', '');
                    ?>
                    <div class="side-card side-profile">
                        <div class="profile-banner"<?php if ($profileBanner !== '') echo ' style="background-image:url(' . fluxgrid_escape($profileBanner) . ')"'; ?>></div>
                        <div class="profile-body">
                            <?php if ($profileAvatar !== ''): ?>
                                <img class="profile-avatar" src="<?php echo fluxgrid_escape($profileAvatar); ?>" alt="<?php echo $siteTitleEscaped; ?>">
                            <?php else: ?>
                                <div class="profile-avatar profile-avatar--default"></div>
                            <?php endif; ?>
                            <div class="profile-name"><?php echo $siteTitleEscaped; ?></div>
                            <div class="profile-bio"><?php echo $siteDescEscaped !== '' ? $siteDescEscaped : '记录技术，沉淀思考，构建价值。'; ?></div>
                            <div class="profile-stats">
                                <div class="profile-stat">
                                    <strong><?php echo $statPosts; ?></strong>
                                    <span>文章</span>
                                </div>
                                <div class="profile-stat">
                                    <strong><?php echo $statCategories; ?></strong>
                                    <span>分类</span>
                                </div>
                                <div class="profile-stat">
                                    <strong><?php echo $statComments; ?></strong>
                                    <span>评论</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($navCategoryChips)): ?>
                        <div class="side-card">
                            <h3>分类</h3>
                            <div class="tags-list" style="margin-top: 14px;">
                                <?php foreach ($navCategoryChips as $cat): ?>
                                    <a class="tag" href="<?php echo fluxgrid_escape($cat['permalink']); ?>"><?php echo fluxgrid_escape($cat['name']); ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($latestComments)): ?>
                        <div class="side-card">
                            <h3>最新评论</h3>
                            <ul class="side-comments">
                                <?php foreach ($latestComments as $c): ?>
                                    <li>
                                        <div class="sc-head">
                                            <span class="sc-author"><?php echo fluxgrid_escape($c['author']); ?></span>
                                            <span class="sc-meta"><?php echo fluxgrid_escape($c['date']); ?></span>
                                        </div>
                                        <?php if ($c['text'] !== ''): ?>
                                            <p class="sc-text"><?php echo fluxgrid_escape($c['text']); ?></p>
                                        <?php endif; ?>
                                        <span class="sc-on">on <a href="<?php echo fluxgrid_escape($c['permalink']); ?>"><?php echo fluxgrid_escape($c['title']); ?></a></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($sideLatest)): ?>
                        <div class="side-card">
                            <h3>最新文章</h3>
                            <ul class="side-list" style="margin-top: 14px;">
                                <?php foreach ($sideLatest as $sideItem): ?>
                                    <li>
                                        <a href="<?php echo fluxgrid_escape($sideItem['permalink']); ?>">
                                            <strong><?php echo fluxgrid_escape($sideItem['title']); ?></strong>
                                            <span><?php echo fluxgrid_escape($sideItem['date']); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="side-card">
                        <h3>订阅</h3>
                        <p>通过 RSS 获取最新文章更新。</p>
                        <a class="button button-primary" style="margin-top: 14px; width: 100%;" href="<?php $this->options->feedUrl(); ?>">RSS 订阅</a>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>
<?php $this->need('footer.php'); ?>
