<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php if (!isset($discussion)) {
    $mk42DiscussionCount = is_array($discussions ?? null) ? count($discussions) : 0;
    $mk42CommentCount = 0;
    if (is_array($discussions ?? null)) {
        foreach ($discussions as $mk42Row) {
            $mk42CommentCount += (int) $mk42Row['total_comments'];
        }
    }
?>
<section class="mk42-feature-hero mk42-discussion-hero mk4-cursor-glow">
    <div>
        <div class="mk4-kicker">PROJECT CONVERSATIONS</div>
        <h2>Discussions</h2>
        <p>Keep decisions, feedback and project conversations attached to the work.</p>
    </div>

    <div class="mk42-feature-stats">
        <div class="mk42-feature-stat">
            <span class="mk42-feature-stat-icon"><i class="fa-regular fa-comments"></i></span>
            <span><strong class="mk4-counter" data-value="<?= e($mk42DiscussionCount); ?>">0</strong><small>Threads</small></span>
        </div>
        <div class="mk42-feature-stat is-purple">
            <span class="mk42-feature-stat-icon"><i class="fa-regular fa-message"></i></span>
            <span><strong class="mk4-counter" data-value="<?= e($mk42CommentCount); ?>">0</strong><small>Comments</small></span>
        </div>
    </div>
</section>

<?php if ($project->settings->open_discussions == 1) { ?>
    <div class="mk42-discussion-toolbar">
        <div>
            <strong>Project conversations</strong>
            <small>Start a new thread when feedback needs its own context.</small>
        </div>
        <a href="#" onclick="new_discussion();return false;" class="btn btn-primary">
            <i class="fa-regular fa-plus"></i>
            <?= _l('new_project_discussion'); ?>
        </a>
    </div>

    <div class="modal fade mk365-discussion-modal-wrap" id="discussion" tabindex="-1" role="dialog">
        <div class="modal-dialog mk365-discussion-dialog">
            <div class="modal-content mk42-discussion-modal mk365-discussion-modal">
                <?= form_open(site_url('clients/project/' . $project->id), ['id' => 'discussion_form', 'class' => 'mk365-discussion-form']); ?>
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <div class="mk42-modal-title">
                            <span class="mk42-modal-icon"><i class="fa-regular fa-comments"></i></span>
                            <h4 class="modal-title">
                                <span class="edit-title"><?= _l('edit_discussion'); ?></span>
                                <span class="add-title"><?= _l('new_project_discussion'); ?></span>
                            </h4>
                        </div>
                    </div>
                    <div class="modal-body mk365-discussion-modal-body">
                        <?= form_hidden('project_id', $project->id); ?>
                        <?= form_hidden('action', 'new_discussion'); ?>
                        <div id="additional_discussion"></div>
                        <?= render_input('subject', 'project_discussion_subject'); ?>
                        <?= render_textarea('description', 'project_discussion_description'); ?>
                    </div>
                    <div class="modal-footer mk365-discussion-modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                        <button type="submit" class="btn btn-primary"
                            data-loading-text="<?= _l('wait_text'); ?>"
                            data-autocomplete="off"
                            data-form="#discussion_form"><?= _l('submit'); ?></button>
                    </div>
                <?= form_close(); ?>
            </div>
        </div>
    </div>
<?php } ?>

<?php if (empty($discussions)) { ?>
    <div class="mk42-empty-premium">
        <span><i class="fa-regular fa-comments"></i></span>
        <strong>No discussions yet</strong>
        <p>Project feedback and collaboration threads will appear here.</p>
    </div>
<?php } else { ?>
    <div class="mk42-discussion-grid">
        <?php foreach ($discussions as $item) { ?>
            <a class="mk42-discussion-card mk4-cursor-glow"
               href="<?= site_url('clients/project/' . $project->id . '?group=' . $group . '&discussion_id=' . $item['id']); ?>">
                <div class="mk42-discussion-card-icon"><i class="fa-regular fa-message"></i></div>
                <div class="mk42-discussion-card-copy">
                    <strong><?= e($item['subject']); ?></strong>
                    <span>
                        <i class="fa-regular fa-clock"></i>
                        <?= e(!is_null($item['last_activity']) ? time_ago($item['last_activity']) : _l('project_discussion_no_activity')); ?>
                    </span>
                </div>
                <div class="mk42-discussion-comments">
                    <strong><?= e($item['total_comments']); ?></strong>
                    <small>comments</small>
                </div>
                <i class="fa-solid fa-chevron-right mk42-discussion-chevron"></i>
            </a>
        <?php } ?>
    </div>
<?php } ?>

<?php } else { ?>

<?= form_hidden('discussion_user_profile_image_url', $discussion_user_profile_image_url); ?>
<?= form_hidden('discussion_id', $discussion->id); ?>

<section class="mk42-discussion-detail-hero mk4-cursor-glow">
    <a class="mk42-back-link" href="<?= site_url('clients/project/' . $project->id . '?group=' . $group); ?>">
        <i class="fa-solid fa-arrow-left"></i> Discussions
    </a>
    <div class="mk42-discussion-detail-main">
        <span class="mk42-discussion-detail-icon"><i class="fa-regular fa-message"></i></span>
        <div>
            <div class="mk4-kicker">DISCUSSION</div>
            <h2><?= e($discussion->subject); ?></h2>
            <div class="mk42-discussion-detail-meta">
                <span><i class="fa-regular fa-calendar"></i><?= e(_d($discussion->datecreated)); ?></span>
                <span><i class="fa-regular fa-user"></i><?= e($discussion->staff_id == 0 ? get_contact_full_name($discussion->contact_id) : get_staff_full_name($discussion->staff_id)); ?></span>
                <span><i class="fa-regular fa-comment"></i><?= e(total_rows(db_prefix() . 'projectdiscussioncomments', ['discussion_id' => $discussion->id, 'discussion_type' => 'regular'])); ?> comments</span>
            </div>
        </div>
    </div>
</section>

<section class="mk42-discussion-body">
    <div class="mk42-discussion-original">
        <div class="mk42-conversation-label">Original message</div>
        <div class="tw-text-neutral-500">
            <?= process_text_content_for_display($discussion->description); ?>
        </div>
    </div>

    <div class="mk42-comments-shell">
        <div class="mk42-conversation-label">Conversation</div>
        <div id="discussion-comments" class="tc-content"></div>
    </div>
</section>

<?php } ?>
