<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<nav class="navbar navbar-default header mk43-header" id="mk43-header">
    <div class="container mk43-shell" id="mk43-shell">
        <div class="navbar-header mk43-brand-area">
            <button type="button"
                class="navbar-toggle collapsed mk43-mobile-toggle"
                data-toggle="collapse"
                data-target="#theme-navbar-collapse"
                aria-expanded="false">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>

            <a href="<?= site_url(); ?>" class="mk43-brand" aria-label="Markito Client Portal">
                <span class="mk43-brand-mark">M</span>
                <span class="mk43-brand-copy">
                    <strong>Markito</strong>
                    <small>Client Portal</small>
                </span>
            </a>
        </div>

        <div class="collapse navbar-collapse mk43-collapse" id="theme-navbar-collapse">
            <div class="mk43-layout">
                <div class="mk43-primary-wrap" id="mk43-primary-wrap">
                    <ul class="nav navbar-nav mk43-primary-list" id="mk43-primary-list">
                        <?php if (is_client_logged_in()) { ?>
                            <li class="mk43-primary-item mk43-home-link<?= rtrim(current_full_url(), '/') === rtrim(site_url(), '/') ? ' active' : ''; ?>"
                                data-mk43-primary="1">
                                <a href="<?= site_url(); ?>">
                                    <i class="fa-solid fa-house"></i>
                                    <span>Home</span>
                                </a>
                            </li>
                        <?php } ?>

                        <?php hooks()->do_action('customers_navigation_start'); ?>

                        <?php foreach ($menu as $item_id => $item) { ?>
                            <li class="mk43-primary-item customers-nav-item-<?= e($item_id); ?><?= $item['href'] === current_full_url() ? ' active' : ''; ?>"
                                data-mk43-primary="1"
                                <?= _attributes_to_string($item['li_attributes'] ?? []); ?>>
                                <a href="<?= e($item['href']); ?>"
                                    <?= _attributes_to_string($item['href_attributes'] ?? []); ?>>
                                    <?php
                                    if (! empty($item['icon'])) {
                                        echo '<i class="' . $item['icon'] . '"></i>';
                                    }
                                    ?>
                                    <span><?= e($item['name']); ?></span>
                                </a>
                            </li>
                        <?php } ?>

                        <?php hooks()->do_action('customers_navigation_end'); ?>

                        <li class="dropdown mk43-more" id="mk43-more">
                            <a href="#"
                                class="dropdown-toggle"
                                data-toggle="dropdown"
                                role="button"
                                aria-haspopup="true"
                                aria-expanded="false">
                                <span>More</span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </a>
                            <ul class="dropdown-menu mk43-more-menu" id="mk43-more-menu"></ul>
                        </li>
                    </ul>
                </div>

                <?php if (is_client_logged_in()) { ?>
                    <ul class="nav navbar-nav mk43-actions">
                        <li class="mk43-action-item mk43-action-link<?= strpos(current_full_url(), site_url('clients/files')) === 0 ? ' active' : ''; ?>">
                            <a href="<?= site_url('clients/files'); ?>" title="<?= e(_l('customer_profile_files')); ?>">
                                <i class="fa-regular fa-folder-open"></i>
                                <span class="mk43-action-label"><?= _l('customer_profile_files'); ?></span>
                            </a>
                        </li>

                        <li class="mk43-action-item mk43-action-link<?= strpos(current_full_url(), site_url('clients/calendar')) === 0 ? ' active' : ''; ?>">
                            <a href="<?= site_url('clients/calendar'); ?>" title="<?= e(_l('calendar')); ?>">
                                <i class="fa-regular fa-calendar-days"></i>
                                <span class="mk43-action-label"><?= _l('calendar'); ?></span>
                            </a>
                        </li>

                        <li class="mk43-action-divider" aria-hidden="true"></li>

                        <li class="mk43-action-item mk43-bell">
                            <a href="<?= site_url('clients/announcements'); ?>" title="<?= e(_l('announcements')); ?>">
                                <i class="fa-regular fa-bell"></i>
                                <?php if ($total_undismissed_announcements != 0) { ?>
                                    <span class="mk43-notification-count"><?= e($total_undismissed_announcements); ?></span>
                                <?php } ?>
                            </a>
                        </li>

                        <li class="dropdown customers-nav-item-profile mk43-profile">
                            <a href="#"
                                class="dropdown-toggle mk43-profile-trigger"
                                data-toggle="dropdown"
                                role="button"
                                aria-haspopup="true"
                                aria-expanded="false">
                                <span class="mk43-profile-avatar">
                                    <img src="<?= e(contact_profile_image_url($contact->id, 'thumb')); ?>"
                                        alt="<?= e($contact->firstname . ' ' . $contact->lastname); ?>">
                                    <span class="mk43-profile-online"></span>
                                </span>

                                <span class="mk43-profile-text">
                                    <strong><?= e($contact->firstname); ?></strong>
                                    <small>Account</small>
                                </span>

                                <i class="fa-solid fa-chevron-down mk43-profile-chevron"></i>
                            </a>

                            <ul class="dropdown-menu mk43-profile-menu">
                                <li class="mk43-profile-menu-head">
                                    <span class="mk43-profile-avatar is-large">
                                        <img src="<?= e(contact_profile_image_url($contact->id, 'thumb')); ?>"
                                            alt="<?= e($contact->firstname . ' ' . $contact->lastname); ?>">
                                        <span class="mk43-profile-online"></span>
                                    </span>
                                    <span class="mk43-profile-menu-copy">
                                        <strong><?= e($contact->firstname . ' ' . $contact->lastname); ?></strong>
                                        <small><?= e($contact->email); ?></small>
                                    </span>
                                </li>

                                <li class="divider"></li>

                                <li>
                                    <a href="<?= site_url('clients/profile'); ?>">
                                        <span class="mk43-menu-icon"><i class="fa-regular fa-user"></i></span>
                                        <span><?= _l('clients_nav_profile'); ?></span>
                                    </a>
                                </li>

                                <?php if ($contact->is_primary == 1) { ?>
                                    <?php if (can_loggged_in_user_manage_contacts()) { ?>
                                        <li>
                                            <a href="<?= site_url('contacts'); ?>">
                                                <span class="mk43-menu-icon"><i class="fa-regular fa-address-book"></i></span>
                                                <span><?= _l('clients_nav_contacts'); ?></span>
                                            </a>
                                        </li>
                                    <?php } ?>

                                    <li>
                                        <a href="<?= site_url('clients/company'); ?>">
                                            <span class="mk43-menu-icon"><i class="fa-regular fa-building"></i></span>
                                            <span><?= _l('client_company_info'); ?></span>
                                        </a>
                                    </li>
                                <?php } ?>

                                <?php if (can_logged_in_contact_update_credit_card()) { ?>
                                    <li>
                                        <a href="<?= site_url('clients/credit_card'); ?>">
                                            <span class="mk43-menu-icon"><i class="fa-regular fa-credit-card"></i></span>
                                            <span><?= _l('credit_card'); ?></span>
                                        </a>
                                    </li>
                                <?php } ?>

                                <?php if (is_gdpr() && get_option('show_gdpr_in_customers_menu') == '1') { ?>
                                    <li>
                                        <a href="<?= site_url('clients/gdpr'); ?>">
                                            <span class="mk43-menu-icon"><i class="fa-solid fa-shield-halved"></i></span>
                                            <span><?= _l('gdpr_short'); ?></span>
                                        </a>
                                    </li>
                                <?php } ?>

                                <li>
                                    <a href="<?= site_url('clients/announcements'); ?>">
                                        <span class="mk43-menu-icon"><i class="fa-regular fa-bell"></i></span>
                                        <span><?= _l('announcements'); ?></span>
                                        <?php if ($total_undismissed_announcements != 0) { ?>
                                            <span class="badge pull-right"><?= e($total_undismissed_announcements); ?></span>
                                        <?php } ?>
                                    </a>
                                </li>

                                <?php if (! is_language_disabled()) { ?>
                                    <li class="dropdown-submenu pull-left customers-nav-item-languages">
                                        <a href="#" tabindex="-1">
                                            <span class="mk43-menu-icon"><i class="fa-solid fa-globe"></i></span>
                                            <span><?= _l('language'); ?></span>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-left">
                                            <li class="<?= get_contact_language() == '' ? 'active' : ''; ?>">
                                                <a href="<?= site_url('clients/change_language'); ?>">
                                                    <?= _l('system_default_string'); ?>
                                                </a>
                                            </li>
                                            <?php foreach ($this->app->get_available_languages() as $user_lang) { ?>
                                                <li <?= get_contact_language() == $user_lang ? 'class="active"' : ''; ?>>
                                                    <a href="<?= site_url('clients/change_language/' . $user_lang); ?>">
                                                        <?= e(ucfirst($user_lang)); ?>
                                                    </a>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    </li>
                                <?php } ?>

                                <?= hooks()->do_action('customers_navigation_before_logout'); ?>

                                <li class="divider"></li>

                                <li class="mk43-logout">
                                    <a href="<?= site_url('authentication/logout'); ?>">
                                        <span class="mk43-menu-icon"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
                                        <span><?= _l('clients_nav_logout'); ?></span>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <?php hooks()->do_action('customers_navigation_after_profile'); ?>
                    </ul>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="mk43-progress-line" aria-hidden="true"></div>
</nav>
