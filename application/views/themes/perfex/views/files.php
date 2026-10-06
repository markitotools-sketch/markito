<?php defined('BASEPATH') or exit('No direct script access allowed');

$mk42TotalFiles = is_array($files ?? null) ? count($files) : 0;
?>

<section class="mk42-page-hero mk42-files-page-hero mk4-cursor-glow">
    <div class="mk42-page-hero-copy">
        <div class="mk4-kicker">FILE HUB</div>
        <h1><?= _l('customer_profile_files'); ?></h1>
        <p>Your shared documents and uploads, organized in one secure place.</p>
    </div>
    <div class="mk42-page-hero-stat">
        <span><i class="fa-regular fa-folder-open"></i></span>
        <div><strong class="mk4-counter" data-value="<?= e($mk42TotalFiles); ?>">0</strong><small>Total Files</small></div>
    </div>
</section>

<?php hooks()->do_action('after_customers_area_files_heading'); ?>

<section class="mk42-upload-shell mk42-global-upload">
    <div class="mk42-upload-copy">
        <span class="mk42-upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
        <div>
            <strong>Upload a file</strong>
            <small>Drag & drop your file below or click the upload area.</small>
        </div>
    </div>

    <?= form_open_multipart(site_url('clients/upload_files'), [
        'class' => 'dropzone mk42-project-dropzone',
        'id' => 'files-upload'
    ]); ?>
        <input type="file" name="file" multiple class="hide" />
    <?= form_close(); ?>

    <?php hooks()->do_action('after_customers_area_files_dropzone'); ?>

    <div class="mk42-files-actions">
        <span class="mk42-files-hint"><i class="fa-solid fa-shield-halved"></i> Secure client file storage</span>
        <div class="mk42-cloud-actions">
            <button class="gpicker" data-on-pick="customerFileGoogleDriveSave">
                <i class="fa-brands fa-google" aria-hidden="true"></i>
                <?= _l('choose_from_google_drive'); ?>
            </button>
            <?php if (get_option('dropbox_app_key') != '') { ?>
                <div id="dropbox-chooser-files"></div>
            <?php } ?>
        </div>
    </div>
</section>

<?php if (count($files) == 0) { ?>
    <div class="mk42-empty-premium">
        <span><i class="fa-regular fa-folder-open"></i></span>
        <strong><?= _l('no_files_found'); ?></strong>
        <p>Your uploaded and shared files will appear here.</p>
    </div>
<?php } else { ?>
    <div class="mk42-file-grid mk42-global-file-grid">
        <?php foreach ($files as $file) {
            $url = site_url() . 'download/file/client/';
            $path = get_upload_path_by_type('customer') . $file['rel_id'] . '/' . $file['file_name'];
            $is_image = false;

            if (!isset($file['external'])) {
                $attachment_url = $url . $file['attachment_key'];
                $is_image = is_image($path);
                $img_url = site_url('download/preview_image?path=' . protected_file_url_by_path($path, true) . '&type=' . $file['filetype']);
            } elseif (isset($file['external']) && !empty($file['external'])) {
                if (!empty($file['thumbnail_link'])) {
                    $is_image = true;
                    $img_url = optimize_dropbox_thumbnail($file['thumbnail_link']);
                }
                $attachment_url = $file['external_link'];
            }
        ?>
        <article class="mk42-file-card mk4-cursor-glow">
            <a href="<?= e($attachment_url); ?>"
               <?= isset($file['external']) && !empty($file['external']) ? ' target="_blank"' : ''; ?>
               class="mk42-file-preview">
                <?php if ($is_image) { ?>
                    <img src="<?= e($img_url); ?>" alt="<?= e($file['file_name']); ?>">
                <?php } else { ?>
                    <span class="mk42-file-type-icon">
                        <i class="<?= e(get_mime_class($file['filetype'])); ?>"></i>
                    </span>
                <?php } ?>
                <span class="mk42-file-open"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
            </a>

            <div class="mk42-file-card-body">
                <div class="mk42-file-card-title">
                    <a href="<?= e($attachment_url); ?>"
                       <?= isset($file['external']) && !empty($file['external']) ? ' target="_blank"' : ''; ?>>
                        <?= e($file['file_name']); ?>
                    </a>
                    <small><?= e($file['filetype']); ?></small>
                </div>

                <div class="mk42-file-meta">
                    <span><i class="fa-regular fa-calendar"></i><?= e(_dt($file['dateadded'])); ?></span>
                </div>

                <?php if (get_option('allow_contact_to_delete_files') == 1 && $file['contact_id'] == get_contact_user_id()) { ?>
                    <a href="<?= site_url('clients/delete_file/' . $file['id'] . '/general'); ?>"
                       class="btn btn-danger btn-icon _delete file-delete mk42-file-delete">
                        <i class="fa fa-remove"></i>
                    </a>
                <?php } ?>
            </div>
        </article>
        <?php } ?>
    </div>
<?php } ?>

<?php hooks()->do_action('after_customers_area_files'); ?>
