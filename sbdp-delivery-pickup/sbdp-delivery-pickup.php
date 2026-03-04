<?php
/**
 * Plugin Name:       SBDP Delivery & Pickup
 * Plugin URI:        https://github.com/syedfaizali/sbdp-delivery-pickup
 * Description:       Adds a configurable Delivery & Pickup widget to the WooCommerce cart, checkout and order management pages. Includes admin settings, booking calendar, time slots and capacity limits.
 * Version:           1.0.0
 * Author:            Syed Faiz Ali
 * Author URI:        https://github.com/syedfaizali
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sbdp-delivery-pickup
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
/* ==========================================================================
   DELIVERY & PICKUP — ADMIN SETTINGS PAGE
   ========================================================================== */

/**
 * Register admin menu page under WooCommerce.
 */
function sbdp_register_admin_menu() {
    add_submenu_page(
        'woocommerce',
        __( 'Delivery & Pickup', 'sbdp-delivery-pickup' ),
        __( 'Delivery & Pickup', 'sbdp-delivery-pickup' ),
        'manage_woocommerce',
        'sb-delivery-pickup',
        'sbdp_admin_page_html'
    );
    add_submenu_page(
        'woocommerce',
        __( 'Booking Calendar', 'sbdp-delivery-pickup' ),
        __( 'Booking Calendar', 'sbdp-delivery-pickup' ),
        'manage_woocommerce',
        'sbdp-booking-calendar',
        'sbdp_booking_calendar_html'
    );
}
add_action( 'admin_menu', 'sbdp_register_admin_menu' );

/**
 * Register all settings.
 */
function sbdp_register_settings() {
    // General
    register_setting( 'sbdp_general', 'sbdp_enable_delivery',   array( 'sanitize_callback' => 'absint',            'default' => 1 ) );
    register_setting( 'sbdp_general', 'sbdp_enable_pickup',     array( 'sanitize_callback' => 'absint',            'default' => 1 ) );
    register_setting( 'sbdp_general', 'sbdp_default_method',    array( 'sanitize_callback' => 'sanitize_text_field','default' => 'pickup' ) );
    register_setting( 'sbdp_general', 'sbdp_min_advance_days',  array( 'sanitize_callback' => 'absint',            'default' => 1 ) );
    register_setting( 'sbdp_general', 'sbdp_section_title',     array( 'sanitize_callback' => 'sanitize_text_field','default' => 'Delivery & Pickup Options' ) );

    // Pickup locations (serialised array)
    register_setting( 'sbdp_locations', 'sbdp_pickup_locations', array( 'sanitize_callback' => 'sbdp_sanitize_locations', 'default' => array() ) );

    // Time slots
    register_setting( 'sbdp_timeslots', 'sbdp_time_start',    array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '06:30' ) );
    register_setting( 'sbdp_timeslots', 'sbdp_time_end',      array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '21:00' ) );
    register_setting( 'sbdp_timeslots', 'sbdp_time_interval', array( 'sanitize_callback' => 'absint',              'default' => 10 ) );
    register_setting( 'sbdp_timeslots', 'sbdp_disable_dates', array( 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ) );

    // Booking capacity
    register_setting( 'sbdp_booking', 'sbdp_max_bookings_per_date', array( 'sanitize_callback' => 'absint', 'default' => 0 ) );
    // Date-specific capacity overrides
    register_setting( 'sbdp_booking', 'sbdp_date_specific_capacity', array( 'sanitize_callback' => 'sbdp_sanitize_date_capacities', 'default' => array() ) );
}
add_action( 'admin_init', 'sbdp_register_settings' );

/**
 * Sanitize the pickup locations array.
 */
function sbdp_sanitize_locations( $raw ) {
    if ( ! is_array( $raw ) ) {
        return array();
    }
    $clean = array();
    foreach ( $raw as $item ) {
        $label   = isset( $item['label'] )   ? sanitize_text_field( $item['label'] )   : '';
        $address = isset( $item['address'] ) ? sanitize_text_field( $item['address'] ) : '';
        $phone   = isset( $item['phone'] )   ? sanitize_text_field( $item['phone'] )   : '';
        $hours   = isset( $item['hours'] )   ? sanitize_text_field( $item['hours'] )   : '';
        if ( $label !== '' ) {
            $clean[] = compact( 'label', 'address', 'phone', 'hours' );
        }
    }
    return $clean;
}

/**
 * Sanitize date-specific capacity overrides: array of 'Y-m-d' => absint.
 */
function sbdp_sanitize_date_capacities( $raw ) {
    if ( ! is_array( $raw ) ) return array();
    $clean = array();
    foreach ( $raw as $date => $cap ) {
        $d = sanitize_text_field( $date );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
            $clean[ $d ] = absint( $cap );
        }
    }
    return $clean;
}

/**
 * Enqueue scripts & styles — must run on admin_enqueue_scripts so WP loads them in <head>.
 */
function sbdp_admin_enqueue_scripts( $hook ) {
    if ( $hook !== 'woocommerce_page_sb-delivery-pickup' ) {
        return;
    }
    // jQuery UI datepicker (bundled with WordPress)
    wp_enqueue_script( 'jquery-ui-datepicker' );
    // jQuery UI base theme from Google CDN
    wp_enqueue_style(
        'sbdp-jquery-ui',
        'https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css',
        array(),
        '1.13.2'
    );
}
add_action( 'admin_enqueue_scripts', 'sbdp_admin_enqueue_scripts' );

/**
 * Output inline admin CSS — admin_head is correct for raw <style> blocks.
 */
function sbdp_admin_head_styles() {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, array(
        'woocommerce_page_sb-delivery-pickup',
        'woocommerce_page_sbdp-booking-calendar',
    ), true ) ) {
        return;
    }
    ?>
    <style>
        .sbdp-wrap { max-width: 860px; }
        .sbdp-nav-tabs { display: flex; gap: 0; margin-bottom: 0; border-bottom: 1px solid #c3c4c7; }
        .sbdp-nav-tabs a {
            display: inline-block;
            padding: 8px 18px;
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
            border-bottom: none;
            margin-right: 4px;
            border-radius: 3px 3px 0 0;
            text-decoration: none;
            color: #50575e;
            font-size: 13px;
        }
        .sbdp-nav-tabs a.active { background: #fff; color: #1d2327; font-weight: 600; }
        .sbdp-nav-tabs .sbdp-nav-calendar-link {
            background: #f0fdf4;
            color: #00a32a;
            border-color: #00a32a;
            margin-left: auto;
        }
        .sbdp-nav-tabs .sbdp-nav-calendar-link:hover { background: #dcfce7; }
        .sbdp-tab-content { background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 24px; border-radius: 0 0 4px 4px; }
        .sbdp-tab-pane { display: none; }
        .sbdp-tab-pane.active { display: block; }
        .sbdp-section-title { font-size: 14px; font-weight: 600; margin: 0 0 16px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0; }
        .sbdp-table { width: 100%; border-collapse: collapse; }
        .sbdp-table th, .sbdp-table td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; text-align: left; font-size: 13px; }
        .sbdp-table th { background: #f9f9f9; font-weight: 600; }
        .sbdp-table input[type="text"] { width: 100%; }
        .sbdp-add-row { margin-top: 10px; }
        .sbdp-remove-row { color: #cc1818; cursor: pointer; background: none; border: none; font-size: 18px; line-height: 1; }
        .sbdp-form-table th { width: 200px; vertical-align: top; padding-top: 14px; }
        .sbdp-badge { display: inline-block; background: #00a32a; color: #fff; font-size: 10px; padding: 2px 7px; border-radius: 10px; vertical-align: middle; margin-left: 6px; }
        .sbdp-badge.disabled { background: #8c8f94; }

        /* Multi-date calendar */
        #sbdp-cal-wrap { display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start; margin-top: 4px; }
        #sbdp-inline-cal { display: inline-block; }
        #sbdp-inline-cal .ui-datepicker {
            width: 300px !important;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
            border-radius: 6px;
            border: 1px solid #c3c4c7;
            display: block !important;
        }
        #sbdp-inline-cal .ui-datepicker-header { border-radius: 6px 6px 0 0; background: #2c7be5; border-color: #2c7be5; color: #fff; }
        #sbdp-inline-cal .ui-datepicker-header .ui-datepicker-title { color: #fff; font-weight: 600; }
        #sbdp-inline-cal .ui-datepicker-header a { color: #fff; }
        #sbdp-inline-cal .ui-datepicker-calendar td.sbdp-selected a,
        #sbdp-inline-cal .ui-datepicker-calendar td.sbdp-selected span {
            background: #2c7be5 !important;
            color: #fff !important;
            border-color: #2c7be5 !important;
            border-radius: 50%;
            font-weight: 700;
        }
        #sbdp-tags-wrap { flex: 1 1 220px; }
        #sbdp-tags-wrap p.sbdp-tags-label { font-size: 12px; font-weight: 600; color: #777; text-transform: uppercase; letter-spacing: .4px; margin: 0 0 8px; }
        #sbdp-tags-list { display: flex; flex-wrap: wrap; gap: 6px; min-height: 32px; }
        .sbdp-tag { display: inline-flex; align-items: center; gap: 5px; background: #f0f6ff; border: 1px solid #2c7be5; color: #2c7be5; border-radius: 20px; padding: 3px 10px; font-size: 12px; font-weight: 600; }
        .sbdp-tag-remove { background: none; border: none; cursor: pointer; color: #2c7be5; font-size: 15px; line-height: 1; padding: 0; }
        .sbdp-tag-remove:hover { color: #cc1818; }
        #sbdp-tags-empty { font-size: 12px; color: #aaa; font-style: italic; }

        /* ── Date-Specific Capacity section ── */
        #sbdp-dsc-wrap { margin-top: 4px; }
        #sbdp-dsc-cal { display: inline-block; }
        #sbdp-dsc-cal .ui-datepicker {
            width: 300px !important;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
            border-radius: 6px;
            border: 1px solid #c3c4c7;
            display: block !important;
        }
        #sbdp-dsc-cal .ui-datepicker-header { border-radius: 6px 6px 0 0; background: #2c7be5; border-color: #2c7be5; color: #fff; }
        #sbdp-dsc-cal .ui-datepicker-header .ui-datepicker-title { color: #fff; font-weight: 600; }
        #sbdp-dsc-cal .ui-datepicker-header a { color: #fff; }
        #sbdp-dsc-info { display:flex; flex-direction:column; gap:12px; min-width:220px; }
        #sbdp-dsc-info label { font-size:13px; font-weight:600; display:block; margin-bottom:4px; }
        #sbdp-dsc-date-label { font-size:14px; color:#2c7be5; font-weight:700; }
        #sbdp-dsc-table { margin-top:12px; }
        #sbdp-dsc-table .sbdp-dsc-override-badge { font-size:10px; background:#e8f4fd; border:1px solid #2c7be5; color:#2c7be5; border-radius:8px; padding:1px 6px; margin-left:4px; }

        /* ── Booking Calendar page ── */
        #sbdp-cal-page .sbdp-cal-nav { display:flex; align-items:center; gap:16px; margin-bottom:20px; }
        #sbdp-cal-page .sbdp-cal-month-label { font-size:18px; font-weight:700; color:#1d2327; min-width:180px; text-align:center; }
        .sbdp-cal-layout { display:flex; gap:24px; align-items:flex-start; flex-wrap:wrap; }
        .sbdp-cal-grid-wrap { flex:0 0 auto; }
        .sbdp-cal-table { border-collapse:collapse; width:auto; }
        .sbdp-cal-table th { background:#2c7be5; color:#fff; font-size:12px; font-weight:600; padding:8px 12px; text-align:center; width:90px; }
        .sbdp-cal-table td { vertical-align:top; border:1px solid #e8e8e8; padding:0; width:90px; height:80px; background:#fff; }
        .sbdp-cal-empty { background:#fafafa !important; }
        .sbdp-cal-day { cursor:pointer; transition:background .15s; position:relative; }
        .sbdp-cal-day:hover { background:#f0f6ff !important; }
        .sbdp-cal-day.selected { background:#e8f4fd !important; outline:2px solid #2c7be5; outline-offset:-2px; }
        .sbdp-cal-day.has-bookings { background:#f0fdf4; }
        .sbdp-cal-day.is-full { background:#fff5f5; }
        .sbdp-cal-day.is-today .sbdp-day-num { background:#2c7be5; color:#fff; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; font-weight:700; }
        .sbdp-day-num { display:block; font-size:13px; font-weight:600; color:#1d2327; padding:6px 8px; }
        .sbdp-day-badge { display:inline-block; background:#00a32a; color:#fff; font-size:10px; font-weight:700; border-radius:10px; padding:1px 7px; margin:2px 6px; }
        .sbdp-day-badge.full { background:#cc1818; }
        .sbdp-cal-legend { display:flex; gap:16px; margin-top:10px; font-size:12px; flex-wrap:wrap; }
        .sbdp-cal-legend .leg { display:flex; align-items:center; gap:6px; }
        .sbdp-cal-legend .leg::before { content:''; display:inline-block; width:14px; height:14px; border-radius:3px; }
        .sbdp-cal-legend .leg.has-bookings::before { background:#f0fdf4; border:1px solid #00a32a; }
        .sbdp-cal-legend .leg.is-full::before { background:#fff5f5; border:1px solid #cc1818; }
        .sbdp-cal-legend .leg.is-today::before { background:#2c7be5; border-radius:50%; }
        /* Detail panel */
        .sbdp-cal-detail { flex:1 1 340px; min-width:300px; background:#fff; border:1px solid #e8e8e8; border-radius:6px; padding:20px; min-height:400px; }
        .sbdp-detail-placeholder { display:flex; flex-direction:column; align-items:center; justify-content:center; height:360px; color:#aaa; gap:10px; }
        .sbdp-detail-placeholder .dashicons { font-size:48px; width:48px; height:48px; color:#c3c4c7; }
        .sbdp-detail-placeholder p { font-size:14px; margin:0; }
        .sbdp-cal-detail h3 { font-size:15px; font-weight:700; margin:0 0 16px; padding-bottom:10px; border-bottom:2px solid #f0f0f0; }
        .sbdp-booking-card { border:1px solid #e8e8e8; border-radius:6px; margin-bottom:14px; overflow:hidden; }
        .sbdp-bc-header { display:flex; align-items:center; gap:10px; background:#f9f9f9; padding:10px 14px; border-bottom:1px solid #e8e8e8; }
        .sbdp-bc-num { font-size:11px; font-weight:700; background:#2c7be5; color:#fff; border-radius:50%; width:20px; height:20px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
        .sbdp-bc-order { font-size:13px; font-weight:600; color:#2c7be5; text-decoration:none; flex:1; }
        .sbdp-bc-order:hover { text-decoration:underline; }
        .sbdp-bc-status { font-size:10px; font-weight:600; background:#e8f4fd; color:#1d7fb5; border-radius:10px; padding:2px 9px; text-transform:uppercase; }
        .sbdp-bc-body { padding:12px 14px; display:flex; flex-direction:column; gap:6px; }
        .sbdp-bc-row { display:flex; gap:8px; font-size:13px; align-items:baseline; }
        .sbdp-bc-label { font-weight:600; color:#555; min-width:80px; flex-shrink:0; font-size:11px; text-transform:uppercase; letter-spacing:.3px; }
    </style>
    <?php
}
add_action( 'admin_head', 'sbdp_admin_head_styles' );

/**
 * Enqueue jQuery UI datepicker + theme on the front-end cart/checkout pages.
 */
function sbdp_frontend_enqueue_scripts() {
    // No external dependencies needed — calendar is pure vanilla JS
}
add_action( 'wp_enqueue_scripts', 'sbdp_frontend_enqueue_scripts' );

/**
 * Render the admin settings page.
 */
function sbdp_admin_page_html() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

    // Saved values
    $enable_delivery  = get_option( 'sbdp_enable_delivery', 1 );
    $enable_pickup    = get_option( 'sbdp_enable_pickup', 1 );
    $default_method   = get_option( 'sbdp_default_method', 'pickup' );
    $min_days         = get_option( 'sbdp_min_advance_days', 1 );
    $section_title    = get_option( 'sbdp_section_title', 'Delivery & Pickup Options' );
    $locations        = get_option( 'sbdp_pickup_locations', array() );
    $time_start       = get_option( 'sbdp_time_start', '06:30' );
    $time_end         = get_option( 'sbdp_time_end', '21:00' );
    $time_interval    = get_option( 'sbdp_time_interval', 10 );
    $disable_dates    = get_option( 'sbdp_disable_dates', '' );

    $tab_groups = array(
        'general'   => 'sbdp_general',
        'locations' => 'sbdp_locations',
        'timeslots' => 'sbdp_timeslots',
        'booking'   => 'sbdp_booking',
    );
    $settings_group = $tab_groups[ $current_tab ] ?? 'sbdp_general';
    $page_url = admin_url( 'admin.php?page=sb-delivery-pickup' );
    ?>
    <div class="wrap sbdp-wrap">
        <h1><?php esc_html_e( 'Delivery & Pickup Settings', 'sbdp-delivery-pickup' ); ?></h1>

        <?php settings_errors( 'sbdp_messages' ); ?>

        <!-- Tab nav -->
        <div class="sbdp-nav-tabs">
            <a href="<?php echo esc_url( $page_url . '&tab=general' ); ?>"
               class="<?php echo $current_tab === 'general' ? 'active' : ''; ?>">
                <?php esc_html_e( 'General', 'sbdp-delivery-pickup' ); ?>
            </a>
            <a href="<?php echo esc_url( $page_url . '&tab=locations' ); ?>"
               class="<?php echo $current_tab === 'locations' ? 'active' : ''; ?>">
                <?php esc_html_e( 'Pickup Locations', 'sbdp-delivery-pickup' ); ?>
            </a>
            <a href="<?php echo esc_url( $page_url . '&tab=timeslots' ); ?>"
               class="<?php echo $current_tab === 'timeslots' ? 'active' : ''; ?>">
                <?php esc_html_e( 'Time Slots', 'sbdp-delivery-pickup' ); ?>
            </a>
            <a href="<?php echo esc_url( $page_url . '&tab=booking' ); ?>"
               class="<?php echo $current_tab === 'booking' ? 'active' : ''; ?>">
                <?php esc_html_e( 'Booking Capacity', 'sbdp-delivery-pickup' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sbdp-booking-calendar' ) ); ?>"
               class="sbdp-nav-calendar-link">
                &#128197; <?php esc_html_e( 'Booking Calendar', 'sbdp-delivery-pickup' ); ?>
            </a>
        </div>

        <div class="sbdp-tab-content">
            <form method="post" action="options.php">
                <?php settings_fields( $settings_group ); ?>

                <!-- ======= GENERAL ======= -->
                <?php if ( $current_tab === 'general' ) : ?>
                <p class="sbdp-section-title"><?php esc_html_e( 'General Settings', 'sbdp-delivery-pickup' ); ?></p>
                <table class="form-table sbdp-form-table">
                    <tr>
                        <th><?php esc_html_e( 'Section Title', 'sbdp-delivery-pickup' ); ?></th>
                        <td>
                            <input type="text" name="sbdp_section_title"
                                   value="<?php echo esc_attr( $section_title ); ?>"
                                   class="regular-text">
                            <p class="description"><?php esc_html_e( 'Heading shown above the options on the cart page.', 'sbdp-delivery-pickup' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Enable Delivery', 'sbdp-delivery-pickup' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sbdp_enable_delivery" value="1"
                                       <?php checked( $enable_delivery, 1 ); ?>>
                                <?php esc_html_e( 'Show Delivery option on the cart', 'sbdp-delivery-pickup' ); ?>
                                <span class="sbdp-badge <?php echo $enable_delivery ? '' : 'disabled'; ?>">
                                    <?php echo $enable_delivery ? 'ON' : 'OFF'; ?>
                                </span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Enable Pickup', 'sbdp-delivery-pickup' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sbdp_enable_pickup" value="1"
                                       <?php checked( $enable_pickup, 1 ); ?>>
                                <?php esc_html_e( 'Show Pick Up option on the cart', 'sbdp-delivery-pickup' ); ?>
                                <span class="sbdp-badge <?php echo $enable_pickup ? '' : 'disabled'; ?>">
                                    <?php echo $enable_pickup ? 'ON' : 'OFF'; ?>
                                </span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Default Method', 'sbdp-delivery-pickup' ); ?></th>
                        <td>
                            <select name="sbdp_default_method">
                                <option value="pickup"   <?php selected( $default_method, 'pickup' ); ?>><?php esc_html_e( 'Pick Up', 'sbdp-delivery-pickup' ); ?></option>
                                <option value="delivery" <?php selected( $default_method, 'delivery' ); ?>><?php esc_html_e( 'Delivery', 'sbdp-delivery-pickup' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Which method is pre-selected when customer opens the cart.', 'sbdp-delivery-pickup' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Minimum Advance Days', 'sbdp-delivery-pickup' ); ?></th>
                        <td>
                            <input type="number" name="sbdp_min_advance_days"
                                   value="<?php echo esc_attr( $min_days ); ?>"
                                   min="0" max="30" style="width:80px;">
                            <p class="description"><?php esc_html_e( 'Earliest selectable date = today + this many days.', 'sbdp-delivery-pickup' ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- ======= PICKUP LOCATIONS ======= -->
                <?php elseif ( $current_tab === 'locations' ) : ?>
                <p class="sbdp-section-title"><?php esc_html_e( 'Pickup Locations', 'sbdp-delivery-pickup' ); ?></p>
                <p class="description" style="margin-bottom:14px;">
                    <?php esc_html_e( 'Add all the locations where customers can pick up their orders. These appear in the dropdown on the cart page.', 'sbdp-delivery-pickup' ); ?>
                </p>
                <table class="sbdp-table" id="sbdp-locations-table">
                    <thead>
                        <tr>
                            <th style="width:22%;"><?php esc_html_e( 'Location Name', 'sbdp-delivery-pickup' ); ?></th>
                            <th style="width:28%;"><?php esc_html_e( 'Address', 'sbdp-delivery-pickup' ); ?></th>
                            <th style="width:18%;"><?php esc_html_e( 'Phone', 'sbdp-delivery-pickup' ); ?></th>
                            <th style="width:24%;"><?php esc_html_e( 'Hours (e.g. Mon–Fri 8am–5pm)', 'sbdp-delivery-pickup' ); ?></th>
                            <th style="width:8%;"></th>
                        </tr>
                    </thead>
                    <tbody id="sbdp-locations-body">
                        <?php if ( ! empty( $locations ) ) : ?>
                            <?php foreach ( $locations as $i => $loc ) : ?>
                            <tr>
                                <td><input type="text" name="sbdp_pickup_locations[<?php echo $i; ?>][label]"   value="<?php echo esc_attr( $loc['label'] ); ?>"   placeholder="e.g. CBD Store"></td>
                                <td><input type="text" name="sbdp_pickup_locations[<?php echo $i; ?>][address]" value="<?php echo esc_attr( $loc['address'] ); ?>" placeholder="123 Main St"></td>
                                <td><input type="text" name="sbdp_pickup_locations[<?php echo $i; ?>][phone]"   value="<?php echo esc_attr( $loc['phone'] ); ?>"   placeholder="+1 555 0000"></td>
                                <td><input type="text" name="sbdp_pickup_locations[<?php echo $i; ?>][hours]"   value="<?php echo esc_attr( $loc['hours'] ?? '' ); ?>"   placeholder="Mon–Fri 8am–5pm"></td>
                                <td><button type="button" class="sbdp-remove-row" title="Remove">&times;</button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td><input type="text" name="sbdp_pickup_locations[0][label]"   value="" placeholder="e.g. CBD Store"></td>
                                <td><input type="text" name="sbdp_pickup_locations[0][address]" value="" placeholder="123 Main St"></td>
                                <td><input type="text" name="sbdp_pickup_locations[0][phone]"   value="" placeholder="+1 555 0000"></td>
                                <td><input type="text" name="sbdp_pickup_locations[0][hours]"   value="" placeholder="Mon–Fri 8am–5pm"></td>
                                <td><button type="button" class="sbdp-remove-row" title="Remove">&times;</button></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="button" class="button sbdp-add-row" id="sbdp-add-location">
                    + <?php esc_html_e( 'Add Location', 'sbdp-delivery-pickup' ); ?>
                </button>
                <script>
                (function(){
                    var tbody = document.getElementById('sbdp-locations-body');
                    var idx   = tbody.querySelectorAll('tr').length;

                    document.getElementById('sbdp-add-location').addEventListener('click', function(){
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td><input type="text" name="sbdp_pickup_locations['+idx+'][label]"   value="" placeholder="e.g. CBD Store"></td>' +
                            '<td><input type="text" name="sbdp_pickup_locations['+idx+'][address]" value="" placeholder="123 Main St"></td>' +
                            '<td><input type="text" name="sbdp_pickup_locations['+idx+'][phone]"   value="" placeholder="+1 555 0000"></td>' +
                            '<td><input type="text" name="sbdp_pickup_locations['+idx+'][hours]"   value="" placeholder="Mon–Fri 8am–5pm"></td>' +
                            '<td><button type="button" class="sbdp-remove-row" title="Remove">&times;</button></td>';
                        tbody.appendChild(tr);
                        idx++;
                        bindRemove(tr.querySelector('.sbdp-remove-row'));
                    });

                    function bindRemove(btn){
                        btn.addEventListener('click', function(){ this.closest('tr').remove(); });
                    }
                    document.querySelectorAll('.sbdp-remove-row').forEach(bindRemove);
                })();
                </script>

                <!-- ======= TIME SLOTS ======= -->
                <?php elseif ( $current_tab === 'timeslots' ) : ?>
                <p class="sbdp-section-title"><?php esc_html_e( 'Time Slot Settings', 'sbdp-delivery-pickup' ); ?></p>
                <table class="form-table sbdp-form-table">
                    <tr>
                        <th><?php esc_html_e( 'Start Time', 'sbdp-delivery-pickup' ); ?></th>
                        <td>
                            <input type="time" name="sbdp_time_start"
                                   value="<?php echo esc_attr( $time_start ); ?>">
                            <p class="description"><?php esc_html_e( 'First available time slot (24h format).', 'sbdp-delivery-pickup' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'End Time', 'sbdp-delivery-pickup' ); ?></th>
                        <td>
                            <input type="time" name="sbdp_time_end"
                                   value="<?php echo esc_attr( $time_end ); ?>">
                            <p class="description"><?php esc_html_e( 'Last available time slot (24h format).', 'sbdp-delivery-pickup' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Interval (minutes)', 'sbdp-delivery-pickup' ); ?></th>
                        <td>
                            <select name="sbdp_time_interval">
                                <?php foreach ( array( 10, 15, 20, 30, 60 ) as $mins ) : ?>
                                    <option value="<?php echo $mins; ?>" <?php selected( $time_interval, $mins ); ?>>
                                        <?php echo $mins; ?> <?php esc_html_e( 'minutes', 'sbdp-delivery-pickup' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th style="vertical-align:top;padding-top:18px;"><?php esc_html_e( 'Disabled Dates', 'sbdp-delivery-pickup' ); ?></th>
                        <td>
                            <!-- Hidden textarea stores dates for form POST -->
                            <textarea name="sbdp_disable_dates" id="sbdp_disable_dates_field"
                                      style="display:none;"><?php echo esc_textarea( $disable_dates ); ?></textarea>

                            <div id="sbdp-cal-wrap">
                                <!-- Inline calendar -->
                                <div id="sbdp-inline-cal"></div>

                                <!-- Selected date tags -->
                                <div id="sbdp-tags-wrap">
                                    <p class="sbdp-tags-label"><?php esc_html_e( 'Selected dates', 'sbdp-delivery-pickup' ); ?></p>
                                    <div id="sbdp-tags-list">
                                        <span id="sbdp-tags-empty"><?php esc_html_e( 'No dates selected', 'sbdp-delivery-pickup' ); ?></span>
                                    </div>
                                    <p class="description" style="margin-top:10px;">
                                        <?php esc_html_e( 'Click a date on the calendar to add or remove it. Saved as DD/MM/YYYY.', 'sbdp-delivery-pickup' ); ?>
                                    </p>
                                </div>
                            </div>

                            <script>
                            jQuery(document).ready(function($){
                                var field    = document.getElementById('sbdp_disable_dates_field');
                                var tagsList = document.getElementById('sbdp-tags-list');
                                var empty    = document.getElementById('sbdp-tags-empty');

                                // Parse existing saved dates
                                var saved    = field.value.trim();
                                var selected = saved ? saved.split('\n').map(function(d){ return d.trim(); }).filter(Boolean) : [];

                                function renderTags() {
                                    tagsList.innerHTML = '';
                                    if ( selected.length === 0 ) {
                                        tagsList.appendChild(empty);
                                        return;
                                    }
                                    selected.forEach(function(d){
                                        var tag = document.createElement('span');
                                        tag.className = 'sbdp-tag';
                                        tag.innerHTML = d + '<button type="button" class="sbdp-tag-remove" data-date="'+d+'" title="Remove">&times;</button>';
                                        tagsList.appendChild(tag);
                                    });
                                    tagsList.querySelectorAll('.sbdp-tag-remove').forEach(function(btn){
                                        btn.addEventListener('click', function(){
                                            var rm = this.getAttribute('data-date');
                                            selected = selected.filter(function(x){ return x !== rm; });
                                            syncField();
                                            renderTags();
                                            $('#sbdp-inline-cal').datepicker('refresh');
                                        });
                                    });
                                }

                                function syncField() {
                                    field.value = selected.join('\n');
                                }

                                // Convert Date → DD/MM/YYYY
                                function formatDate(dt) {
                                    var d = String(dt.getDate()).padStart(2,'0');
                                    var m = String(dt.getMonth()+1).padStart(2,'0');
                                    var y = dt.getFullYear();
                                    return d+'/'+m+'/'+y;
                                }
                                // Convert DD/MM/YYYY → Date object for sorting
                                function parseDate(str) {
                                    var p = str.split('/');
                                    return new Date( parseInt(p[2]), parseInt(p[1])-1, parseInt(p[0]) );
                                }

                                $('#sbdp-inline-cal').datepicker({
                                    inline     : true,
                                    showOtherMonths  : true,
                                    selectOtherMonths: true,
                                    dateFormat : 'dd/mm/yy',
                                    beforeShowDay: function(dt) {
                                        var fmt = formatDate(dt);
                                        var isSelected = selected.indexOf(fmt) !== -1;
                                        return [ true, isSelected ? 'sbdp-selected' : '', isSelected ? 'Disabled: ' + fmt : '' ];
                                    },
                                    onSelect: function(dateText) {
                                        var idx = selected.indexOf(dateText);
                                        if ( idx === -1 ) {
                                            selected.push(dateText);
                                            selected.sort(function(a,b){ return parseDate(a)-parseDate(b); });
                                        } else {
                                            selected.splice(idx, 1);
                                        }
                                        syncField();
                                        renderTags();
                                        $(this).datepicker('refresh');
                                    }
                                });

                                renderTags();
                            });
                            </script>
                        </td>
                    </tr>
                </table>

                <!-- ======= BOOKING CAPACITY ======= -->
                <?php elseif ( $current_tab === 'booking' ) :
                $max_bookings  = (int) get_option( 'sbdp_max_bookings_per_date', 0 );
                $date_specific = get_option( 'sbdp_date_specific_capacity', array() );
                $all_bookings  = get_option( 'sbdp_bookings_log', array() );
                // Sort by date descending
                krsort( $all_bookings );
                ?>
                <p class="sbdp-section-title"><?php esc_html_e( 'Booking Capacity Settings', 'sbdp-delivery-pickup' ); ?></p>
                <table class="form-table sbdp-form-table">
                    <tr>
                        <th><?php esc_html_e( 'Max Bookings Per Date', 'sbdp-delivery-pickup' ); ?></th>
                        <td>
                            <input type="number" name="sbdp_max_bookings_per_date"
                                   value="<?php echo esc_attr( $max_bookings ); ?>"
                                   min="0" style="width:90px;"> &nbsp;
                            <span class="description"><?php esc_html_e( 'Maximum orders allowed per date. Set to 0 for unlimited.', 'sbdp-delivery-pickup' ); ?></span>
                        </td>
                    </tr>
                </table>

                <!-- ======= DATE-SPECIFIC CAPACITY OVERRIDES ======= -->
                <p class="sbdp-section-title" style="margin-top:28px;"><?php esc_html_e( 'Date-Specific Capacity Overrides', 'sbdp-delivery-pickup' ); ?></p>
                <p class="description" style="margin-bottom:16px;">
                    <?php esc_html_e( 'Pick a date from the calendar and set a custom booking capacity for it. This overrides the global default for that date only.', 'sbdp-delivery-pickup' ); ?>
                </p>
                <div id="sbdp-dsc-wrap">
                    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;margin-bottom:20px;">
                        <div id="sbdp-dsc-cal"></div>
                        <div id="sbdp-dsc-info">
                            <div>
                                <label><?php esc_html_e( 'Selected Date', 'sbdp-delivery-pickup' ); ?></label>
                                <span id="sbdp-dsc-date-label"><?php esc_html_e( '— pick a date from calendar —', 'sbdp-delivery-pickup' ); ?></span>
                                <input type="hidden" id="sbdp-dsc-date-ymd" value="">
                            </div>
                            <div>
                                <label><?php esc_html_e( 'Custom Capacity for this Date', 'sbdp-delivery-pickup' ); ?></label>
                                <input type="number" id="sbdp-dsc-cap" min="1" value="1" style="width:90px;">
                            </div>
                            <button type="button" id="sbdp-dsc-add" class="button button-primary" disabled>
                                <?php esc_html_e( 'Add / Update Override', 'sbdp-delivery-pickup' ); ?>
                            </button>
                            <span id="sbdp-dsc-msg" style="font-size:12px;color:#00a32a;display:none;">&#10003; <?php esc_html_e( 'Saved!', 'sbdp-delivery-pickup' ); ?></span>
                        </div>
                    </div>

                    <?php
                    $dsc_list = get_option( 'sbdp_date_specific_capacity', array() );
                    krsort( $dsc_list );
                    ?>
                    <?php if ( ! empty( $dsc_list ) ) : ?>
                    <table class="sbdp-table" id="sbdp-dsc-table">
                        <thead>
                            <tr>
                                <th style="width:35%;"><?php esc_html_e( 'Date', 'sbdp-delivery-pickup' ); ?></th>
                                <th style="width:35%;"><?php esc_html_e( 'Custom Capacity', 'sbdp-delivery-pickup' ); ?></th>
                                <th style="width:30%;"><?php esc_html_e( 'Remove', 'sbdp-delivery-pickup' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="sbdp-dsc-tbody">
                        <?php foreach ( $dsc_list as $dsc_ymd => $dsc_cap ) :
                            $dsc_dt  = DateTime::createFromFormat( 'Y-m-d', $dsc_ymd );
                            $dsc_disp = $dsc_dt ? $dsc_dt->format( 'd/m/Y' ) : $dsc_ymd;
                        ?>
                        <tr id="sbdp-dsc-row-<?php echo esc_attr( $dsc_ymd ); ?>">
                            <td><strong><?php echo esc_html( $dsc_disp ); ?></strong></td>
                            <td style="color:#2c7be5;font-weight:700;"><?php echo (int) $dsc_cap; ?></td>
                            <td>
                                <button type="button" class="button button-small sbdp-dsc-del"
                                        data-date="<?php echo esc_attr( $dsc_ymd ); ?>"
                                        style="color:#cc1818;">
                                    <?php esc_html_e( 'Delete', 'sbdp-delivery-pickup' ); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                    <p id="sbdp-dsc-empty" style="color:#888;font-style:italic;"><?php esc_html_e( 'No date-specific overrides set yet.', 'sbdp-delivery-pickup' ); ?></p>
                    <?php endif; ?>
                </div>

                <script>
                jQuery(document).ready(function($){
                    var dscNonce = '<?php echo esc_js( wp_create_nonce( 'sbdp_date_capacity' ) ); ?>';
                    // Initialise inline calendar
                    $('#sbdp-dsc-cal').datepicker({
                        dateFormat       : 'yy-mm-dd',
                        inline           : true,
                        showOtherMonths  : true,
                        selectOtherMonths: true,
                        onSelect   : function( dateStr ) {
                            $('#sbdp-dsc-date-ymd').val( dateStr );
                            var p = dateStr.split('-');
                            $('#sbdp-dsc-date-label').text( p[2]+'/'+p[1]+'/'+p[0] ).css('color','#2c7be5');
                            $('#sbdp-dsc-add').prop('disabled', false);
                        }
                    });
                    // Add / Update override
                    $('#sbdp-dsc-add').on('click', function(){
                        var date     = $('#sbdp-dsc-date-ymd').val();
                        var capacity = parseInt( $('#sbdp-dsc-cap').val(), 10 );
                        if ( !date ) { alert('<?php echo esc_js( __( 'Please select a date from the calendar.', 'sbdp-delivery-pickup' ) ); ?>'); return; }
                        if ( isNaN(capacity) || capacity < 1 ) { alert('<?php echo esc_js( __( 'Please enter a valid capacity (minimum 1).', 'sbdp-delivery-pickup' ) ); ?>'); return; }
                        $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Saving…', 'sbdp-delivery-pickup' ) ); ?>');
                        $.post( ajaxurl, {
                            action   : 'sbdp_save_date_capacity',
                            nonce    : dscNonce,
                            date     : date,
                            capacity : capacity
                        }, function( res ) {
                            $('#sbdp-dsc-add').prop('disabled', false).text('<?php echo esc_js( __( 'Add / Update Override', 'sbdp-delivery-pickup' ) ); ?>');
                            if ( res.success ) {
                                $('#sbdp-dsc-msg').fadeIn().delay(1800).fadeOut();
                                setTimeout(function(){ location.reload(); }, 700);
                            } else {
                                alert( res.data || 'Error saving' );
                            }
                        });
                    });
                    // Delete override
                    $(document).on('click', '.sbdp-dsc-del', function(){
                        if ( !confirm('<?php echo esc_js( __( 'Remove this date override?', 'sbdp-delivery-pickup' ) ); ?>') ) return;
                        var btn  = $(this);
                        var date = btn.data('date');
                        btn.prop('disabled', true);
                        $.post( ajaxurl, {
                            action : 'sbdp_delete_date_capacity',
                            nonce  : dscNonce,
                            date   : date
                        }, function( res ) {
                            if ( res.success ) {
                                $('#sbdp-dsc-row-' + date).fadeOut(300, function(){ $(this).remove(); });
                            } else {
                                btn.prop('disabled', false);
                                alert( res.data || 'Error' );
                            }
                        });
                    });
                });
                </script>

                <p class="sbdp-section-title" style="margin-top:28px;"><?php esc_html_e( 'Bookings Per Date', 'sbdp-delivery-pickup' ); ?></p>
                <p class="description" style="margin-bottom:12px;">
                    <?php esc_html_e( 'Live count of orders booked per date. Adjust manually if needed.', 'sbdp-delivery-pickup' ); ?>
                </p>

                <?php if ( empty( $all_bookings ) ) : ?>
                    <p style="color:#888;"><?php esc_html_e( 'No bookings recorded yet.', 'sbdp-delivery-pickup' ); ?></p>
                <?php else : ?>
                <table class="sbdp-table" id="sbdp-bookings-log">
                    <thead>
                        <tr>
                            <th style="width:30%;"><?php esc_html_e( 'Date', 'sbdp-delivery-pickup' ); ?></th>
                            <th style="width:20%;"><?php esc_html_e( 'Booked', 'sbdp-delivery-pickup' ); ?></th>
                            <th style="width:25%;"><?php esc_html_e( 'Capacity', 'sbdp-delivery-pickup' ); ?></th>
                            <th style="width:25%;"><?php esc_html_e( 'Adjust', 'sbdp-delivery-pickup' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $all_bookings as $ymd => $count ) :
                        $dt           = DateTime::createFromFormat( 'Y-m-d', $ymd );
                        $display      = $dt ? $dt->format( 'd/m/Y' ) : $ymd;
                        $has_override = isset( $date_specific[ $ymd ] );
                        $eff_cap      = $has_override ? (int) $date_specific[ $ymd ] : $max_bookings;
                        $full         = $eff_cap > 0 && $count >= $eff_cap;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $display ); ?></strong></td>
                        <td>
                            <span style="font-size:15px;font-weight:700;color:<?php echo $full ? '#cc1818' : '#1e6b41'; ?>">
                                <?php echo (int) $count; ?>
                            </span>
                            <?php if ( $full ) : ?>
                                <span style="display:inline-block;background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;font-size:10px;padding:1px 7px;border-radius:10px;margin-left:4px;">FULL</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:<?php echo $has_override ? '#2c7be5' : '#888'; ?>;">
                            <?php if ( $eff_cap > 0 ) {
                                echo esc_html( $eff_cap );
                                if ( $has_override ) { echo ' <span class="sbdp-dsc-override-badge">custom</span>'; }
                            } else {
                                esc_html_e( 'Unlimited', 'sbdp-delivery-pickup' );
                            } ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small sbdp-adj-btn" data-date="<?php echo esc_attr( $ymd ); ?>" data-delta="-1">&#8722;</button>
                            &nbsp;
                            <button type="button" class="button button-small sbdp-adj-btn" data-date="<?php echo esc_attr( $ymd ); ?>" data-delta="1">&#43;</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <script>
                (function($){
                    $('.sbdp-adj-btn').on('click', function(){
                        var btn   = $(this);
                        var date  = btn.data('date');
                        var delta = parseInt( btn.data('delta') );
                        $.post( ajaxurl, {
                            action : 'sbdp_adjust_booking',
                            nonce  : '<?php echo esc_js( wp_create_nonce( 'sbdp_adjust_booking' ) ); ?>',
                            date   : date,
                            delta  : delta
                        }, function( res ){
                            if ( res.success ) {
                                location.reload();
                            } else {
                                alert( res.data || 'Error' );
                            }
                        });
                    });
                })(jQuery);
                </script>
                <?php endif; // end if(empty($all_bookings)) ?>

                <?php endif; // end if($current_tab === 'general') / elseif chain ?>

                <?php submit_button( __( 'Save Settings', 'sbdp-delivery-pickup' ) ); ?>
            </form>
        </div><!-- .sbdp-tab-content -->

        <p style="margin-top:16px;color:#888;font-size:12px;">
            <?php esc_html_e( 'Shortcode:', 'sbdp-delivery-pickup' ); ?>
            <code>[delivery_options]</code> &nbsp;|&nbsp;
            <?php esc_html_e( 'With default set to delivery:', 'sbdp-delivery-pickup' ); ?>
            <code>[delivery_options default="delivery"]</code>
        </p>
    </div>
    <?php
}

/* ==========================================================================
   BOOKING CALENDAR — ADMIN PAGE
   ========================================================================== */

/**
 * Render the Booking Calendar admin page.
 * Shows a full monthly calendar with per-date booking counts and customer details.
 */
function sbdp_booking_calendar_html() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) return;

    // Determine which month/year to display
    $year  = isset( $_GET['cal_year'] )  ? (int) $_GET['cal_year']  : (int) date( 'Y' );
    $month = isset( $_GET['cal_month'] ) ? (int) $_GET['cal_month'] : (int) date( 'n' );
    if ( $month < 1  ) { $month = 12; $year--; }
    if ( $month > 12 ) { $month = 1;  $year++; }

    $base      = admin_url( 'admin.php?page=sbdp-booking-calendar' );
    $prev_m    = $month === 1  ? 12       : $month - 1;
    $prev_y    = $month === 1  ? $year-1  : $year;
    $next_m    = $month === 12 ? 1        : $month + 1;
    $next_y    = $month === 12 ? $year+1  : $year;
    $prev_url  = add_query_arg( array( 'cal_year' => $prev_y, 'cal_month' => $prev_m ), $base );
    $next_url  = add_query_arg( array( 'cal_year' => $next_y, 'cal_month' => $next_m ), $base );
    $today_url = add_query_arg( array( 'cal_year' => date('Y'), 'cal_month' => date('n') ), $base );

    $month_label   = date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );
    $days_in_month = (int) date( 't', mktime( 0, 0, 0, $month, 1, $year ) );
    $start_dow     = (int) date( 'w', mktime( 0, 0, 0, $month, 1, $year ) ); // 0=Sun
    $first_day     = sprintf( '%04d-%02d-01', $year, $month );
    $last_day      = sprintf( '%04d-%02d-%02d', $year, $month, $days_in_month );
    $max_bookings       = (int) get_option( 'sbdp_max_bookings_per_date', 0 );
    $date_specific_caps = get_option( 'sbdp_date_specific_capacity', array() );

    // Query orders with _sbdp_date in this month
    $orders = wc_get_orders( array(
        'limit'      => -1,
        'status'     => array( 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-pending' ),
        'meta_query' => array(
            array(
                'key'     => '_sbdp_date',
                'value'   => array( $first_day, $last_day ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ),
        ),
    ) );

    // Group by Y-m-d
    $by_date = array();
    foreach ( $orders as $order ) {
        $d = $order->get_meta( '_sbdp_date' );
        if ( ! $d ) continue;
        $method_raw = $order->get_meta( '_sbdp_method' );
        $by_date[ $d ][] = array(
            'id'       => $order->get_id(),
            'name'     => $order->get_formatted_billing_full_name(),
            'email'    => $order->get_billing_email(),
            'phone'    => $order->get_billing_phone(),
            'method'   => $method_raw === 'delivery' ? __( 'Delivery', 'sbdp-delivery-pickup' ) : __( 'Pick Up', 'sbdp-delivery-pickup' ),
            'method_raw' => $method_raw,
            'location' => $order->get_meta( '_sbdp_location_label' ),
            'time'     => $order->get_meta( '_sbdp_time' ),
            'status'   => wc_get_order_status_name( $order->get_status() ),
            'status_raw' => $order->get_status(),
            'total'    => strip_tags( $order->get_formatted_order_total() ),
        );
    }
    ?>
    <div class="wrap sbdp-wrap" id="sbdp-cal-page">
        <h1>
            <?php esc_html_e( 'Booking Calendar', 'sbdp-delivery-pickup' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sb-delivery-pickup&tab=booking' ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Manage Capacity', 'sbdp-delivery-pickup' ); ?>
            </a>
        </h1>

        <!-- Month navigation -->
        <div class="sbdp-cal-nav">
            <a href="<?php echo esc_url( $prev_url ); ?>" class="button">&lsaquo; <?php esc_html_e( 'Prev', 'sbdp-delivery-pickup' ); ?></a>
            <span class="sbdp-cal-month-label"><?php echo esc_html( $month_label ); ?></span>
            <a href="<?php echo esc_url( $next_url ); ?>" class="button"><?php esc_html_e( 'Next', 'sbdp-delivery-pickup' ); ?> &rsaquo;</a>
            <a href="<?php echo esc_url( $today_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Today', 'sbdp-delivery-pickup' ); ?></a>
        </div>

        <div class="sbdp-cal-layout">

            <!-- Calendar grid -->
            <div class="sbdp-cal-grid-wrap">
                <table class="sbdp-cal-table">
                    <thead>
                        <tr>
                            <?php foreach ( array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ) as $dn ) : ?>
                            <th><?php echo esc_html( $dn ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $cell = 0;
                    echo '<tr>';
                    for ( $i = 0; $i < $start_dow; $i++ ) {
                        echo '<td class="sbdp-cal-empty"></td>';
                        $cell++;
                    }
                    for ( $day = 1; $day <= $days_in_month; $day++ ) :
                        $ymd      = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                        $bookings = isset( $by_date[ $ymd ] ) ? $by_date[ $ymd ] : array();
                        $count        = count( $bookings );
                        $eff_cap_cal  = isset( $date_specific_caps[ $ymd ] ) ? (int) $date_specific_caps[ $ymd ] : $max_bookings;
                        $is_full      = $eff_cap_cal > 0 && $count >= $eff_cap_cal;
                        $is_today     = ( $ymd === date( 'Y-m-d' ) );
                        $cls      = 'sbdp-cal-day';
                        if ( $count > 0 ) $cls .= ' has-bookings';
                        if ( $is_full )   $cls .= ' is-full';
                        if ( $is_today )  $cls .= ' is-today';
                        ?>
                        <td class="<?php echo esc_attr( $cls ); ?>" data-date="<?php echo esc_attr( $ymd ); ?>">
                            <span class="sbdp-day-num"><?php echo $day; ?></span>
                            <?php if ( $count > 0 ) : ?>
                                <span class="sbdp-day-badge<?php echo $is_full ? ' full' : ''; ?>">
                                    <?php echo $count; ?><?php echo $eff_cap_cal > 0 ? '/' . $eff_cap_cal : ''; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <?php
                        $cell++;
                        if ( $cell % 7 === 0 && $day < $days_in_month ) echo '</tr><tr>';
                    endfor;
                    $remaining = ( 7 - ( $cell % 7 ) ) % 7;
                    for ( $i = 0; $i < $remaining; $i++ ) echo '<td class="sbdp-cal-empty"></td>';
                    echo '</tr>';
                    ?>
                    </tbody>
                </table>

                <div class="sbdp-cal-legend">
                    <span class="leg has-bookings"><?php esc_html_e( 'Has bookings', 'sbdp-delivery-pickup' ); ?></span>
                    <?php if ( $max_bookings > 0 ) : ?>
                    <span class="leg is-full"><?php esc_html_e( 'Fully booked', 'sbdp-delivery-pickup' ); ?></span>
                    <?php endif; ?>
                    <span class="leg is-today"><?php esc_html_e( 'Today', 'sbdp-delivery-pickup' ); ?></span>
                </div>
            </div><!-- .sbdp-cal-grid-wrap -->

            <!-- Detail panel -->
            <div class="sbdp-cal-detail" id="sbdp-detail-panel">
                <div class="sbdp-detail-placeholder" id="sbdp-detail-placeholder">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <p><?php esc_html_e( 'Click a date to see bookings', 'sbdp-delivery-pickup' ); ?></p>
                </div>
                <div class="sbdp-detail-content" id="sbdp-detail-content" style="display:none;">
                    <h3 id="sbdp-detail-title"></h3>
                    <div id="sbdp-detail-list"></div>
                </div>
            </div>

        </div><!-- .sbdp-cal-layout -->
    </div>

    <script>
    var sbdpBookings       = <?php echo wp_json_encode( $by_date ); ?>;
    var sbdpOrderEditBase  = <?php echo wp_json_encode( admin_url( 'post.php?action=edit&post=' ) ); ?>;
    var sbdpL10n = {
        noBookings : <?php echo wp_json_encode( __( 'No bookings on this date.', 'sbdp-delivery-pickup' ) ); ?>,
        bookings   : <?php echo wp_json_encode( __( 'booking(s)', 'sbdp-delivery-pickup' ) ); ?>,
        order      : <?php echo wp_json_encode( __( 'Order', 'sbdp-delivery-pickup' ) ); ?>,
        customer   : <?php echo wp_json_encode( __( 'Customer', 'sbdp-delivery-pickup' ) ); ?>,
        email      : <?php echo wp_json_encode( __( 'Email', 'sbdp-delivery-pickup' ) ); ?>,
        phone      : <?php echo wp_json_encode( __( 'Phone', 'sbdp-delivery-pickup' ) ); ?>,
        method     : <?php echo wp_json_encode( __( 'Method', 'sbdp-delivery-pickup' ) ); ?>,
        location   : <?php echo wp_json_encode( __( 'Location', 'sbdp-delivery-pickup' ) ); ?>,
        time       : <?php echo wp_json_encode( __( 'Time', 'sbdp-delivery-pickup' ) ); ?>,
        total      : <?php echo wp_json_encode( __( 'Total', 'sbdp-delivery-pickup' ) ); ?>
    };
    (function($){
        var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        function formatDate(ymd) {
            var p = ymd.split('-');
            return months[ parseInt(p[1]) - 1 ] + ' ' + parseInt(p[2]) + ', ' + p[0];
        }
        function row(label, val) {
            return '<div class="sbdp-bc-row"><span class="sbdp-bc-label">' + label + '</span><span>' + val + '</span></div>';
        }
        $(document).on('click', '.sbdp-cal-day', function(){
            var date     = $(this).data('date');
            var bookings = sbdpBookings[date] || [];
            $('.sbdp-cal-day').removeClass('selected');
            $(this).addClass('selected');
            $('#sbdp-detail-title').text( formatDate(date) + '  —  ' + bookings.length + ' ' + sbdpL10n.bookings );
            var html = '';
            if ( bookings.length === 0 ) {
                html = '<p style="color:#888;padding:16px 0;">' + sbdpL10n.noBookings + '</p>';
            } else {
                $.each( bookings, function(i, b){
                    var icon = b.method_raw === 'delivery' ? '🚚' : '🏪';
                    html += '<div class="sbdp-booking-card">';
                    html += '<div class="sbdp-bc-header">';
                    html += '<span class="sbdp-bc-num">' + (i+1) + '</span>';
                    html += '<a href="' + sbdpOrderEditBase + b.id + '" target="_blank" class="sbdp-bc-order">' + sbdpL10n.order + ' #' + b.id + ' ↗</a>';
                    html += '<span class="sbdp-bc-status">' + b.status + '</span>';
                    html += '</div><div class="sbdp-bc-body">';
                    html += row( sbdpL10n.customer, '<strong>' + b.name + '</strong>' );
                    if (b.email) html += row( sbdpL10n.email, '<a href="mailto:'+b.email+'">'+b.email+'</a>' );
                    if (b.phone) html += row( sbdpL10n.phone, b.phone );
                    html += row( sbdpL10n.method, icon + ' ' + b.method );
                    if (b.location) html += row( sbdpL10n.location, b.location );
                    if (b.time)     html += row( sbdpL10n.time, b.time );
                    html += row( sbdpL10n.total, b.total );
                    html += '</div></div>';
                });
            }
            $('#sbdp-detail-list').html(html);
            $('#sbdp-detail-placeholder').hide();
            $('#sbdp-detail-content').show();
        });
    })(jQuery);
    </script>
    <?php
}

/* ==========================================================================
   DELIVERY & PICKUP — FRONT-END RENDER
   ========================================================================== */

/**
 * Core render function – returns the delivery/pickup HTML as a string.
 * Reads all settings from the database (configured in the admin page above).
 *
 * Shortcode:  [delivery_options]
 * Optional:   [delivery_options default="pickup|delivery"]
 */
function starbelly_child_delivery_options_html( $atts = array() ) {

    $atts = shortcode_atts( array(
        'default' => '',   // falls back to admin setting when empty
    ), $atts, 'delivery_options' );

    // --- Load settings ---
    $enable_delivery = get_option( 'sbdp_enable_delivery', 1 );
    $enable_pickup   = get_option( 'sbdp_enable_pickup',   1 );
    $default_method  = get_option( 'sbdp_default_method',  'pickup' );
    $min_days        = (int) get_option( 'sbdp_min_advance_days', 1 );
    $section_title   = get_option( 'sbdp_section_title', 'Delivery & Pickup Options' );
    $locations_raw   = get_option( 'sbdp_pickup_locations', array() );
    $time_start      = get_option( 'sbdp_time_start', '06:30' );
    $time_end        = get_option( 'sbdp_time_end',   '21:00' );
    $time_interval   = (int) get_option( 'sbdp_time_interval', 10 );
    $disable_dates   = get_option( 'sbdp_disable_dates', '' );

    // Shortcode attribute overrides admin default
    if ( in_array( $atts['default'], array( 'pickup', 'delivery' ), true ) ) {
        $default_method = $atts['default'];
    }

    // If neither method is enabled, show nothing
    if ( ! $enable_delivery && ! $enable_pickup ) {
        return '';
    }

    // Fall back to whichever is enabled if default is disabled
    if ( $default_method === 'delivery' && ! $enable_delivery ) {
        $default_method = 'pickup';
    }
    if ( $default_method === 'pickup' && ! $enable_pickup ) {
        $default_method = 'delivery';
    }

    // Build pickup location options
    $pickup_locations = array( '' => '— Select Location —' );
    if ( ! empty( $locations_raw ) ) {
        foreach ( $locations_raw as $loc ) {
            if ( ! empty( $loc['label'] ) ) {
                $key   = sanitize_title( $loc['label'] );
                $label = $loc['label'];
                if ( ! empty( $loc['address'] ) ) {
                    $label .= ' – ' . $loc['address'];
                }
                $pickup_locations[ $key ] = $label;
            }
        }
    } else {
        // Fallback demo locations when none configured
        $pickup_locations['branch_1'] = 'Branch 1 – Downtown';
        $pickup_locations['branch_2'] = 'Branch 2 – North Side';
    }

    // Build time options from admin settings
    $times = array( '' => 'Select Time' );
    $ts    = strtotime( $time_start );
    $te    = strtotime( $time_end );
    $ti    = max( 1, $time_interval ) * 60;
    if ( $ts && $te && $ts < $te ) {
        for ( $t = $ts; $t <= $te; $t += $ti ) {
            $lbl           = date( 'g:i a', $t );
            $times[ $lbl ] = $lbl;
        }
    }

    // Min date
    $min_date = date( 'Y-m-d', strtotime( "+{$min_days} days" ) );

    // Disabled dates as JS array (admin-set + fully-booked)
    $disabled_dates_js = '[]';
    $all_dd = array();
    if ( ! empty( $disable_dates ) ) {
        $all_dd = array_filter( array_map( 'trim', explode( "\n", $disable_dates ) ) );
    }
    // Merge fully-booked dates (respects date-specific capacity overrides)
    $max_bookings          = (int) get_option( 'sbdp_max_bookings_per_date', 0 );
    $fe_date_specific_caps = get_option( 'sbdp_date_specific_capacity', array() );
    if ( $max_bookings > 0 || ! empty( $fe_date_specific_caps ) ) {
        $bookings_log = get_option( 'sbdp_bookings_log', array() );
        foreach ( $bookings_log as $ymd => $count ) {
            $eff_cap = isset( $fe_date_specific_caps[ $ymd ] ) ? (int) $fe_date_specific_caps[ $ymd ] : $max_bookings;
            if ( $eff_cap > 0 && $count >= $eff_cap ) {
                $dt = DateTime::createFromFormat( 'Y-m-d', $ymd );
                if ( $dt ) {
                    $all_dd[] = $dt->format( 'd/m/Y' ); // DD/MM/YYYY as used by the JS check
                }
            }
        }
    }
    if ( ! empty( $all_dd ) ) {
        $disabled_dates_js = json_encode( array_values( array_unique( $all_dd ) ) );
    }

    // All booked dates map: DD/MM/YYYY => count (for calendar markers)
    $booked_map_all = array();
    $all_bookings_log = get_option( 'sbdp_bookings_log', array() );
    foreach ( $all_bookings_log as $_ymd => $_cnt ) {
        $_dt = DateTime::createFromFormat( 'Y-m-d', $_ymd );
        if ( $_dt && (int) $_cnt > 0 ) {
            $booked_map_all[ $_dt->format( 'd/m/Y' ) ] = (int) $_cnt;
        }
    }
    $booked_dates_js  = wp_json_encode( $booked_map_all );
    $max_bookings_js  = (int) get_option( 'sbdp_max_bookings_per_date', 0 );
    // Build date-specific capacity map for front-end: DD/MM/YYYY => capacity
    $fe_dsc_js_map = array();
    foreach ( $fe_date_specific_caps as $_feyymd => $_fecap ) {
        $_fedt = DateTime::createFromFormat( 'Y-m-d', $_feyymd );
        if ( $_fedt ) {
            $fe_dsc_js_map[ $_fedt->format( 'd/m/Y' ) ] = (int) $_fecap;
        }
    }
    $date_specific_caps_js = wp_json_encode( $fe_dsc_js_map );
    $min_date_display = ( new DateTime( $min_date ) )->format( 'd/m/Y' );

    // Enqueue jQuery UI datepicker for front-end — done via hook, not here
    // (see sbdp_frontend_enqueue_scripts below)

    // Unique instance ID
    static $instance = 0;
    $instance++;
    $uid = 'sb_do_' . $instance;

    $locs_json = wp_json_encode( $locations_raw );

    ob_start();
    ?>
    <div class="sbdp-widget" id="<?php echo esc_attr( $uid ); ?>">

        <?php if ( $instance === 1 ) : ?>
        <style>
        /* ---- SBDP Widget ---- */
        .sbdp-widget { font-family: inherit; margin-bottom: 24px; }
        .sbdp-widget * { box-sizing: border-box; }

        /* Section label */
        .sbdp-field-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #888;
            letter-spacing: .4px;
            margin-bottom: 8px;
        }

        /* Method buttons */
        .sbdp-methods { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .sbdp-method-btn {
            display: flex;
            align-items: center;
            gap: 9px;
            flex: 1 1 140px;
            padding: 11px 18px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #555;
            transition: border-color .18s, background .18s, color .18s;
            user-select: none;
            justify-content: center;
        }
        .sbdp-method-btn i { font-size: 20px; }
        .sbdp-method-btn input[type="radio"] { display: none; }
        .sbdp-method-btn.sbdp-active {
            border-color: #5ba65b;
            background: #f0f9ed;
            color: #3d7c3d;
        }

        /* Location select wrapper */
        .sbdp-select-wrap {
            position: relative;
            margin-bottom: 4px;
        }
        .sbdp-select-wrap select {
            width: 100%;
            appearance: none;
            -webkit-appearance: none;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            padding: 11px 40px 11px 14px;
            font-size: 14px;
            color: #333;
            background: #fff;
            cursor: pointer;
            outline: none;
            transition: border-color .18s;
        }
        .sbdp-select-wrap select:focus { border-color: #5ba65b; }
        .sbdp-select-wrap::after {
            content: '';
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 6px solid #888;
            pointer-events: none;
        }

        /* Location info card */
        .sbdp-loc-card {
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #555;
            line-height: 1.55;
            display: none;
        }
        .sbdp-loc-card.sbdp-show { display: block; }
        .sbdp-loc-card strong { display: block; color: #222; font-size: 14px; margin-bottom: 3px; }
        .sbdp-loc-card .sbdp-loc-hours {
            margin-top: 6px;
            font-size: 12px;
            color: #666;
        }
        .sbdp-loc-card .sbdp-loc-hours span { color: #5ba65b; font-weight: 600; }

        /* Location hidden row */
        .sbdp-location-row.sbdp-hidden { display: none; }

        /* Date + Time row */
        .sbdp-datetime-row { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; margin-top: 4px; }
        .sbdp-datetime-col { display: flex; flex-direction: column; flex: 1 1 160px; }

        /* Date input group */
        .sbdp-date-group {
            display: flex;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            /* overflow: hidden; */
            background: #fff;
        }
        .sbdp-date-group input[type="text"] {
            flex: 1;
            border: none;
            padding: 10px 12px;
            font-size: 14px;
            color: #333;
            background: transparent;
            outline: none;
            min-width: 0;
            cursor: pointer;
        }
        /* Custom vanilla JS calendar popup */
        .sbdp-cal-popup {
            display: none;
            position: absolute;
            z-index: 99999;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0,0,0,.15);
            padding: 14px;
            width: 280px;
            user-select: none;
        }
        .sbdp-cal-popup.open { display: block; }
        .sbdp-cal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .sbdp-cal-head button {
            background: none;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            line-height: 1;
            padding: 4px 10px;
            color: #555;
        }
        .sbdp-cal-head button:hover { background: #f0f0f0; }
        .sbdp-cal-head span { font-weight: 700; font-size: 14px; color: #1d2327; }
        .sbdp-cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }
        .sbdp-cal-dow {
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            color: #aaa;
            padding: 4px 0;
            text-transform: uppercase;
        }
        .sbdp-cal-cell {
            text-align: center;
            font-size: 13px;
            padding: 5px 2px;
            border-radius: 6px;
            cursor: pointer;
            position: relative;
            line-height: 1.2;
        }
        .sbdp-cal-cell:hover:not(.sbdp-cal-disabled):not(.sbdp-cal-empty) { background: #e8f0fe; }
        .sbdp-cal-cell.sbdp-cal-selected { background: #2c7be5; color: #fff; font-weight: 700; border-radius: 50%; }
        .sbdp-cal-cell.sbdp-cal-today { font-weight: 700; color: #2c7be5; }
        .sbdp-cal-cell.sbdp-cal-today.sbdp-cal-selected { color: #fff; }
        .sbdp-cal-cell.sbdp-cal-disabled { color: #ccc; cursor: default; text-decoration: line-through; }
        .sbdp-cal-cell.sbdp-cal-full { background: #fff0f0; color: #e05252; text-decoration: line-through; cursor: not-allowed; }
        .sbdp-cal-cell.sbdp-cal-booked { background: #f0fff4; color: #166534; }
        .sbdp-cal-cell.sbdp-cal-booked.sbdp-cal-selected { background: #2c7be5; color: #fff; }
        .sbdp-cal-badge {
            display: block;
            font-size: 8px;
            font-weight: 700;
            line-height: 1;
            margin-top: 1px;
        }
        .sbdp-cal-cell.sbdp-cal-booked .sbdp-cal-badge { color: #16a34a; }
        .sbdp-cal-cell.sbdp-cal-full .sbdp-cal-badge { color: #cc1818; }
        .sbdp-cal-cell.sbdp-cal-selected .sbdp-cal-badge { color: rgba(255,255,255,.85); }
        .sbdp-cal-cell.sbdp-cal-empty { cursor: default; }
        .sbdp-date-btn {
            background: #2c7be5;
            border: none;
            color: #fff;
            padding: 0 14px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
        }
        .sbdp-date-btn:hover { background: #1a63c7; }

        /* Time area */
        .sbdp-time-area { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 2px; }
        .sbdp-time-select-wrap {
            position: relative;
            display: inline-block;
        }
        .sbdp-time-select-wrap select {
            appearance: none;
            -webkit-appearance: none;
            border: 1.5px solid #ddd;
            border-radius: 20px;
            padding: 7px 34px 7px 14px;
            font-size: 13px;
            color: #555;
            background: #fff;
            cursor: pointer;
            outline: none;
            transition: border-color .18s;
        }
        .sbdp-time-select-wrap select:focus { border-color: #5ba65b; }
        .sbdp-time-select-wrap::after {
            content: '';
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 0; height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-top: 5px solid #888;
            pointer-events: none;
        }
        /* Time chips */
        .sbdp-time-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1.5px solid #ddd;
            border-radius: 20px;
            padding: 5px 10px 5px 13px;
            font-size: 13px;
            color: #333;
            background: #fff;
        }
        .sbdp-chip-remove {
            background: none;
            border: none;
            cursor: pointer;
            color: #e05252;
            font-size: 16px;
            line-height: 1;
            padding: 0;
            font-weight: 700;
        }
        .sbdp-chip-remove:hover { color: #b91c1c; }

        /* Hidden real time input */
        #<?php echo esc_attr($uid); ?>_time_hidden { display:none; }
        </style>
        <?php endif; ?>

        <!-- METHODS -->
        <div style="margin-bottom:18px;">
            <span class="sbdp-field-label"><?php esc_html_e( 'Methods', 'sbdp-delivery-pickup' ); ?></span>
            <div class="sbdp-methods">
                <?php if ( $enable_delivery ) : ?>
                <label class="sbdp-method-btn<?php echo $default_method === 'delivery' ? ' sbdp-active' : ''; ?>">
                    <input type="radio" name="<?php echo esc_attr( $uid ); ?>_option"
                           value="delivery" <?php checked( $default_method, 'delivery' ); ?>>
                    <i class="icon-delivery"></i>
                    <?php esc_html_e( 'Delivery', 'sbdp-delivery-pickup' ); ?>
                </label>
                <?php endif; ?>
                <?php if ( $enable_pickup ) : ?>
                <label class="sbdp-method-btn<?php echo $default_method === 'pickup' ? ' sbdp-active' : ''; ?>">
                    <input type="radio" name="<?php echo esc_attr( $uid ); ?>_option"
                           value="pickup" <?php checked( $default_method, 'pickup' ); ?>>
                    <i class="icon-pickup"></i>
                    <?php esc_html_e( 'Pick Up', 'sbdp-delivery-pickup' ); ?>
                </label>
                <?php endif; ?>
            </div>
        </div>

        <!-- PICKUP LOCATION -->
        <?php if ( $enable_pickup ) : ?>
        <div class="sbdp-location-row<?php echo $default_method === 'delivery' ? ' sbdp-hidden' : ''; ?>"
             data-loc-row="<?php echo esc_attr( $uid ); ?>">
            <span class="sbdp-field-label"><?php esc_html_e( 'Select Location', 'sbdp-delivery-pickup' ); ?></span>
            <div class="sbdp-select-wrap" style="margin-bottom:10px;">
                <select name="sb_pickup_location" id="<?php echo esc_attr( $uid ); ?>_location">
                    <?php foreach ( $pickup_locations as $val => $text ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $text ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Location info card -->
            <div class="sbdp-loc-card" id="<?php echo esc_attr( $uid ); ?>_loc_card"></div>
        </div>
        <?php endif; ?>

        <!-- DATE + TIME -->
        <div class="sbdp-datetime-row">
            <!-- Date -->
            <div class="sbdp-datetime-col">
                <span class="sbdp-field-label" id="<?php echo esc_attr( $uid ); ?>_date_label">
                    <?php echo $default_method === 'pickup'
                        ? esc_html__( 'Pickup Date', 'sbdp-delivery-pickup' )
                        : esc_html__( 'Delivery Date', 'sbdp-delivery-pickup' ); ?>
                </span>
                <div class="sbdp-date-group" style="position:relative;">
                    <input type="text"
                           id="<?php echo esc_attr( $uid ); ?>_date_display"
                           placeholder="<?php echo esc_attr__( 'Select a date…', 'sbdp-delivery-pickup' ); ?>"
                           autocomplete="off"
                           readonly>
                    <input type="hidden"
                           name="sb_delivery_date"
                           id="<?php echo esc_attr( $uid ); ?>_date">
                    <button type="button" class="sbdp-date-btn"
                            id="<?php echo esc_attr( $uid ); ?>_date_btn">
                        &#128197;
                    </button>
                    <!-- Custom calendar popup -->
                    <div class="sbdp-cal-popup" id="<?php echo esc_attr( $uid ); ?>_cal_popup">
                        <div class="sbdp-cal-head">
                            <button type="button" id="<?php echo esc_attr( $uid ); ?>_cal_prev">&#8249;</button>
                            <span id="<?php echo esc_attr( $uid ); ?>_cal_title"></span>
                            <button type="button" id="<?php echo esc_attr( $uid ); ?>_cal_next">&#8250;</button>
                        </div>
                        <div class="sbdp-cal-grid" id="<?php echo esc_attr( $uid ); ?>_cal_grid"></div>
                    </div>
                </div>
            </div>

            <!-- Time -->
            <div class="sbdp-datetime-col">
                <span class="sbdp-field-label"><?php esc_html_e( 'Time', 'sbdp-delivery-pickup' ); ?></span>
                <div class="sbdp-time-area" id="<?php echo esc_attr( $uid ); ?>_time_area">
                    <div class="sbdp-time-select-wrap">
                        <select id="<?php echo esc_attr( $uid ); ?>_time_picker">
                            <?php foreach ( $times as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Chips injected here by JS -->
                </div>
                <!-- Hidden input carries the real value for form POST -->
                <input type="hidden" name="sb_delivery_time" id="<?php echo esc_attr( $uid ); ?>_time_hidden">
            </div>
        </div>
    </div><!-- .sbdp-widget -->

    <script>
    jQuery(document).ready(function($){
        var uid           = '<?php echo esc_js( $uid ); ?>';
        var wrap          = document.getElementById(uid);
        var radios        = wrap.querySelectorAll('input[type="radio"]');
        var locRow        = wrap.querySelector('[data-loc-row]');
        var locSelect     = document.getElementById(uid + '_location');
        var locCard       = document.getElementById(uid + '_loc_card');
        var dateInput     = document.getElementById(uid + '_date');         // hidden Y-m-d
        var dateDisplay   = document.getElementById(uid + '_date_display'); // visible text
        var dateBtn       = document.getElementById(uid + '_date_btn');
        var dateLabel     = document.getElementById(uid + '_date_label');
        var timePicker    = document.getElementById(uid + '_time_picker');
        var timeHidden    = document.getElementById(uid + '_time_hidden');
        var timeArea      = document.getElementById(uid + '_time_area');
        var locsData      = <?php echo $locs_json; ?>;
        var disabledDates    = <?php echo $disabled_dates_js; ?>;
        var bookedDates       = <?php echo $booked_dates_js; ?>;
        var maxBookings       = <?php echo (int) $max_bookings_js; ?>;
        var dateSpecificCaps  = <?php echo $date_specific_caps_js; ?>; // DD/MM/YYYY => capacity overrides
        var minDateStr        = '<?php echo esc_js( $min_date_display ); ?>'; // DD/MM/YYYY
        var selectedTimes = [];

        /* ---- Helpers ---- */
        function ymdToDmY(ymd) {
            var p = ymd.split('-'); return p[2]+'/'+p[1]+'/'+p[0];
        }
        function dmYToYmd(dmy) {
            var p = dmy.split('/'); return p[2]+'-'+p[1]+'-'+p[0];
        }

        /* ---- Vanilla JS Calendar ---- */
        var calPopup   = document.getElementById(uid + '_cal_popup');
        var calGrid    = document.getElementById(uid + '_cal_grid');
        var calTitle   = document.getElementById(uid + '_cal_title');
        var calPrev    = document.getElementById(uid + '_cal_prev');
        var calNext    = document.getElementById(uid + '_cal_next');
        var calOpen    = false;
        var calYear    = 0;
        var calMonth   = 0; // 0-indexed
        var selectedYmd = '';

        var MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        var DAYS   = ['Su','Mo','Tu','We','Th','Fr','Sa'];

        // Parse min date from DD/MM/YYYY
        var minParts = minDateStr.split('/');
        var minD = new Date( parseInt(minParts[2]), parseInt(minParts[1])-1, parseInt(minParts[0]) );
        minD.setHours(0,0,0,0);

        function fmt2(n){ return n < 10 ? '0'+n : ''+n; }
        function dateToYmd(d){ return d.getFullYear()+'-'+fmt2(d.getMonth()+1)+'-'+fmt2(d.getDate()); }
        function dateToDmY(d){ return fmt2(d.getDate())+'/'+fmt2(d.getMonth()+1)+'/'+d.getFullYear(); }
        function ymdToDate(ymd){ var p=ymd.split('-'); return new Date(+p[0],+p[1]-1,+p[2]); }

        function renderCal() {
            calTitle.textContent = MONTHS[calMonth] + ', ' + calYear;
            var today = new Date(); today.setHours(0,0,0,0);
            var html = '';
            // Day-of-week headers
            DAYS.forEach(function(d){ html += '<div class="sbdp-cal-dow">'+d+'</div>'; });
            // Empty cells before 1st
            var firstDow = new Date(calYear, calMonth, 1).getDay();
            for (var i=0; i<firstDow; i++) html += '<div class="sbdp-cal-cell sbdp-cal-empty"></div>';
            // Day cells
            var daysInMonth = new Date(calYear, calMonth+1, 0).getDate();
            for (var day=1; day<=daysInMonth; day++) {
                var cellDate = new Date(calYear, calMonth, day);
                cellDate.setHours(0,0,0,0);
                var ymd  = dateToYmd(cellDate);
                var dmy  = dateToDmY(cellDate);
                var cls  = 'sbdp-cal-cell';
                var badge = '';
                var disabled = cellDate < minD;
                if ( disabledDates.indexOf(dmy) !== -1 ) {
                    cls += ' sbdp-cal-full'; disabled = true;
                    badge = '<span class="sbdp-cal-badge">✕</span>';
                } else if ( bookedDates[dmy] ) {
                    var cnt    = bookedDates[dmy];
                    var effCap = ( dateSpecificCaps[dmy] !== undefined ) ? dateSpecificCaps[dmy] : maxBookings;
                    if ( effCap > 0 && cnt >= effCap ) {
                        cls += ' sbdp-cal-full'; disabled = true;
                        badge = '<span class="sbdp-cal-badge">✕ '+cnt+(effCap?'/'+effCap:'')+'</span>';
                    } else {
                        cls += ' sbdp-cal-booked';
                        badge = '<span class="sbdp-cal-badge">'+cnt+(effCap?'/'+effCap:'')+'</span>';
                    }
                }
                if ( disabled && cls.indexOf('sbdp-cal-full') === -1 ) cls += ' sbdp-cal-disabled';
                if ( cellDate.getTime() === today.getTime() ) cls += ' sbdp-cal-today';
                if ( ymd === selectedYmd ) cls += ' sbdp-cal-selected';
                var attr = disabled ? '' : ' data-ymd="'+ymd+'" data-dmy="'+dmy+'"';
                html += '<div class="'+cls+'"'+attr+'>'+day+badge+'</div>';
            }
            calGrid.innerHTML = html;
            // Bind clicks
            calGrid.querySelectorAll('.sbdp-cal-cell[data-ymd]').forEach(function(el){
                el.addEventListener('click', function(){
                    var ymd = this.getAttribute('data-ymd');
                    var dmy = this.getAttribute('data-dmy');
                    selectedYmd = ymd;
                    dateInput.value = ymd;
                    // Display as DD/MM/YYYY
                    var p = ymd.split('-');
                    dateDisplay.value = fmt2(+p[2])+'/'+fmt2(+p[1])+'/'+p[0];
                    try { dateInput.dispatchEvent(new Event('change',{bubbles:true})); } catch(e){}
                    closeCalendar();
                    renderCal();
                });
            });
        }

        function openCalendar() {
            var now = new Date();
            if ( selectedYmd ) {
                var sd = ymdToDate(selectedYmd);
                calYear = sd.getFullYear(); calMonth = sd.getMonth();
            } else {
                calYear = now.getFullYear(); calMonth = now.getMonth();
            }
            renderCal();
            calPopup.classList.add('open');
            calOpen = true;
        }
        function closeCalendar() {
            calPopup.classList.remove('open');
            calOpen = false;
        }

        if ( dateDisplay ) {
            dateDisplay.addEventListener('click', function(){ calOpen ? closeCalendar() : openCalendar(); });
        }
        if ( dateBtn ) {
            dateBtn.addEventListener('click', function(e){
                e.preventDefault();
                calOpen ? closeCalendar() : openCalendar();
            });
        }
        if ( calPrev ) {
            calPrev.addEventListener('click', function(){
                calMonth--; if (calMonth < 0){ calMonth=11; calYear--; } renderCal();
            });
        }
        if ( calNext ) {
            calNext.addEventListener('click', function(){
                calMonth++; if (calMonth > 11){ calMonth=0; calYear++; } renderCal();
            });
        }
        // Close on outside click
        document.addEventListener('click', function(e){
            if ( calOpen && calPopup && !calPopup.contains(e.target) && e.target !== dateDisplay && e.target !== dateBtn ) {
                closeCalendar();
            }
        });

        /* ---- Method toggle ---- */
        function updateMethodUI() {
            var sel = wrap.querySelector('input[type="radio"]:checked');
            var method = sel ? sel.value : '';
            wrap.querySelectorAll('.sbdp-method-btn').forEach(function(btn){
                var radio = btn.querySelector('input[type="radio"]');
                btn.classList.toggle('sbdp-active', radio && radio.checked);
            });
            if ( locRow ) {
                locRow.classList.toggle('sbdp-hidden', method !== 'pickup');
            }
            if ( dateLabel ) {
                dateLabel.textContent = method === 'pickup'
                    ? '<?php echo esc_js( __( 'Pickup Date', 'sbdp-delivery-pickup' ) ); ?>'
                    : '<?php echo esc_js( __( 'Delivery Date', 'sbdp-delivery-pickup' ) ); ?>';
            }
        }
        radios.forEach(function(r){ r.addEventListener('change', updateMethodUI); });
        updateMethodUI();

        /* ---- Location info card ---- */
        function showLocCard(val) {
            if ( ! locCard ) return;
            var found = locsData ? locsData.find(function(l){
                return l.label && l.label.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'') === val;
            }) : null;
            if ( found ) {
                var html = '<strong>' + found.label + '</strong>';
                if ( found.address ) html += found.address;
                if ( found.phone )   html += ' <strong style="color:#333">P:</strong> ' + found.phone;
                if ( found.hours )   html += '<div class="sbdp-loc-hours">Hours: <span>' + found.hours + '</span></div>';
                locCard.innerHTML = html;
                locCard.classList.add('sbdp-show');
            } else {
                locCard.innerHTML = '';
                locCard.classList.remove('sbdp-show');
            }
        }
        if ( locSelect ) {
            locSelect.addEventListener('change', function(){ showLocCard(this.value); });
            showLocCard(locSelect.value);
        }

        /* ---- Time chips ---- */
        function renderChips() {
            // Remove existing chips (not the select wrapper)
            timeArea.querySelectorAll('.sbdp-time-chip').forEach(function(c){ c.remove(); });
            selectedTimes.forEach(function(t){
                var chip = document.createElement('span');
                chip.className = 'sbdp-time-chip';
                chip.innerHTML = t + '<button type="button" class="sbdp-chip-remove" data-t="'+t+'" title="Remove">&times;</button>';
                chip.querySelector('.sbdp-chip-remove').addEventListener('click', function(){
                    selectedTimes = selectedTimes.filter(function(x){ return x !== this.getAttribute('data-t'); }, this);
                    syncHidden();
                    renderChips();
                });
                timeArea.appendChild(chip);
            });
        }
        function syncHidden() {
            timeHidden.value = selectedTimes.join(',');
            // Dispatch a real DOM event so external listeners (cart AJAX sync) detect the change
            try { timeHidden.dispatchEvent( new Event('input', { bubbles: true }) ); } catch(e){}
        }
        if ( timePicker ) {
            timePicker.addEventListener('change', function(){
                var val = this.value;
                if ( val && selectedTimes.indexOf(val) === -1 ) {
                    selectedTimes.push(val);
                    syncHidden();
                    renderChips();
                }
                this.value = ''; // reset select back to placeholder
            });
        }

        /* ---- Restore from localStorage on page load ---- */
        function restoreFromStorage() {
            var stored;
            try { stored = JSON.parse( localStorage.getItem('sbdp_selection') || '{}' ); } catch(e) { return; }
            if ( ! stored || typeof stored !== 'object' ) return;

            // Method radio
            if ( stored.sb_delivery_option ) {
                var matchRadio = wrap.querySelector('input[type="radio"][value="' + stored.sb_delivery_option + '"]');
                if ( matchRadio ) {
                    matchRadio.checked = true;
                    updateMethodUI();
                }
            }

            // Location select
            if ( stored.sb_pickup_location && locSelect ) {
                locSelect.value = stored.sb_pickup_location;
                showLocCard( locSelect.value );
            }

            // Date
            if ( stored.sb_delivery_date && dateInput ) {
                dateInput.value = stored.sb_delivery_date;
                selectedYmd = stored.sb_delivery_date;
                // Update the display input (DD/MM/YYYY)
                if ( dateDisplay ) {
                    var p = stored.sb_delivery_date.split('-');
                    if ( p.length === 3 ) {
                        dateDisplay.value = fmt2(+p[2])+'/'+fmt2(+p[1])+'/'+p[0];
                    }
                }
            }

            // Time chips — parse comma-separated saved times
            if ( stored.sb_delivery_time && timeHidden ) {
                var times = stored.sb_delivery_time.split(',').map(function(t){ return t.trim(); }).filter(Boolean);
                times.forEach(function(t){
                    if ( selectedTimes.indexOf(t) === -1 ) selectedTimes.push(t);
                });
                // Set value directly without dispatching (avoids double-sync on load)
                timeHidden.value = selectedTimes.join(',');
                renderChips();
            }
        }
        restoreFromStorage();
    }); // end jQuery(document).ready
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: [delivery_options]
 * Optional:  [delivery_options default="delivery"]
 */
add_shortcode( 'delivery_options', 'starbelly_child_delivery_options_html' );

/**
 * Auto-display on the WooCommerce cart page (above the cart table).
 */
function starbelly_child_cart_delivery_options() {
    if ( is_cart() ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo starbelly_child_delivery_options_html();
    }
}
add_action( 'woocommerce_before_cart', 'starbelly_child_cart_delivery_options' );


/* ==========================================================================
   DELIVERY & PICKUP — SESSION, CHECKOUT, ORDER DETAILS & EMAILS
   ========================================================================== */

/**
 * 1. Save selections to WooCommerce session via AJAX whenever the customer
 *    changes any field on the cart page.
 */
function sbdp_save_session_ajax() {
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
        return;
    }
    $method   = isset( $_POST['sb_delivery_option'] ) ? sanitize_text_field( wp_unslash( $_POST['sb_delivery_option'] ) ) : '';
    $location = isset( $_POST['sb_pickup_location'] ) ? sanitize_text_field( wp_unslash( $_POST['sb_pickup_location'] ) ) : '';
    $date     = isset( $_POST['sb_delivery_date'] )   ? sanitize_text_field( wp_unslash( $_POST['sb_delivery_date'] ) )   : '';
    $time     = isset( $_POST['sb_delivery_time'] )   ? sanitize_text_field( wp_unslash( $_POST['sb_delivery_time'] ) )   : '';

    WC()->session->set( 'sbdp_method',   $method );
    WC()->session->set( 'sbdp_location', $location );
    WC()->session->set( 'sbdp_date',     $date );
    WC()->session->set( 'sbdp_time',     $time );

    wp_send_json_success();
}
add_action( 'wp_ajax_sbdp_save_session',        'sbdp_save_session_ajax' );
add_action( 'wp_ajax_nopriv_sbdp_save_session', 'sbdp_save_session_ajax' );

/**
 * 2a. On the CART page: persist selections into localStorage AND send to WC session via AJAX.
 */
function sbdp_cart_ajax_js() {
    if ( ! is_cart() ) {
        return;
    }
    ?>
    <script>
    (function($){
        var LS_KEY = 'sbdp_selection';

        function sbdpCollect() {
            var wrap = document.querySelector('.sbdp-widget');
            if ( ! wrap ) return null;

            var checked    = wrap.querySelector('input[type="radio"]:checked');
            var locSel     = wrap.querySelector('select[name="sb_pickup_location"]');
            var dateSel    = wrap.querySelector('input[name="sb_delivery_date"]');
            var timeHidden = wrap.querySelector('input[name="sb_delivery_time"]');

            return {
                sb_delivery_option : checked    ? checked.value    : '',
                sb_pickup_location : locSel     ? locSel.value     : '',
                sb_delivery_date   : dateSel    ? dateSel.value    : '',
                sb_delivery_time   : timeHidden ? timeHidden.value : ''
            };
        }

        function sbdpSync() {
            var data = sbdpCollect();
            if ( ! data ) return;

            // Save to localStorage so checkout page can read it
            try { localStorage.setItem( LS_KEY, JSON.stringify( data ) ); } catch(e){}

            // Also push to WC session via AJAX
            $.post( '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
                $.extend( { action: 'sbdp_save_session' }, data )
            );
        }

        $(document).on( 'change', '.sbdp-widget input[type="radio"]',               function(){ sbdpSync(); sbdpClearError(); } );
        $(document).on( 'change', '.sbdp-widget select[name="sb_pickup_location"]', function(){ sbdpSync(); sbdpClearError(); } );
        $(document).on( 'change', '.sbdp-widget input[name="sb_delivery_date"]',    function(){ sbdpSync(); sbdpClearError(); } );
        $(document).on( 'input',  '.sbdp-widget input[name="sb_delivery_time"]',    function(){ sbdpSync(); sbdpClearError(); } );
        $(document).on( 'click',  '.sbdp-chip-remove',                               function(){ setTimeout( function(){ sbdpSync(); sbdpClearError(); }, 50 ); } );

        // Sync on page load to capture default state
        $(window).on( 'load', sbdpSync );

        /* ---- Cart validation: block checkout button if fields missing ---- */

        var ERROR_STYLE = 'background:#fef2f2;border:2px solid #fca5a5;border-radius:8px;padding:14px 18px;margin-bottom:16px;color:#b91c1c;font-size:14px;line-height:1.7;';
        var FIELD_ERR_STYLE = '2px solid #f87171';

        function sbdpShowError( lines, triggerBtn ) {
            // --- Error box near the widget ---
            var errBox = document.getElementById('sbdp-cart-error');
            if ( ! errBox ) {
                errBox = document.createElement('div');
                errBox.id = 'sbdp-cart-error';
                errBox.setAttribute('role', 'alert');
                var widget = document.querySelector('.sbdp-widget');
                if ( widget ) widget.parentNode.insertBefore( errBox, widget );
            }
            errBox.style.cssText = ERROR_STYLE;
            errBox.innerHTML = '<strong style="display:block;margin-bottom:6px;">⚠ Please complete your order details before proceeding:</strong>'
                + '<ul style="margin:4px 0 0 18px;padding:0;">' + lines.map(function(l){ return '<li>'+l+'</li>'; }).join('') + '</ul>';

            // --- Compact notice right above the checkout button (classic & Blocks cart) ---
            // Resolve the anchor element: classic cart uses .wc-proceed-to-checkout,
            // WC Blocks uses .wc-block-cart__submit-container or the button's own parent.
            var anchorEl = document.querySelector('.wc-proceed-to-checkout')
                        || document.querySelector('.wc-block-cart__submit-container')
                        || ( triggerBtn ? triggerBtn.closest('div, form, nav') : null );

            if ( anchorEl ) {
                var btnNotice = document.getElementById('sbdp-btn-notice');
                if ( ! btnNotice ) {
                    btnNotice = document.createElement('p');
                    btnNotice.id = 'sbdp-btn-notice';
                    anchorEl.parentNode.insertBefore( btnNotice, anchorEl );
                }
                btnNotice.style.cssText = 'background:#fef2f2;border:2px solid #fca5a5;border-radius:6px;padding:10px 14px;color:#b91c1c;font-size:13px;margin-bottom:8px;text-align:center;';
                btnNotice.innerHTML = '⚠ ' + lines[0];
            }

            // Scroll to widget error box
            errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function sbdpHighlightField( selector, isError ) {
            var wrap = document.querySelector('.sbdp-widget');
            if ( ! wrap ) return;
            var el = wrap.querySelector( selector );
            if ( ! el ) return;
            el.style.border = isError ? FIELD_ERR_STYLE : '';
            // Also highlight parent date-group or select-wrap
            var parent = el.closest('.sbdp-date-group, .sbdp-select-wrap, .sbdp-time-select-wrap');
            if ( parent ) parent.style.border = isError ? FIELD_ERR_STYLE : '';
        }

        function sbdpClearError() {
            var errBox = document.getElementById('sbdp-cart-error');
            if ( errBox ) { errBox.style.cssText = ''; errBox.innerHTML = ''; }
            var btnNotice = document.getElementById('sbdp-btn-notice');
            if ( btnNotice ) { btnNotice.style.cssText = ''; btnNotice.innerHTML = ''; }
            // Clear field highlights
            sbdpHighlightField('input[name="sb_delivery_date"]', false);
            sbdpHighlightField('input[name="sb_delivery_time"]', false);
            sbdpHighlightField('select[name="sb_pickup_location"]', false);
        }

        /**
         * Broad selector that covers:
         *   - Standard WC anchor  <a class="checkout-button …">
         *   - Button variant       <button class="checkout-button …">
         *   - WC Blocks cart       <a class="wc-block-cart__submit-button …">
         *   - Theme button         <a class="sb-btn … wc-block-cart__submit-container">
         *                          <a class="sb-btn …">  (any sb-btn anchor/button)
         *   - Inside proceed wrappers (classic & blocks)
         *   - A "proceed" submit input
         */
        var CHECKOUT_BTN_SEL = [
            'a.checkout-button',
            'button.checkout-button',
            'a.wc-block-cart__submit-button',
            'button.wc-block-cart__submit-button',
            'a.wc-block-cart__submit-container',
            'button.wc-block-cart__submit-container',
            'a.sb-btn',
            'button.sb-btn',
            '.wc-proceed-to-checkout a',
            '.wc-proceed-to-checkout button',
            '.wc-proceed-to-checkout input[type="submit"]',
            '.wc-block-cart__submit-container a',
            '.wc-block-cart__submit-container button',
            'input[name="proceed"]'
        ].join(', ');

        $(document).on( 'click', CHECKOUT_BTN_SEL, function(e) {
            var data = sbdpCollect();

            // If the widget is not on the page at all, do not block.
            if ( ! data ) return;

            var errs = [];
            var highlightDate = false, highlightTime = false, highlightLoc = false;

            if ( ! data.sb_delivery_option ) {
                errs.push( 'Select a method: <strong>Delivery</strong> or <strong>Pick Up</strong>.' );
            }
            if ( data.sb_delivery_option === 'pickup' && ! data.sb_pickup_location ) {
                errs.push( 'Select a <strong>pickup location</strong>.' );
                highlightLoc = true;
            }
            if ( ! data.sb_delivery_date ) {
                errs.push( 'Choose a <strong>date</strong> for your order.' );
                highlightDate = true;
            }
            if ( ! data.sb_delivery_time ) {
                errs.push( 'Choose a <strong>time slot</strong> for your order.' );
                highlightTime = true;
            }

            if ( errs.length ) {
                e.preventDefault();
                e.stopImmediatePropagation();
                sbdpShowError( errs, this );
                // Highlight problematic fields
                sbdpHighlightField('input[name="sb_delivery_date"]', highlightDate);
                sbdpHighlightField('input[name="sb_delivery_time"]', highlightTime);
                sbdpHighlightField('select[name="sb_pickup_location"]', highlightLoc);
                return false;
            }

            sbdpClearError();
            // All good — force a final sync before navigating
            sbdpSync();
        });
    })(jQuery);
    </script>
    <?php
}
add_action( 'wp_footer', 'sbdp_cart_ajax_js' );

/**
 * Helper: resolve WC session into a structured data array.
 */
function sbdp_get_session_data() {
    $method = $location_slug = $date = $time = '';
    if ( WC()->session ) {
        $method        = sanitize_text_field( (string) WC()->session->get( 'sbdp_method',   '' ) );
        $location_slug = sanitize_text_field( (string) WC()->session->get( 'sbdp_location', '' ) );
        $date          = sanitize_text_field( (string) WC()->session->get( 'sbdp_date',     '' ) );
        $time          = sanitize_text_field( (string) WC()->session->get( 'sbdp_time',     '' ) );
    }
    // Resolve location slug → label + address
    $location_label   = $location_slug;
    $location_address = '';
    if ( $location_slug ) {
        foreach ( get_option( 'sbdp_pickup_locations', array() ) as $loc ) {
            if ( ! empty( $loc['label'] ) && sanitize_title( $loc['label'] ) === $location_slug ) {
                $location_label   = $loc['label'];
                $location_address = $loc['address'] ?? '';
                break;
            }
        }
    }
    // Format date Y-m-d → d/m/Y
    $date_display = '';
    if ( $date ) {
        $dt = DateTime::createFromFormat( 'Y-m-d', $date );
        $date_display = $dt ? $dt->format( 'd/m/Y' ) : $date;
    }
    return compact( 'method', 'location_slug', 'location_label', 'location_address', 'date', 'date_display', 'time' );
}

/**
 * 2b. CHECKOUT: Render delivery/pickup rows directly from PHP WC session.
 *     Called on woocommerce_review_order_after_shipping (inside the order review tfoot).
 *     Also called by WC's updated_checkout AJAX — so rows survive every refresh automatically.
 */
function sbdp_checkout_review_rows() {
    if ( ! is_checkout() ) return;

    $d         = sbdp_get_session_data();
    $method    = $d['method'];
    $is_pickup = ( $method === 'pickup' );

    if ( ! $method ) return;

    $section_label = $is_pickup
        ? __( 'Pickup Details',  'sbdp-delivery-pickup' )
        : __( 'Delivery Details','sbdp-delivery-pickup' );

    $method_label = $is_pickup
        ? '🏪 ' . __( 'Store Pickup', 'sbdp-delivery-pickup' )
        : '🚚 ' . __( 'Delivery',     'sbdp-delivery-pickup' );
    ?>
    <tr class="sbdp-co-section-row">
        <th colspan="2" style="font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#1e6b41;padding:10px 0 4px;border-top:2px solid #a7d7b8;">
            <?php echo esc_html( $section_label ); ?>
        </th>
    </tr>
    <tr class="sbdp-co-row">
        <th><?php esc_html_e( 'Method', 'sbdp-delivery-pickup' ); ?></th>
        <td><?php echo esc_html( $method_label ); ?></td>
    </tr>
    <?php if ( $is_pickup && $d['location_label'] ) : ?>
    <tr class="sbdp-co-row">
        <th><?php esc_html_e( 'Location', 'sbdp-delivery-pickup' ); ?></th>
        <td>
            <?php echo esc_html( $d['location_label'] ); ?>
            <?php if ( $d['location_address'] ) : ?>
                <span style="display:block;font-size:12px;color:#888;font-weight:400;margin-top:1px;">
                    <?php echo esc_html( $d['location_address'] ); ?>
                </span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endif; ?>
    <?php if ( $d['date_display'] ) : ?>
    <tr class="sbdp-co-row">
        <th><?php echo esc_html( $is_pickup ? __( 'Pickup Date', 'sbdp-delivery-pickup' ) : __( 'Delivery Date', 'sbdp-delivery-pickup' ) ); ?></th>
        <td><?php echo esc_html( $d['date_display'] ); ?></td>
    </tr>
    <?php endif; ?>
    <?php if ( $d['time'] ) : ?>
    <tr class="sbdp-co-row">
        <th><?php esc_html_e( 'Time', 'sbdp-delivery-pickup' ); ?></th>
        <td><?php echo esc_html( $d['time'] ); ?></td>
    </tr>
    <?php endif; ?>
    <?php
}
add_action( 'woocommerce_review_order_after_shipping', 'sbdp_checkout_review_rows', 10 );

/**
 * 2c. CHECKOUT: Method heading badge shown below billing details (left column).
 *     PHP renders initial state from WC session; JS keeps it live from localStorage.
 */
function sbdp_checkout_method_heading() {
    if ( ! is_checkout() ) return;

    $d         = sbdp_get_session_data();
    $method    = $d['method'];
    $is_pickup = ( $method === 'pickup' );
    $cart_url  = wc_get_cart_url();
    ?>
    <div id="sbdp-method-heading">
    <?php if ( $method ) : ?>
        <?php
        $ico  = $is_pickup ? '🏪' : '🚚';
        $mlbl = $is_pickup ? __( 'Store Pickup', 'sbdp-delivery-pickup' ) : __( 'Delivery', 'sbdp-delivery-pickup' );
        $sub_parts = array();
        if ( $is_pickup && $d['location_label'] ) $sub_parts[] = esc_html( $d['location_label'] );
        if ( $d['date_display'] )                 $sub_parts[] = esc_html( $d['date_display'] );
        if ( $d['time'] )                         $sub_parts[] = 'at ' . esc_html( $d['time'] );
        ?>
        <div class="sbdp-method-badge">
            <span class="sbdp-method-badge-left">
                <span class="sbdp-method-dot"></span>
                <span class="sbdp-method-badge-text">
                    <span class="sbdp-method-badge-title"><?php echo $ico . '&nbsp;' . esc_html( $mlbl ); ?></span>
                    <?php if ( $sub_parts ) : ?>
                        <span class="sbdp-method-badge-sub"><?php echo implode( ' &nbsp;&bull;&nbsp; ', $sub_parts ); ?></span>
                    <?php endif; ?>
                </span>
            </span>
            <a href="<?php echo esc_url( $cart_url ); ?>" class="sbdp-method-change">&larr; Change</a>
        </div>
    <?php endif; ?>
    </div>
    <?php
}
add_action( 'woocommerce_after_checkout_billing_form', 'sbdp_checkout_method_heading', 10 );

/**
 * CSS for checkout delivery/pickup rows and badge.
 * Rows are rendered by PHP (sbdp_checkout_review_rows) so no JS tfoot injection needed.
 */
function sbdp_checkout_summary_assets() {
    if ( ! is_checkout() ) return;
    $cart_url  = wc_get_cart_url();
    $locs_json = wp_json_encode( get_option( 'sbdp_pickup_locations', array() ) );
    $d         = sbdp_get_session_data();
    $sess_json = wp_json_encode( array(
        'sb_delivery_option' => $d['method'],
        'sb_pickup_location' => $d['location_slug'],
        'sb_delivery_date'   => $d['date'],
        'sb_delivery_time'   => $d['time'],
    ) );
    ?>
    <style>
    #sbdp-method-heading {
        margin-top: 20px;
        margin-bottom: 14px;
        width: 100%;
    }
    .sbdp-method-badge {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        box-sizing: border-box;
        background: linear-gradient(135deg, #f0faf4 0%, #e6f7ed 100%);
        border: 1.5px solid #a7d7b8;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 14px;
        font-weight: 600;
        color: #1d2327;
        gap: 12px;
        box-shadow: 0 1px 4px rgba(30,107,65,.07);
    }
    .sbdp-method-badge-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 0;
    }
    .sbdp-method-dot {
        width: 10px;
        height: 10px;
        background: #1e6b41;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .sbdp-method-badge-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .sbdp-method-badge-title {
        font-size: 14px;
        font-weight: 700;
        color: #1d2327;
        white-space: nowrap;
    }
    .sbdp-method-change {
        display: inline-block;
        font-size: 12px;
        font-weight: 500;
        color: #2c7be5;
        text-decoration: none;
        white-space: nowrap;
        flex-shrink: 0;
        border: 1px solid #2c7be5;
        border-radius: 6px;
        padding: 4px 10px;
        transition: background .15s, color .15s;
    }
    .sbdp-method-change:hover {
        background: #2c7be5;
        color: #fff;
        text-decoration: none;
    }
    .sbdp-method-badge-sub {
        font-size: 12px;
        font-weight: 400;
        color: #3a6a4a;
        margin-top: 3px;
        display: block;
        line-height: 1.6;
        white-space: normal;
        word-break: break-word;
    }
    .sbdp-co-row th {
        font-weight: 600; color: #555;
        padding: 6px 0; text-align: left;
        border-top: 1px solid #ebe9eb;
    }
    .sbdp-co-row td {
        color: #1d2327; font-weight: 600;
        padding: 6px 0; text-align: right;
        border-top: 1px solid #ebe9eb;
    }
    </style>
    <script>
    (function(){
        var LS_KEY      = 'sbdp_selection';
        var locsData    = <?php echo $locs_json; ?>;
        var cartUrl     = '<?php echo esc_js( $cart_url ); ?>';
        var sessionData = <?php echo $sess_json; ?>;

        function slug(s) { return s.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,''); }
        function findLoc(val) {
            if ( ! val || ! Array.isArray(locsData) ) return null;
            return locsData.find(function(l){ return l.label && slug(l.label) === val; }) || null;
        }
        function fmtDate(ymd) {
            if ( ! ymd ) return '';
            var p = ymd.split('-');
            return p.length === 3 ? p[2]+'/'+p[1]+'/'+p[0] : ymd;
        }
        function esc(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function loadData() {
            var ls = {};
            try { ls = JSON.parse( localStorage.getItem(LS_KEY) || '{}' ); } catch(e) { ls = {}; }
            return {
                method   : ls.sb_delivery_option || sessionData.sb_delivery_option || '',
                locKey   : ls.sb_pickup_location || sessionData.sb_pickup_location || '',
                date     : ls.sb_delivery_date   || sessionData.sb_delivery_date   || '',
                time     : ls.sb_delivery_time   || sessionData.sb_delivery_time   || '',
            };
        }

        /* Update the badge above the order table from localStorage (fresher than session) */
        function renderBadge() {
            var badge = document.getElementById('sbdp-method-heading');
            if ( ! badge ) return;
            var d        = loadData();
            var method   = d.method;
            var isPickup = ( method === 'pickup' );
            var loc      = isPickup ? findLoc(d.locKey) : null;
            if ( ! method ) return;
            var ico  = isPickup ? '\uD83C\uDFEA' : '\uD83D\uDE9A';
            var mlbl = isPickup ? 'Store Pickup' : 'Delivery';
            var sub  = [];
            if ( isPickup ) sub.push( esc( loc ? loc.label : d.locKey ) );
            if ( d.date )   sub.push( fmtDate(d.date) );
            if ( d.time )   sub.push( 'at ' + esc(d.time) );
            var subLine = sub.length ? '<span class="sbdp-method-badge-sub">' + sub.join(' &bull; ') + '</span>' : '';
            badge.innerHTML =
                '<div class="sbdp-method-badge">'
                + '<span class="sbdp-method-badge-left">'
                + '<span class="sbdp-method-dot"></span>'
                + '<span class="sbdp-method-badge-text">'
                + '<span class="sbdp-method-badge-title">' + ico + '&nbsp;' + mlbl + '</span>'
                + subLine
                + '</span>'
                + '</span>'
                + '<a href="' + esc(cartUrl) + '" class="sbdp-method-change">&larr; Change</a>'
                + '</div>';
        }

        document.addEventListener('DOMContentLoaded', renderBadge);
        window.sbdpRenderCheckoutSummary = renderBadge;
        if ( typeof jQuery !== 'undefined' ) {
            jQuery(document.body).on('updated_checkout', renderBadge);
        }
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'sbdp_checkout_summary_assets' );

/**
 * 2c. On CHECKOUT: inject delivery values as hidden form fields.
 *     Reads from localStorage first, falls back to PHP WC session values.
 */
function sbdp_checkout_inject_js() {
    if ( ! is_checkout() ) {
        return;
    }

    // PHP WC session fallback values
    $sess = array(
        'sb_delivery_option' => '',
        'sb_pickup_location' => '',
        'sb_delivery_date'   => '',
        'sb_delivery_time'   => '',
    );
    if ( WC()->session ) {
        $sess['sb_delivery_option'] = sanitize_text_field( (string) WC()->session->get( 'sbdp_method',   '' ) );
        $sess['sb_pickup_location'] = sanitize_text_field( (string) WC()->session->get( 'sbdp_location', '' ) );
        $sess['sb_delivery_date']   = sanitize_text_field( (string) WC()->session->get( 'sbdp_date',     '' ) );
        $sess['sb_delivery_time']   = sanitize_text_field( (string) WC()->session->get( 'sbdp_time',     '' ) );
    }
    $sess_json = wp_json_encode( $sess );
    ?>
    <script>
    (function(){
        var LS_KEY      = 'sbdp_selection';
        var fields      = [ 'sb_delivery_option', 'sb_pickup_location', 'sb_delivery_date', 'sb_delivery_time' ];
        var sessionData = <?php echo $sess_json; ?>;

        function getData() {
            var ls = {};
            try { ls = JSON.parse( localStorage.getItem( LS_KEY ) || '{}' ); } catch(e) { ls = {}; }
            // Merge: localStorage wins, session fills blanks
            var out = {};
            fields.forEach(function(k){
                out[k] = ls[k] || sessionData[k] || '';
            });
            return out;
        }

        function injectHiddenFields() {
            var form = document.querySelector('form.woocommerce-checkout');
            if ( ! form ) return;

            var stored = getData();
            fields.forEach(function( name ){
                var existing = form.querySelector( 'input[name="' + name + '"]' );
                if ( existing ) {
                    existing.value = stored[ name ] || '';
                } else {
                    var inp = document.createElement('input');
                    inp.type  = 'hidden';
                    inp.name  = name;
                    inp.value = stored[ name ] || '';
                    form.appendChild( inp );
                }
            });

            // Keep the checkout summary card in sync
            if ( typeof window.sbdpRenderCheckoutSummary === 'function' ) {
                window.sbdpRenderCheckoutSummary();
            }
        }

        // Run on load and also just before form submit
        document.addEventListener( 'DOMContentLoaded', injectHiddenFields );
        document.addEventListener( 'DOMContentLoaded', function(){
            var form = document.querySelector('form.woocommerce-checkout');
            if ( form ) form.addEventListener( 'submit', injectHiddenFields );
        });

        // WooCommerce reloads checkout via AJAX - re-inject on updated_checkout
        if ( typeof jQuery !== 'undefined' ) {
            jQuery(document.body).on( 'updated_checkout', injectHiddenFields );
        }
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'sbdp_checkout_inject_js' );

/**
 * 3. When an order is created at checkout, read from POST (primary) then session (fallback).
 *
 * @param WC_Order $order
 */
function sbdp_save_to_order( $order ) {
    // Primary: read directly from the checkout form POST (injected by sbdp_checkout_inject_js)
    $method   = isset( $_POST['sb_delivery_option'] ) ? sanitize_text_field( wp_unslash( $_POST['sb_delivery_option'] ) ) : '';
    $location = isset( $_POST['sb_pickup_location'] ) ? sanitize_text_field( wp_unslash( $_POST['sb_pickup_location'] ) ) : '';
    $date     = isset( $_POST['sb_delivery_date'] )   ? sanitize_text_field( wp_unslash( $_POST['sb_delivery_date'] ) )   : '';
    $time     = isset( $_POST['sb_delivery_time'] )   ? sanitize_text_field( wp_unslash( $_POST['sb_delivery_time'] ) )   : '';

    // Fallback: WC session (set via AJAX on cart page)
    if ( ! $method && WC()->session ) {
        $method   = sanitize_text_field( (string) WC()->session->get( 'sbdp_method',   '' ) );
        $location = sanitize_text_field( (string) WC()->session->get( 'sbdp_location', '' ) );
        $date     = sanitize_text_field( (string) WC()->session->get( 'sbdp_date',     '' ) );
        $time     = sanitize_text_field( (string) WC()->session->get( 'sbdp_time',     '' ) );
    }

    // Resolve location slug → human-readable label
    $location_label = $location;
    if ( $location ) {
        $locs_raw = get_option( 'sbdp_pickup_locations', array() );
        foreach ( $locs_raw as $loc ) {
            if ( ! empty( $loc['label'] ) && sanitize_title( $loc['label'] ) === $location ) {
                $location_label = $loc['label'];
                break;
            }
        }
    }

    // Format time for readability ("6:00 am, 6:30 am" instead of raw csv)
    $time_display = implode( ', ', array_filter( array_map( 'trim', explode( ',', $time ) ) ) );

    // Save all meta
    $order->update_meta_data( '_sbdp_method',         $method );
    $order->update_meta_data( '_sbdp_location',       $location );       // slug key
    $order->update_meta_data( '_sbdp_location_label', $location_label ); // readable label
    $order->update_meta_data( '_sbdp_date',           $date );           // Y-m-d
    $order->update_meta_data( '_sbdp_time',           $time_display );   // readable
    $order->save();
}
add_action( 'woocommerce_checkout_create_order', 'sbdp_save_to_order', 10, 1 );

/**
 * Server-side validation: block order placement if delivery method or date is missing.
 */
function sbdp_checkout_validate() {
    // Try POST first (hidden fields injected by sbdp_checkout_inject_js)
    $method = isset( $_POST['sb_delivery_option'] ) ? sanitize_text_field( wp_unslash( $_POST['sb_delivery_option'] ) ) : '';
    $date   = isset( $_POST['sb_delivery_date'] )   ? sanitize_text_field( wp_unslash( $_POST['sb_delivery_date'] ) )   : '';
    $time   = isset( $_POST['sb_delivery_time'] )   ? sanitize_text_field( wp_unslash( $_POST['sb_delivery_time'] ) )   : '';
    $location = isset( $_POST['sb_pickup_location'] ) ? sanitize_text_field( wp_unslash( $_POST['sb_pickup_location'] ) ) : '';

    // Fallback: WC session
    if ( ! $method && WC()->session ) {
        $method   = sanitize_text_field( (string) WC()->session->get( 'sbdp_method', '' ) );
        $date     = sanitize_text_field( (string) WC()->session->get( 'sbdp_date',   '' ) );
        $time     = sanitize_text_field( (string) WC()->session->get( 'sbdp_time',   '' ) );
        $location = sanitize_text_field( (string) WC()->session->get( 'sbdp_location', '' ) );
    }

    if ( ! $method ) {
        wc_add_notice(
            __( 'Please select a delivery or pickup method (go back to cart).', 'sbdp-delivery-pickup' ),
            'error'
        );
    }
    if ( $method === 'pickup' && ! $location ) {
        wc_add_notice(
            __( 'Please select a pickup location (go back to cart).', 'sbdp-delivery-pickup' ),
            'error'
        );
    }
    if ( ! $date ) {
        wc_add_notice(
            __( 'Please select a delivery/pickup date (go back to cart).', 'sbdp-delivery-pickup' ),
            'error'
        );
    }
    if ( ! $time ) {
        wc_add_notice(
            __( 'Please select a delivery/pickup time (go back to cart).', 'sbdp-delivery-pickup' ),
            'error'
        );
    }
}
add_action( 'woocommerce_checkout_process', 'sbdp_checkout_validate' );

/**
 * Helper: return a formatted array of saved delivery meta for an order.
 *
 * @param  WC_Order $order
 * @return array    [ label => value, ... ]  – empty rows are omitted.
 */
function sbdp_get_order_meta_rows( $order ) {
    $method         = $order->get_meta( '_sbdp_method' );
    $location_label = $order->get_meta( '_sbdp_location_label' );
    $location       = $location_label ?: $order->get_meta( '_sbdp_location' ); // fallback to slug for old orders
    $date           = $order->get_meta( '_sbdp_date' );
    $time           = $order->get_meta( '_sbdp_time' );

    if ( ! $method && ! $date ) {
        return array();
    }

    $rows = array();

    if ( $method ) {
        $rows[ __( 'Method', 'sbdp-delivery-pickup' ) ] = $method === 'pickup'
            ? __( 'Store Pickup', 'sbdp-delivery-pickup' )
            : __( 'Delivery', 'sbdp-delivery-pickup' );
    }

    if ( $method === 'pickup' && $location ) {
        $rows[ __( 'Pickup Location', 'sbdp-delivery-pickup' ) ] = $location;
    }

    if ( $date ) {
        $dt = DateTime::createFromFormat( 'Y-m-d', $date );
        $rows[ $method === 'pickup' ? __( 'Pickup Date', 'sbdp-delivery-pickup' ) : __( 'Delivery Date', 'sbdp-delivery-pickup' ) ]
            = $dt ? $dt->format( 'd/m/Y' ) : $date;
    }

    if ( $time ) {
        $rows[ __( 'Time', 'sbdp-delivery-pickup' ) ] = $time;
    }

    return $rows;
}

/**
 * 4a. CUSTOMER ORDER TOTALS TABLE — native WC filter used by:
 *     thank-you page, My Account → View Order, and order emails (WC runs this in all three).
 */
function sbdp_inject_customer_order_rows( $total_rows, $order, $tax_display = '' ) {
    $rows = sbdp_get_order_meta_rows( $order );
    if ( empty( $rows ) ) {
        return $total_rows;
    }

    $new_rows = array();
    foreach ( $rows as $label => $value ) {
        $key              = 'sbdp_' . sanitize_key( $label );
        $new_rows[ $key ] = array(
            'label' => esc_html( $label ) . ':',
            'value' => esc_html( $value ),
        );
    }

    // Insert after 'shipping' row (mirrors where WC places shipping), fallback to append.
    $keys      = array_keys( $total_rows );
    $after_key = in_array( 'shipping', $keys, true ) ? 'shipping'
               : ( in_array( 'payment_method', $keys, true ) ? 'payment_method' : '' );

    if ( $after_key ) {
        $pos    = array_search( $after_key, $keys, true );
        $before = array_slice( $total_rows, 0, $pos + 1, true );
        $after  = array_slice( $total_rows, $pos + 1, null, true );
        return array_merge( $before, $new_rows, $after );
    }

    return array_merge( $total_rows, $new_rows );
}
add_filter( 'woocommerce_get_order_item_totals', 'sbdp_inject_customer_order_rows', 10, 3 );

/**
 * 4b. ADMIN ORDER PAGE — meta box shown in the sidebar of the order edit screen.
 *     Works with both classic orders and HPOS (WC 7+).
 */
function sbdp_register_order_meta_box() {
    $screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
    foreach ( $screens as $screen ) {
        add_meta_box(
            'sbdp_order_details',
            __( 'Delivery & Pickup Details', 'sbdp-delivery-pickup' ),
            'sbdp_admin_order_meta_box_html',
            $screen,
            'side',
            'high'
        );
    }
}
add_action( 'add_meta_boxes', 'sbdp_register_order_meta_box' );

function sbdp_admin_order_meta_box_html( $post_or_order ) {
    // Works with both WP_Post (classic) and WC_Order (HPOS)
    $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
    if ( ! $order ) return;

    $rows = sbdp_get_order_meta_rows( $order );
    if ( empty( $rows ) ) {
        echo '<p style="color:#888;font-size:13px;margin:0;">' . esc_html__( 'No delivery/pickup data saved for this order.', 'sbdp-delivery-pickup' ) . '</p>';
        return;
    }
    ?>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <?php foreach ( $rows as $label => $value ) : ?>
        <tr>
            <td style="padding:6px 8px 6px 0;color:#646970;font-weight:600;width:45%;vertical-align:top;border-bottom:1px solid #f0f0f0;">
                <?php echo esc_html( $label ); ?>:
            </td>
            <td style="padding:6px 0;color:#1d2327;font-weight:700;border-bottom:1px solid #f0f0f0;">
                <?php echo esc_html( $value ); ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
}

/**
 * 5. Add delivery/pickup details to ALL WooCommerce order emails (customer + admin).
 *
 * @param WC_Order $order
 * @param bool     $sent_to_admin
 * @param bool     $plain_text
 */
function sbdp_email_order_meta( $order, $sent_to_admin, $plain_text ) {
    $rows = sbdp_get_order_meta_rows( $order );
    if ( empty( $rows ) ) {
        return;
    }

    if ( $plain_text ) {
        echo "\n" . strtoupper( __( 'Delivery & Pickup Details', 'sbdp-delivery-pickup' ) ) . "\n";
        echo str_repeat( '-', 32 ) . "\n";
        foreach ( $rows as $label => $value ) {
            echo esc_html( $label ) . ': ' . esc_html( $value ) . "\n";
        }
        echo "\n";
    } else {
        echo '<h2 style="color:#1d2327;font-family:inherit;font-size:16px;font-weight:700;margin:32px 0 10px;padding-bottom:8px;border-bottom:2px solid #e5e5e5;">';
        echo esc_html__( 'Delivery & Pickup Details', 'sbdp-delivery-pickup' );
        echo '</h2>';
        echo '<table cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:24px;">';
        foreach ( $rows as $label => $value ) {
            echo '<tr>';
            echo '<td style="padding:10px 16px 10px 0;border-bottom:1px solid #f0f0f0;color:#646970;font-weight:600;width:160px;vertical-align:top;">' . esc_html( $label ) . '</td>';
            echo '<td style="padding:10px 0;border-bottom:1px solid #f0f0f0;color:#1d2327;font-weight:700;">' . esc_html( $value ) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}
add_action( 'woocommerce_email_order_meta', 'sbdp_email_order_meta', 10, 3 );

/* ==========================================================================
   DELIVERY & PICKUP — CLEAR SESSION & LOCALSTORAGE AFTER ORDER PLACED
   ========================================================================== */

/**
 * 6. On the thank-you page: clear the WC session keys and output JS to wipe localStorage,
 *    so the next order starts fresh.
 *
 * @param int $order_id
 */
function sbdp_clear_after_order( $order_id ) {
    // Clear WC session keys
    if ( WC()->session ) {
        WC()->session->__unset( 'sbdp_method' );
        WC()->session->__unset( 'sbdp_location' );
        WC()->session->__unset( 'sbdp_date' );
        WC()->session->__unset( 'sbdp_time' );
    }
    // Output JS to remove localStorage key
    ?>
    <script>
    (function(){ try { localStorage.removeItem('sbdp_selection'); } catch(e){} })();
    </script>
    <?php
}
add_action( 'woocommerce_thankyou', 'sbdp_clear_after_order', 10, 1 );

// =============================================================================
// BOOKING CAPACITY — TRACKING
// =============================================================================

/**
 * Increment booking count when a new order is created.
 */
function sbdp_track_booking_on_create( $order ) {
    $date = $order->get_meta( '_sbdp_date' );
    if ( ! $date ) return;
    $log          = get_option( 'sbdp_bookings_log', array() );
    $log[ $date ] = ( isset( $log[ $date ] ) ? (int) $log[ $date ] : 0 ) + 1;
    update_option( 'sbdp_bookings_log', $log );
}
add_action( 'woocommerce_checkout_order_created', 'sbdp_track_booking_on_create', 20 );

/**
 * Decrement booking count when an order is cancelled, refunded, or trashed.
 *
 * @param int    $order_id
 * @param string $old_status
 * @param string $new_status
 */
function sbdp_untrack_booking( $order_id, $old_status, $new_status ) {
    if ( ! in_array( $new_status, array( 'cancelled', 'refunded', 'trash' ), true ) ) {
        return;
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    $date = $order->get_meta( '_sbdp_date' );
    if ( ! $date ) return;
    $log = get_option( 'sbdp_bookings_log', array() );
    if ( isset( $log[ $date ] ) ) {
        $log[ $date ] = max( 0, (int) $log[ $date ] - 1 );
        update_option( 'sbdp_bookings_log', $log );
    }
}
add_action( 'woocommerce_order_status_changed', 'sbdp_untrack_booking', 10, 3 );

/**
 * Admin AJAX: manually adjust (+/-) the booking count for a given date.
 * Called by the +/- buttons in the Booking Capacity admin tab.
 */
function sbdp_adjust_booking_ajax() {
    check_ajax_referer( 'sbdp_adjust_booking', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $date  = sanitize_text_field( wp_unslash( $_POST['date']  ?? '' ) );
    $delta = (int) ( $_POST['delta'] ?? 0 );
    if ( ! $date || 0 === $delta ) {
        wp_send_json_error( 'Invalid data' );
    }
    $log          = get_option( 'sbdp_bookings_log', array() );
    $current      = isset( $log[ $date ] ) ? (int) $log[ $date ] : 0;
    $log[ $date ] = max( 0, $current + $delta );
    update_option( 'sbdp_bookings_log', $log );
    wp_send_json_success( array( 'count' => $log[ $date ] ) );
}
add_action( 'wp_ajax_sbdp_adjust_booking', 'sbdp_adjust_booking_ajax' );


/* ==========================================================================
   DATE-SPECIFIC CAPACITY — AJAX HANDLERS
   ========================================================================== */

/**
 * Save (add or update) a date-specific capacity override.
 */
function sbdp_save_date_capacity_ajax() {
    check_ajax_referer( 'sbdp_date_capacity', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $date     = sanitize_text_field( wp_unslash( $_POST['date']     ?? '' ) );
    $capacity = absint( $_POST['capacity'] ?? 0 );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        wp_send_json_error( 'Invalid date format' );
    }
    if ( $capacity < 1 ) {
        wp_send_json_error( 'Capacity must be at least 1' );
    }
    $caps          = get_option( 'sbdp_date_specific_capacity', array() );
    $caps[ $date ] = $capacity;
    update_option( 'sbdp_date_specific_capacity', $caps );
    wp_send_json_success( array( 'date' => $date, 'capacity' => $capacity ) );
}
add_action( 'wp_ajax_sbdp_save_date_capacity', 'sbdp_save_date_capacity_ajax' );

/**
 * Delete a date-specific capacity override.
 */
function sbdp_delete_date_capacity_ajax() {
    check_ajax_referer( 'sbdp_date_capacity', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
    $caps = get_option( 'sbdp_date_specific_capacity', array() );
    if ( isset( $caps[ $date ] ) ) {
        unset( $caps[ $date ] );
        update_option( 'sbdp_date_specific_capacity', $caps );
    }
    wp_send_json_success();
}
add_action( 'wp_ajax_sbdp_delete_date_capacity', 'sbdp_delete_date_capacity_ajax' );