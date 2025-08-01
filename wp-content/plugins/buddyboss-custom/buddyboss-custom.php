<?php
/*
Plugin Name: BuddyBoss Custom Enhancements
Description: Custom profile field and member directory shortcode with filtering.
Version: 1.0
Author: Diego Gutierrez
*/

if (!defined('ABSPATH')) exit;

// Create custom profile field
function bb_custom_register_profile_fields() {
    if (bp_is_active('xprofile')) {
        $group_id = 1;
        $field_name = 'Learning Goal';

        if (!xprofile_get_field_id_from_name($field_name)) {
            xprofile_insert_field([
                'field_group_id' => $group_id,
                'type' => 'textbox',
                'name' => $field_name,
                'is_required' => false,
                'can_delete' => true
            ]);
        }
    }
}
add_action('bp_init', 'bb_custom_register_profile_fields');

// Show field in profile
function bb_custom_display_learning_goal() {
    $goal = bp_get_profile_field_data(['field' => 'Learning Goal']);
    if (!empty($goal)) {
        echo '<div class="learning-goal"><strong>Learning Goal:</strong> ' . esc_html($goal) . '</div>';
    }
}
add_action('bp_before_member_header_meta', 'bb_custom_display_learning_goal');

// Shortcode whit filter
function bb_custom_learning_directory_shortcode() {
    ob_start();

    $goals = bb_custom_get_unique_learning_goals();

    echo '<div class="learning-directory-filter">';
    echo '<label for="learning-goal-filter"><strong>Filter by Learning Goal:</strong> </label>';
    echo '<select id="learning-goal-filter">';
    echo '<option value="">All Goals</option>';
    foreach ($goals as $goal) {
        echo '<option value="' . esc_attr($goal) . '">' . esc_html($goal) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div id="learning-directory-container">';
    echo bb_custom_render_members();
    echo '</div>';

    return ob_get_clean();
}
add_shortcode('learning_community_directory', 'bb_custom_learning_directory_shortcode');

// Obtain user list with HTML
function bb_custom_render_members($filter = '') {
    $args = ['role__not_in' => ['Administrator']];
    $users = get_users($args);
    $output = '<div class="learning-directory" style="display: flex; flex-wrap: wrap; gap: 20px;">';

    foreach ($users as $user) {
        $goal = xprofile_get_field_data('Learning Goal', $user->ID);
        if ($filter && $goal !== $filter) continue;

        $avatar = bp_core_fetch_avatar(['item_id' => $user->ID, 'type' => 'thumb', 'html' => false]);
        $profile_link = bp_core_get_user_domain($user->ID);

        $output .= '
        <div class="member-card" style="border:1px solid #ddd; padding:15px; width:250px;">
            <a href="' . esc_url($profile_link) . '">
                <img src="' . esc_url($avatar) . '" width="80" style="border-radius:50%;" />
                <h4>' . esc_html($user->display_name) . '</h4>
            </a>
            <p><strong>Learning Goal:</strong> ' . esc_html($goal) . '</p>
        </div>';
    }

    $output .= '</div>';
    return $output;
}

// AJAX: Return filtered members
add_action('wp_ajax_bb_filter_members', 'bb_custom_ajax_filter_members');
add_action('wp_ajax_nopriv_bb_filter_members', 'bb_custom_ajax_filter_members');

function bb_custom_ajax_filter_members() {
    $goal = isset($_POST['goal']) ? sanitize_text_field($_POST['goal']) : '';
    echo bb_custom_render_members($goal);
    wp_die();
}

// Obtain Unique  Learning Goals list
function bb_custom_get_unique_learning_goals() {
    $users = get_users(['role__not_in' => ['Administrator']]);
    $goals = [];

    foreach ($users as $user) {
        $goal = trim(xprofile_get_field_data('Learning Goal', $user->ID));
        if ($goal && !in_array($goal, $goals)) {
            $goals[] = $goal;
        }
    }

    sort($goals);
    return $goals;
}

// Charge JS
function bb_custom_enqueue_scripts() {
    wp_enqueue_script(
        'bb-custom-script',
        plugin_dir_url(__FILE__) . 'assets/script.js',
        ['jquery'],
        null,
        true
    );
    wp_localize_script('bb-custom-script', 'bbCustomAjax', [
        'ajaxurl' => admin_url('admin-ajax.php')
    ]);
}
add_action('wp_enqueue_scripts', 'bb_custom_enqueue_scripts');

// Shortcode: [group_activity_feed]
function bb_custom_group_activity_feed_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view group activity.</p>';
    }

    ob_start();

    // Manejar solicitud para unirse a un grupo
    if (isset($_POST['join_group']) && isset($_POST['group_id'])) {
        $group_id = (int) $_POST['group_id'];
        groups_join_group($group_id, get_current_user_id());
        wp_redirect(get_permalink());
        exit;
    }

    echo '<div class="group-activity-feed" style="max-width:800px;margin:0 auto;padding:20px;">';
    echo '<h2>Latest Group Activities</h2>';

    $args = [
        'per_page' => 10,
        'filter'   => ['object' => 'groups'],
        'action'   => false,
    ];

    if (bp_has_activities($args)) :
        while (bp_activities()) : bp_the_activity();

            $group_id = bp_get_activity_item_id();
            $group = groups_get_group(['group_id' => $group_id]);

            if (!$group || $group->status !== 'public') continue;

            $group_link = bp_get_group_permalink($group);
            $group_name = $group->name;
            $is_member = groups_is_user_member(get_current_user_id(), $group->id);

            $user_link = bp_core_get_userlink(bp_get_activity_user_id());
            $activity_snippet = wp_trim_words(bp_get_activity_content_body(), 25);

            echo '<div class="activity-card" style="border:1px solid #ccc; padding:15px; margin-bottom:20px;">';

            // Group name
            echo '<h3><a href="' . esc_url($group_link) . '">' . esc_html($group_name) . '</a></h3>';

            // Activity author
            echo '<p><strong>' . $user_link . '</strong> posted:</p>';

            // Content snippet
            echo '<p>' . $activity_snippet . '</p>';

            // Join button (if not member)
            if (!$is_member) {
                echo '<form method="post" action="">';
                echo '<input type="hidden" name="group_id" value="' . esc_attr($group->id) . '">';
                echo '<input type="submit" name="join_group" value="Join Group">';
                echo '</form>';
            }

            echo '</div>';

        endwhile;
    else :
        echo '<p>No activity found in public groups.</p>';
    endif;

    echo '</div>';

    return ob_get_clean();
}
add_shortcode('group_activity_feed', 'bb_custom_group_activity_feed_shortcode');

// Restrict access to member resources
function restrict_member_resources_access() {
    if (is_page('member-resources')) {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url() . '?redirected=true');
            exit;
        }

        $user = wp_get_current_user();
        $allowed_roles = ['subscriber', 'contributor', 'author', 'editor', 'administrator'];

        $has_access = false;
        foreach ($allowed_roles as $role) {
            if (in_array($role, (array) $user->roles)) {
                $has_access = true;
                break;
            }
        }

        if (!$has_access) {
            wp_redirect(wp_login_url() . '?redirected=true');
            exit;
        }
    }
}
add_action('template_redirect', 'restrict_member_resources_access');

// Show message on login form if redirected
add_action('login_form', 'show_custom_login_notice');
function show_custom_login_notice() {
    if (isset($_GET['redirected']) && $_GET['redirected'] === 'true') {
        echo '<div style="background:#ffe3e3; border:1px solid #cc0000; padding:10px; margin-bottom:15px; text-align:center;">
           <strong> ⚠️ Please log in to access member-only resources. </strong>
        </div>';
    }
}
