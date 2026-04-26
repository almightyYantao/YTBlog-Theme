<?php if (!defined('__TYPECHO_ROOT_DIR__')) { exit; } ?>
<?php $this->comments()->to($comments); ?>
<?php $allowComment = $this->allow('comment'); ?>
<?php $hasComments = $comments->have(); ?>
<?php if ($allowComment || $hasComments): ?>
    <section class="comment-section">
        <div class="flux-container">
            <div class="section-heading">
                <div>
                    <span class="section-tag">COMMENTS</span>
                    <h2><?php $this->commentsNum('还没有评论', '1 条评论', '%d 条评论'); ?></h2>
                </div>
                <p>欢迎留下你的观点，保持交流的清晰和友好。</p>
            </div>

            <?php if ($hasComments): ?>
                <div class="comment-list-wrap">
                    <?php $comments->listComments(); ?>
                    <div class="pagination-wrap">
                        <?php $comments->pageNav('上一页', '下一页'); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($allowComment): ?>
                <div class="comment-form-panel">
                    <h3>写下评论</h3>
                    <form method="post" action="<?php $this->commentUrl(); ?>" class="comment-form">
                        <?php if ($this->user->hasLogin()): ?>
                            <p class="form-note">已登录为 <?php $this->user->screenName(); ?>，提交后会直接显示。</p>
                        <?php else: ?>
                            <div class="form-grid form-grid-three">
                                <label>
                                    <span>昵称</span>
                                    <input type="text" name="author" value="<?php $this->remember('author'); ?>" required>
                                </label>
                                <label>
                                    <span>邮箱</span>
                                    <input type="email" name="mail" value="<?php $this->remember('mail'); ?>" required>
                                </label>
                                <label>
                                    <span>网址</span>
                                    <input type="url" name="url" value="<?php $this->remember('url'); ?>" placeholder="https://">
                                </label>
                            </div>
                        <?php endif; ?>

                        <div class="comment-text-field">
                            <div class="comment-text-head">
                                <label for="fluxgrid-comment-text">评论内容</label>
                                <div class="comment-emoji-wrap">
                                    <button type="button" class="comment-emoji-toggle" aria-label="插入表情" data-emoji-toggle>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                                    </button>
                                    <div class="comment-emoji-panel" data-emoji-panel hidden></div>
                                </div>
                            </div>
                            <textarea id="fluxgrid-comment-text" name="text" rows="6" required data-comment-text></textarea>
                        </div>

                        <button class="button button-primary" type="submit">提交评论</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="comment-form-panel is-disabled">
                    <h3>评论已关闭</h3>
                    <p class="form-note">当前页面不再接受新评论，但历史评论仍然保留。</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
