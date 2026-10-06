<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?= theme_head_view(); ?>
<?php get_template_part($navigationEnabled ? 'navigation' : ''); ?>
<div id="wrapper">
    <div id="content">
        <div class="container">
            <div class="row">
                <?php get_template_part('alerts'); ?>
            </div>
        </div>
        <?php if (isset($knowledge_base_search)) { ?>
        <?php get_template_part('knowledge_base/search'); ?>
        <?php } ?>
        <div class="container">
            <?php hooks()->do_action('customers_content_container_start'); ?>
            <div class="row">
                <?php
            /**
             * Don't show calendar for invoices, estimates, proposals etc.. views where no navigation is included or in kb area
             */
            if (is_client_logged_in() && $subMenuEnabled && ! isset($knowledge_base_search)) {
                ob_start();
                hooks()->do_action('before_customers_area_sub_menu_start');
                hooks()->do_action('after_customers_area_sub_menu_end');
                $mk4CustomSubMenu = trim(ob_get_clean());

                if ($mk4CustomSubMenu !== '') { ?>
                    <ul class="submenu customer-top-submenu mk4-extension-submenu">
                        <?= $mk4CustomSubMenu; ?>
                    </ul>
                    <div class="clearfix"></div>
                <?php }
            } ?>
                <?= theme_template_view(); ?>
            </div>
        </div>
    </div>
</div>
</div>
<?= theme_footer_view();

// Always have app_customers_footer() just before the closing </body>
app_customers_footer();
/**
 * Check for any alerts stored in session
 */
app_js_alerts();
?>
</body>

</html>