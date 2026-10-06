<?php defined('BASEPATH') or exit('No direct script access allowed');

$mk4FileCount = is_array($files ?? null) ? count($files) : 0;
$mk4FileComments = 0;
if (is_array($files ?? null)) {
    foreach ($files as $mk4FileRow) {
        $mk4FileComments += total_rows(db_prefix() . 'projectdiscussioncomments', [
            'discussion_id' => $mk4FileRow['id'],
            'discussion_type' => 'file',
        ]);
    }
}
?>

<section class="mk42-feature-hero mk42-files-hero mk4-cursor-glow">
    <div>
        <div class="mk4-kicker">PROJECT FILES</div>
        <h2>Shared Files</h2>
        <p>Upload, preview and discuss project assets from one organized workspace.</p>
    </div>

    <div class="mk42-feature-stats">
        <div class="mk42-feature-stat">
            <span class="mk42-feature-stat-icon"><i class="fa-regular fa-folder-open"></i></span>
            <span><strong class="mk4-counter" data-value="<?= e($mk4FileCount); ?>">0</strong><small>Files</small></span>
        </div>
        <div class="mk42-feature-stat is-purple">
            <span class="mk42-feature-stat-icon"><i class="fa-regular fa-comments"></i></span>
            <span><strong class="mk4-counter" data-value="<?= e($mk4FileComments); ?>">0</strong><small>Comments</small></span>
        </div>
    </div>
</section>

<?php if ($project->settings->upload_files == 1) { ?>
<section class="mk42-upload-shell">
    <div class="mk42-upload-copy">
        <span class="mk42-upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
        <div>
            <strong>Upload project files</strong>
            <small>Drag & drop files or click to browse. Existing Perfex file rules still apply.</small>
        </div>
    </div>

    <?= form_open_multipart(site_url('clients/project/' . $project->id), [
        'class' => 'dropzone mbot15 mk42-project-dropzone',
        'id' => 'project-files-upload'
    ]); ?>
        <input type="file" name="file" multiple class="hide" />
    <?= form_close(); ?>

    <div class="mk42-files-actions">
        <a href="<?= site_url('clients/download_all_project_files/' . $project->id); ?>"
           class="btn btn-primary">
            <i class="fa-solid fa-download"></i>
            <?= _l('download_all'); ?>
        </a>

        <div class="mk42-cloud-actions">
            <button class="gpicker" data-on-pick="projectFileGoogleDriveSave">
                <i class="fa-brands fa-google" aria-hidden="true"></i>
                <?= _l('choose_from_google_drive'); ?>
            </button>
            <div id="dropbox-chooser-project-files"></div>
        </div>
    </div>
</section>
<?php } ?>

<?php if (empty($files)) { ?>
    <div class="mk42-empty-premium">
        <span><i class="fa-regular fa-folder-open"></i></span>
        <strong>No project files yet</strong>
        <p>Uploaded assets, documents and shared files will appear here.</p>
    </div>
<?php } else { ?>
    <div class="mk42-file-grid">
        <?php foreach ($files as $file) {
            $path = get_upload_path_by_type('project') . $project->id . '/' . $file['file_name'];
            $total_file_comments = total_rows(db_prefix() . 'projectdiscussioncomments', [
                'discussion_id' => $file['id'],
                'discussion_type' => 'file',
            ]);
            $isImage = is_image(PROJECT_ATTACHMENTS_FOLDER . $project->id . '/' . $file['file_name'])
                || (!empty($file['external']) && !empty($file['thumbnail_link']));
        ?>
        <article class="mk42-file-card mk4-cursor-glow">
            <a href="#"
               class="mk42-file-preview"
               onclick="view_project_file(<?= e($file['id']); ?>,<?= e($file['project_id']); ?>); return false;">
                <?php if ($isImage) { ?>
                    <img src="<?= e(project_file_url($file, true)); ?>" alt="<?= e($file['subject']); ?>">
                <?php } else { ?>
                    <span class="mk42-file-type-icon">
                        <i class="<?= e(get_mime_class($file['filetype'])); ?>"></i>
                    </span>
                <?php } ?>
                <span class="mk42-file-open"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
            </a>

            <div class="mk42-file-card-body">
                <div class="mk42-file-card-title">
                    <a href="#"
                       onclick="view_project_file(<?= e($file['id']); ?>,<?= e($file['project_id']); ?>); return false;">
                        <?= e($file['subject'] ?: $file['file_name']); ?>
                    </a>
                    <small><?= e($file['filetype']); ?></small>
                </div>

                <div class="mk42-file-meta">
                    <span><i class="fa-regular fa-clock"></i><?= e(!is_null($file['last_activity']) ? time_ago($file['last_activity']) : _l('project_discussion_no_activity')); ?></span>
                    <span><i class="fa-regular fa-comment"></i><?= e($total_file_comments); ?></span>
                    <span><i class="fa-regular fa-calendar"></i><?= e(_dt($file['dateadded'])); ?></span>
                </div>

                <?php if (get_option('allow_contact_to_delete_files') == 1 && $file['contact_id'] == get_contact_user_id()) { ?>
                    <a href="<?= site_url('clients/delete_file/' . $file['id'] . '/project'); ?>"
                       class="btn btn-danger btn-icon _delete mk42-file-delete"
                       title="<?= _l('delete'); ?>">
                        <i class="fa fa-remove"></i>
                    </a>
                <?php } ?>
            </div>
        </article>
        <?php } ?>
    </div>
<?php } ?>

<div id="project_file_data"></div>
