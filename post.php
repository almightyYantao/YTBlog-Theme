<?php if (!defined('__TYPECHO_ROOT_DIR__')) { exit; } ?>
<?php $this->need('header.php'); ?>
<?php
$postDate = fluxgrid_widget_output($this, 'date', 'Y.m.d');
$postModified = isset($this->modified) ? date('Y.m.d', (int) $this->modified) : $postDate;

$postRawText = fluxgrid_post_text($this);
$postWordCount = 0;
if ($postRawText !== '') {
    $plainForCount = strip_tags(preg_replace('/<!--.*?-->/us', '', $postRawText));
    $plainForCount = preg_replace('/\s+/u', '', $plainForCount);
    if (function_exists('mb_strlen')) {
        $postWordCount = mb_strlen($plainForCount, 'UTF-8');
    } else {
        $postWordCount = strlen($plainForCount);
    }
}
$postWordCountLabel = $postWordCount >= 1000
    ? (round($postWordCount / 100) / 10) . 'k'
    : (string) $postWordCount;
$postReadMinutes = max(1, (int) ceil($postWordCount / 400));

$postCoverImage = fluxgrid_safe_image_url(fluxgrid_post_cover($this), $this->options);
?>
<main class="site-main">
    <article class="article-shell">
        <header class="article-head">
            <div class="article-head-media">
                <img
                    src="<?php echo fluxgrid_escape($postCoverImage); ?>"
                    alt=""
                    loading="eager"
                    onerror="<?php echo fluxgrid_escape(fluxgrid_image_fallback_script($this->options)); ?>"
                >
            </div>
            <div class="article-head-surface"></div>

            <div class="flux-container">
                <span class="eyebrow">// <?php echo fluxgrid_escape(fluxgrid_badge($this)); ?></span>
                <h1><?php $this->title(); ?></h1>
                <div class="article-meta">
                    <span><?php $this->author(); ?></span>
                    <span><?php echo fluxgrid_escape($postDate); ?></span>
                    <span><?php $this->commentsNum('0 评论', '1 评论', '%d 评论'); ?></span>
                    <span>预计 <?php echo $postReadMinutes; ?> 分钟</span>
                    <span><?php echo fluxgrid_escape($postWordCountLabel); ?> 字</span>
                </div>
                <div class="tags-list" style="margin-top: 18px;">
                    <?php $this->tags(' ', true, '暂无标签'); ?>
                </div>
            </div>
        </header>

        <div class="flux-container article-container">
            <div class="article-layout">
                <div class="article-main">
                    <div class="article-content">
                        <?php fluxgrid_render_content($this); ?>
                    </div>

                    <footer class="article-footer">
                        <div class="article-tags">
                            <span class="meta-label">标签</span>
                            <div><?php $this->tags(' ', true, '暂无标签'); ?></div>
                        </div>
                        <div class="article-neighbors">
                            <div class="neighbor-card">
                                <span class="meta-label">上一篇</span>
                                <?php $this->thePrev('%s', '已经是第一篇'); ?>
                            </div>
                            <div class="neighbor-card">
                                <span class="meta-label">下一篇</span>
                                <?php $this->theNext('%s', '已经是最后一篇'); ?>
                            </div>
                        </div>
                    </footer>
                </div>

                <aside class="article-aside">
                    <div class="article-toc-wrap" data-article-toc-wrap hidden>
                        <div class="article-toc-panel">
                            <h3>文章目录</h3>
                            <nav class="article-toc" data-article-toc></nav>
                        </div>
                    </div>

                    <div class="article-info">
                        <h3>文章信息</h3>
                        <div class="info-row"><span>发布日期</span><span><?php echo fluxgrid_escape($postDate); ?></span></div>
                        <div class="info-row"><span>最后更新</span><span><?php echo fluxgrid_escape($postModified); ?></span></div>
                        <div class="info-row"><span>字数统计</span><span><?php echo fluxgrid_escape($postWordCountLabel); ?> 字</span></div>
                        <div class="info-row"><span>阅读时间</span><span>约 <?php echo $postReadMinutes; ?> 分钟</span></div>
                        <div class="info-row"><span>分类</span><span><?php $this->category(', ', false); ?></span></div>
                    </div>
                </aside>
            </div>
        </div>
    </article>

    <?php $this->related(3)->to($relatedPosts); ?>
    <?php if ($relatedPosts->have()): ?>
        <section class="related-section">
            <div class="flux-container">
                <div class="section-heading">
                    <h2>相关文章</h2>
                </div>
                <div class="featured-grid">
                    <?php while ($relatedPosts->next()): ?>
                        <article class="featured-card">
                            <span class="eyebrow" style="margin-bottom: 0;">// <?php echo fluxgrid_escape(fluxgrid_badge($relatedPosts)); ?></span>
                            <h3><a href="<?php $relatedPosts->permalink(); ?>"><?php $relatedPosts->title(); ?></a></h3>
                            <p><?php echo fluxgrid_escape(fluxgrid_excerpt($relatedPosts, 90)); ?></p>
                            <div class="card-meta">
                                <span><?php $relatedPosts->date('Y.m.d'); ?></span>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php $this->need('comments.php'); ?>
</main>
<?php $this->need('footer.php'); ?>
