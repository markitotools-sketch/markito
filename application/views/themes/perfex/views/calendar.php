<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<section class="mk43-calendar-hero mk4-cursor-glow">
    <div>
        <div class="mk4-kicker">PLANNING</div>
        <h1><?= _l('calendar'); ?></h1>
        <p>See project dates, deadlines and events in one focused planning workspace.</p>
    </div>
    <div class="mk43-calendar-hero-icon">
        <i class="fa-regular fa-calendar"></i>
    </div>
</section>

<section class="mk43-calendar-card">
    <div class="mk43-section-head">
        <span class="mk43-section-icon"><i class="fa-regular fa-calendar-days"></i></span>
        <div><strong><?= _l('calendar'); ?></strong><small>Your schedule and important dates</small></div>
    </div>
    <div class="mk43-calendar-shell">
        <div id="calendar"></div>
    </div>
</section>
