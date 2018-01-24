<?php

/**
 * Plugin Name:       Badge Factor Certificate Generator
 * Plugin URI:        https://github.com/DigitalPygmalion/badge-factor-certificates
 * Description:       This plugin generates individual certificates with information concerning issued badges
 * Version:           1.0.0
 * Author:            ctrlweb
 * Author URI:        https://ctrlweb.ca/
 * License:           MIT
 * Text Domain:       badge-factor-cert
 * Domain Path:       /languages
 */

/*
 * Copyright (c) 2017 Digital Pygmalion
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and
 * to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of
 * the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO
 * THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

require __DIR__ . '/vendor/autoload.php';

class BadgeFactorCertificates
{
    /**
     * BadgeFactorCertificates Version
     *
     * @var string
     */
    public static $version = '1.0.0';


    /**
     * Holds any blocker error messages stopping plugin running
     *
     * @var array
     *
     * @since 1.0.0
     */
    private $notices = array();


    /**
     * The plugin's required WordPress version
     *
     * @var string
     *
     * @since 1.0.0
     */
    public $required_bf_version = '1.0.0';


    /**
     * BadgeFactorCertificates constructor.
     */
    function __construct()
    {
        // Plugin constants
        $this->basename = plugin_basename(__FILE__);
        $this->directory_path = plugin_dir_path(__FILE__);
        $this->directory_url = plugin_dir_url(__FILE__);

        // Load translations
        load_plugin_textdomain('badgefactor_cert', false, basename(dirname(__FILE__)) . '/languages');

        // Activation / deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('admin_menu', array($this, 'admin_menu'), 300);
        add_action('init', array($this, 'create_cpt_certificate'));

        add_action('parse_request', array($this, 'display_certificate'));

        add_action('init', array($this, 'preview_certificate_template'));
        //add_action('acf/save_post', array($this, 'save_certificate_template'), 20);

        add_action('add_meta_boxes', array($this, 'badgefactor_certificate_meta_boxes'));

        add_filter('acf/prepare_field', array($this, 'my_acf_prepare_field'), 10, 1);
    }


    ///////////////////////////////////////////////////////////////////////////
    //                                 HOOKS                                 //
    ///////////////////////////////////////////////////////////////////////////

    /**
     * BadgeFactorCertificates plugin activation hook.
     */
    public function activate()
    {


    }


    /**
     * BadgeFactorCertificates plugin deactivation hook.
     */
    public function deactivate()
    {

    }


    function display_notices()
    {
        ?>
        <div class="error">
            <p>
                <strong><?php esc_html_e('Badge Factor Certificates Installation Problem', 'badgefactor_cert'); ?></strong>
            </p>

            <p><?php esc_html_e('The minimum requirements for Badge Factor Certificates have not been met. Please fix the issue(s) below to continue:', 'badgefactor_cert'); ?></p>
            <ul style="padding-bottom: 0.5em">
                <?php foreach ($this->notices as $notice) : ?>
                    <li style="padding-left: 20px;list-style: inside"><?php echo $notice; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }


    /**
     * Check if Badge Factor version is compatible
     *
     * @return boolean Whether compatible or not
     *
     * @since 1.0.0
     */
    public function is_compatible_bf_version()
    {

        /* Gravity Forms version not compatible */
        if (!class_exists('BadgeFactor') || !version_compare(BadgeFactor::$version, $this->required_bf_version, '>=')) {
            $this->notices[] = sprintf(esc_html__('%sBadge Factor%s Version %s is required.', 'badgefactor_cert'), '<a href="https://github.com/DigitalPygmalion/badge-factor">', '</a>', $this->required_bf_version);

            return false;
        }

        return true;
    }

    /**
     * admin_options.
     */
    public function admin_options()
    {

    }

    /**
     * admin_menu hook.
     */
    public function admin_menu()
    {
        add_submenu_page('badgeos_badgeos', __('Badge Factor Options', 'badgefactor'), __('Certificates Settings', 'badgefactor_cert'), 'manage_options', 'badgefactor_cert', array($this, 'admin_options'));
    }


    /**
     * add_options_page hook.
     */
    public function badgefactor_options()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        include('admin/settings-page.tpl.php');
    }


    /**
     * init hook to create the certificate post type attached to a badge
     */
    public function create_cpt_certificate()
    {
        // Register the post type
        register_post_type('certificate', array(
            'labels' => array(
                'name' => __('Certificates', 'badgefactor_cert'),
                'singular_name' => __('Certificate', 'badgefactor_cert'),
                'add_new' => __('Add New', 'badgefactor_cert'),
                'add_new_item' => __('Add New Certificate', 'badgefactor_cert'),
                'edit_item' => __('Edit Certificate', 'badgefactor_cert'),
                'new_item' => __('New Certificate', 'badgefactor_cert'),
                'all_items' => __('Certificates', 'badgefactor_cert'),
                'view_item' => __('View Certificates', 'badgefactor_cert'),
                'search_items' => __('Search Certificates', 'badgefactor_cert'),
                'not_found' => __('No certificate found', 'badgefactor_cert'),
                'not_found_in_trash' => __('No certificate found in Trash', 'badgefactor_cert'),
                'parent_item_colon' => '',
                'menu_name' => 'Certificates',
            ),
            'rewrite' => array(
                'slug' => 'certificate',
            ),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => current_user_can(badgeos_get_manager_capability()),
            'show_in_menu' => 'badgeos_badgeos',
            'query_var' => true,
            'exclude_from_search' => true,
            'capability_type' => 'post',
            'hierarchical' => true,
            'menu_position' => null,
            'supports' => array('title')
        ));


        if (function_exists('register_field_group')):

            register_field_group(array(
                'id' => 'acf_certificate',
                'title' => 'Certificate model',
                'fields' => array(
                    array(
                        'key' => 'field_59159115271cd',
                        'label' => 'Certificate Template File (PDF)',
                        'name' => 'template',
                        'type' => 'file',
                        'required' => 0,
                        'save_format' => 'object',
                        'library' => 'all',
                    ),
                    /* array(
                         'key' => 'field_59159159271ce',
                         'label' => 'Associated Badge',
                         'name' => 'badge',
                         'type' => 'relationship',
                         'return_format' => 'object',
                         'post_type' => array(
                             0 => 'badges',
                         ),
                         'taxonomy' => array(
                             0 => 'all',
                         ),
                         'filters' => array(
                             0 => 'search',
                         ),
                         'result_elements' => array(
                             0 => 'post_type',
                             1 => 'post_title',
                         ),
                         'max' => '',
                     ),*/
                    array(
                        'key' => 'field_19159bb617319',
                        'label' => 'Badge Name Affiché',
                        'name' => 'badge_name_show',
                        'type' => 'true_false',
                        'required' => 0,
                        'default_value' => true,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'wrapper' => array(
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ),

                    ),
                    array(
                        'key' => 'field_59159bb617310',
                        'label' => 'Badge Name Position (x)',
                        'name' => 'badge_name_position_x',
                        'type' => 'number',
                        'required' => 0,
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'min' => '',
                        'max' => '',
                        'step' => '',
                        'wrapper' => array(
                            'width' => '40',
                            'class' => '',
                            'id' => '',
                        ),

                    ),
                    array(
                        'key' => 'field_59159bb617311',
                        'label' => 'Badge Name Position (y)',
                        'name' => 'badge_name_position_y',
                        'type' => 'number',
                        'required' => 0,
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'min' => '',
                        'max' => '',
                        'step' => '',
                        'wrapper' => array(
                            'width' => '40',
                            'class' => '',
                            'id' => '',
                        ),
                    ),
                    array(
                        'key' => 'field_19159bb617312',
                        'label' => 'Badge Description Affiché',
                        'name' => 'badge_description_show',
                        'type' => 'true_false',
                        'required' => 0,
                        'default_value' => true,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'wrapper' => array(
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ),

                    ),
                    array(
                        'key' => 'field_59159bb617312',
                        'label' => 'Badge Description Position (x)',
                        'name' => 'badge_description_position_x',
                        'type' => 'number',
                        'required' => 0,
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'min' => '',
                        'max' => '',
                        'step' => '',
                        'wrapper' => array(
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ),
                    ),
                    array(
                        'key' => 'field_59159bb617313',
                        'label' => 'Badge Description Position (y)',
                        'name' => 'badge_description_position_y',
                        'type' => 'number',
                        'required' => 0,
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'min' => '',
                        'max' => '',
                        'step' => '',
                        'wrapper' => array(
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ),
                    ),
                    array(
                        'key' => 'field_39159cda1730e',
                        'label' => 'Badge Description Largeur',
                        'name' => 'badge_description_largeur',
                        'type' => 'number',
                        'default_value' => '-1',
                        'allow_null' => 0,
                        'wrapper' => array(
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ),
                    ),
                    array(
                        'key' => 'field_34159cda1730e',
                        'label' => 'Badge Description Alignement',
                        'name' => 'badge_description_alignement',
                        'type' => 'select',
                        'choices' => array(
                            'L' => 'Gauche',
                            'R' => 'Droite',
                            'C' => 'Centrer',
                        ),
                        'default_value' => '',
                        'allow_null' => 0,
                        'multiple' => 0,
                        'wrapper' => array(
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ),
                    ),
                    array(
                        'key' => 'field_19159bb617316',
                        'label' => 'Badge Image Affiché',
                        'name' => 'badge_image_show',
                        'type' => 'true_false',
                        'required' => 0,
                        'default_value' => true,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'wrapper' => array(
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ),

                    ),
                    array(
                        'key' => 'field_59159bb617316',
                        'label' => 'Badge Image Position (x)',
                        'name' => 'badge_image_position_x',
                        'type' => 'number',
                        'required' => 0,
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'min' => '',
                        'max' => '',
                        'step' => '',
                        'wrapper' => array(
                            'width' => '40',
                            'class' => '',
                            'id' => '',
                        ),
                    ),
                    array(
                        'key' => 'field_59159bb617317',
                        'label' => 'Badge Image Position (y)',
                        'name' => 'badge_image_position_y',
                        'type' => 'number',
                        'required' => 0,
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'min' => '',
                        'max' => '',
                        'step' => '',
                        'wrapper' => array(
                            'width' => '40',
                            'class' => '',
                            'id' => '',
                        ),
                    ),
                    array(
                        'key' => 'field_19159bb617306',
                        'label' => 'Recipient Name Affiché',
                        'name' => 'recipient_name_show',
                        'type' => 'true_false',
                        'required' => 0,
                        'default_value' => true,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'wrapper' => array(
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ),

                    ),
                    array(
                        'key' => 'field_59159bb617306',
                        'label' => 'Recipient Name Position (x)',
                        'name' => 'recipient_name_position_x',
                        'type' => 'number',
                        'required' => 0,
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'min' => '',
                        'max' => '',
                        'step' => '',
                        'wrapper' => array(
                            'width' => '40',
                            'class' => '',
                            'id' => '',
                        ),
                    ),
                    array(
                        'key' => 'field_59159bf417307',
                        'label' => 'Recipient Name Position (y)',
                        'name' => 'recipient_name_position_y',
                        'type' => 'number',
                        'required' => 0,
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'min' => '',
                        'max' => '',
                        'step' => '',
                        'wrapper' => array(
                            'width' => '40',
                            'class' => '',
                            'id' => '',
                        ),
                    ),
                    array(
                        'key' => 'field_19159c0617308',
                        'label' => 'Issue Date Affiché',
                        'name' => 'issue_date_show',
                        'type' => 'true_false',
                        'required' => 0,
                        'default_value' => true,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'wrapper' => array(
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ),

                    ),
                    array(
                        'key' => 'field_59159c0617308',
                        'label' => 'Issue Date Position (x)',
                        'name' => 'issue_date_position_x',
                        'type' => 'number',
                        'required' => 0,
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'min' => '',
                        'max' => '',
                        'step' => '',
                        'wrapper' => array(
                            'width' => '40',
                            'class' => '',
                            'id' => '',
                        ),
                    ),

                    array(
                        'key' => 'field_59159c2717309',
                        'label' => 'Issue Date Position (y)',
                        'name' => 'issue_date_position_y',
                        'type' => 'number',
                        'required' => 0,
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'min' => '',
                        'max' => '',
                        'step' => '',
                        'wrapper' => array(
                            'width' => '40',
                            'class' => '',
                            'id' => '',
                        ),
                    ),
                    array(
                        'key' => 'field_29159159271c4',
                        'label' => 'Autres éléments affichés',
                        'name' => 'badge_metas',
                        'type' => 'repeater',
                        'default_value' => true,
                        'sub_fields' => array(
                            array(
                                'key' => 'meta_titre',
                                'label' => 'Titre',
                                'name' => 'titre',
                                'type' => 'text',
                                'column_width' => '',
                                'default_value' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                                'maxlength' => '',
                            ),
                            array(
                                'key' => 'position_x',
                                'label' => 'Position X',
                                'name' => 'position_x',
                                'type' => 'number',
                                'column_width' => '',
                                'default_value' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                                'maxlength' => '',
                            ),
                            array(
                                'key' => 'position_y',
                                'label' => 'Position Y',
                                'name' => 'position_y',
                                'type' => 'number',
                                'column_width' => '',
                                'default_value' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                                'maxlength' => '',
                            ),
                        ),
                    ),
                    array(
                        'key' => 'field_59159d171730c',
                        'label' => 'Font Size',
                        'name' => 'font_size',
                        'type' => 'number',
                        'default_value' => 12,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'min' => '',
                        'max' => '',
                        'step' => '', 'wrapper' => array(
                        'width' => '20',
                        'class' => '',
                        'id' => '',
                    ),
                    ),
                    array(
                        'key' => 'field_59159c641730a',
                        'label' => 'Font Family',
                        'name' => 'font_family',
                        'type' => 'select',
                        'choices' => array(
                            'Courier' => 'Courier',
                            'Helvetica' => 'Helvetica',
                            'Times' => 'Times',
                        ),
                        'default_value' => 'Helvetica',
                        'allow_null' => 0,
                        'multiple' => 0,
                        'wrapper' => array(
                            'width' => '40',
                            'class' => '',
                            'id' => '',
                        ),
                    ),
                    array(
                        'key' => 'field_59159cda1730b',
                        'label' => 'Font Style',
                        'name' => 'font_style',
                        'type' => 'select',
                        'choices' => array(
                            'none' => 'Regular',
                            'B' => 'Bold',
                            'I' => 'Italic',
                            'U' => 'Underline',
                        ),
                        'default_value' => '',
                        'allow_null' => 0,
                        'multiple' => 0, 'wrapper' => array(
                        'width' => '40',
                        'class' => '',
                        'id' => '',
                    ),
                    ),

                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'certificate',//'badges',
                            'order_no' => 10,
                            'group_no' => 0,
                        ),
                    ),
                ),
                'options' => array(
                    'position' => 'normal',
                    'layout' => 'default',
                    'hide_on_screen' => array(),
                ),
                'menu_order' => 999,
            ));


            register_field_group(array(
                'id' => 'acf_badges',
                'title' => 'Certificate',
                'fields' => array(
                    array(
                        'key' => 'field_59159159271cd',
                        'label' => 'Certificate model',
                        'name' => 'certificate',
                        'type' => 'post_object',
                        'required' => 1,
                        'return_format' => 'object',
                        'post_type' => array(
                            0 => 'certificate',
                        ),
                        'taxonomy' => array(
                            0 => 'all',
                        ),
                        'filters' => array(
                            0 => 'search',
                        ),
                        'result_elements' => array(
                            0 => 'post_type',
                            1 => 'post_title',
                        ),
                        'max' => '',
                    ),


                    array(
                        'key' => 'field_29159159271cd',
                        'label' => 'Description',
                        'name' => 'badge_certificate_description',
                        'type' => 'textarea',
                    ),
                    array(
                        'key' => 'field_29159f59271c1',
                        'label' => 'Autres éléments affichés',
                        'name' => 'badge_metas',
                        'type' => 'repeater',
                        'default_value' => true,
                        'sub_fields' => array(
                            array(
                                'key' => 'titre',
                                'label' => 'Titre (ne pas modifier)',
                                'name' => 'titre',
                                'type' => 'text',
                                'column_width' => '30',
                                'default_value' => '',
                                'message' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                                'maxlength' => '',
                            ),
                            array(
                                'key' => 'texte',
                                'label' => 'Texte',
                                'name' => 'texte',
                                'type' => 'text',
                                'column_width' => '70',
                                'default_value' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                                'maxlength' => '',
                            ),

                        ),
                    ),
                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'badges',
                            'order_no' => 10,
                            'group_no' => 0,
                        ),
                    ),
                ),
                'options' => array(
                    'position' => 'normal',
                    'layout' => 'default',
                    'hide_on_screen' => array(),
                ),
                'menu_order' => 999,
            ));


        endif;

        flush_rewrite_rules();

    }

    function my_acf_prepare_field($field)
    {

        if ($field["key"] == "field_29159f59271c1") {
            $certificat = get_field("certificate");
            $metas = get_field("badge_metas", $certificat);
            if ($metas) {
                $field["min"] = count($metas);
                $field["max"] = count($metas);
                foreach ($metas as $k => $meta) {
                    $field["value"][$k]["titre"] = $meta["titre"];
                }
            } else {
                return false;
            }
        }


        return $field;
    }

    /**
     * Handles options page form submission
     * @param int $post_id Post ID
     */
    public function save_certificate_template($post_id)
    {
        $post = get_post($post_id);
        if ($post->post_type === "certificate")

            if ($post_id == 'options') {
                $archive = get_field('documents_archive', $post_id);
                $filename = get_attached_file($archive['ID']);
                if ($this->unzip_archive($filename) === true) {
                    unlink($filename);
                    delete_field('documents_archive', $post_id);
                    wp_delete_attachment($archive['ID'], true);
                }
            }
    }


    /**
     *
     * Displays badge certificate
     *
     * @since 1.0.0
     */
    public function display_certificate($preview = false)
    {
        $ok = false;
        if ($preview && is_admin()) {
            $ok = true;


            $badge_id = (int)$_GET["post"];

            $recipient_name = "John Doe";
            $recipient_url = '';

            $submission_date_modified = date_i18n("Y-m-d");


            $badge_name = get_the_title($badge_id);
            $badge_description = get_field('badge_certificate_description', $badge_id);
            $badge_image_url = wp_get_attachment_url(get_post_thumbnail_id($badge_id), 'full');
            $badge_criteria = get_post_meta($badge_id, 'badge_criteria');

            $certificate = get_field('certificate', $badge_id);
            $certificate_id = $certificate->ID;


        } elseif (preg_match("/^\/members\/([^\/]+)\/badges\/([^\/]+)\/certificate\/?$/", $_SERVER["REQUEST_URI"], $output_array)) {
            $ok = true;
            $user_name = $output_array[1];
            $badge_name = $output_array[2];

            $badge_id = $GLOBALS['badgefactor']->get_badge_id_by_slug($badge_name);
            $badge_image_url = wp_get_attachment_url(get_post_thumbnail_id($badge_id), 'full');

            $certificate = get_field('certificate', $badge_id);

            $certificate_id = $certificate->ID;
            //var_dump($user_name); exit;

            $user = get_user_by('slug', $user_name);

            $recipient_url = home_url() . str_replace('/certificate', '', $_SERVER["REQUEST_URI"]);

//var_dump($user); exit;

            $submission = $GLOBALS['badgefactor']->get_submission($badge_id, $user);
            $submission_date_modified = $submission->post_modified;
            $badge = $GLOBALS['badgefactor']->get_badge_by_submission($submission);
            $badge_criteria = get_post_meta($badge_id, 'badge_criteria');
            // $badge_description = $badge->post_content;
            $badge_description = get_field('badge_certificate_description', $badge_id);

            $achievement_id = get_post_meta($badge_id, '_badgeos_submission_achievement_id');

            if (!$badge_id || ($user->ID != wp_get_current_user()->ID && $GLOBALS['badgefactor']->is_achievement_private($submission->ID))) {

                // What to do if badge is private: redirect to user page
                $url = $_SERVER['REQUEST_URI'];
                $segments = explode('/', parse_url($url, PHP_URL_PATH));
                $user_path = '/' . $segments[1] . '/' . $segments[2];
                wp_safe_redirect($user_path);
                exit;
            }

            if($submission->post_type == "nomination"){
                $user_nominate = get_user_by('id', get_field("_badgeos_nomination_user_id",$submission->ID,true));
                $recipient_name = $user_nominate->display_name ;
            }else{
                $recipient_name = bp_core_get_user_displayname($submission->post_author);
            }
        }

        if ($ok) {

            // Get field values
            if ($recipient_name_active = get_field('recipient_name_show', $certificate_id)) {
                $recipient_name_position_x = get_field('recipient_name_position_x', $certificate_id);
                $recipient_name_position_y = get_field('recipient_name_position_y', $certificate_id);
            }

            if ($badge_name_active = get_field('badge_name_show', $certificate_id)) {
                $badge_name_position_x = get_field('badge_name_position_x', $certificate_id);
                $badge_name_position_y = get_field('badge_name_position_y', $certificate_id);
            }

            if ($badge_description_active = get_field('badge_description_show', $certificate_id)) {
                $badge_description_position_x = get_field('badge_description_position_x', $certificate_id);
                $badge_description_position_y = get_field('badge_description_position_y', $certificate_id);
                $badge_description_alignement = get_field('badge_description_alignement', $certificate_id);
                $badge_description_largeur = get_field('badge_description_largeur', $certificate_id);

            }


            if ($badge_image_active = get_field('badge_image_show', $certificate_id)) {
                $badge_image_position_x = get_field('badge_image_position_x', $certificate_id);
                $badge_image_position_y = get_field('badge_image_position_y', $certificate_id);
            }

            if ($issue_date_active = get_field('issue_date_show', $certificate_id)) {
                setlocale(LC_TIME, get_locale(), 0);
                $issue_date = strftime('%e %B %G', (strtotime($submission_date_modified)));
                $issue_date_position_x = get_field('issue_date_position_x', $certificate_id);
                $issue_date_position_y = get_field('issue_date_position_y', $certificate_id);
            }


            /*
                        $recipient_name_position_x = -1;
                        $recipient_name_position_y = 83;

                        $badge_name_position_x = -1;
                        $badge_name_position_y = 106;

                        $badge_description_position_x = -1;
                        $badge_description_position_y = 130;

                        $badge_criteria_position_x = 10;
                        $badge_criteria_position_y = 135;

                        $badge_image_position_x = 15;
                        $badge_image_position_y = 80;

                        $issue_date_position_x = 23;
                        $issue_date_position_y = 165;
            */
            $badge_cert = get_field('template', $certificate_id);
            $cert_path = get_attached_file($badge_cert["id"]);
            $pdf_file = $cert_path;//ltrim(parse_url($badge_cert['url'], PHP_URL_PATH), '/');

            if ($certificate_id && (($preview && is_admin()) || $user->ID === wp_get_current_user()->ID || !$GLOBALS['badgefactor']->is_achievement_private($submission->ID))) {
                $pdf = new FPDI();
                $pdf->setSourceFile($pdf_file);
                $templateId = $pdf->importPage(1);
                $size = $pdf->getTemplateSize($templateId);
                $w = $size['w'];
                $h = $size['h'];
                $pdf->AddPage('L');
                $pdf->useTemplate($templateId, null, null, $w, $h, TRUE);


                $pdf->SetMargins(15, 15, 15);


                if ($recipient_name_active) {
                    // Recipient name
                    // TODO Get Font Family, Type and Size
                    $pdf->SetFont('Helvetica', '', 18);
                    $pdf->SetXY(
                    //positionX
                        (($recipient_name_position_x == '-1') ? ($w / 2 - $pdf->GetStringWidth($recipient_name) / 2) : $recipient_name_position_x),
                        //positionY
                        (($recipient_name_position_y == '-1') ? ($h / 2 - $pdf->GetStringHeight($recipient_name) / 2) : $recipient_name_position_y)
                    );
                    $pdf->PutLink($recipient_url, utf8_decode($recipient_name));

                }

                if ($badge_name_active) {
                    // Badge name
                    // TODO Get Font Family, Type and Size
                    $pdf->SetFont('Helvetica', '', 20);
                    $pdf->SetXY(
                    //positionX
                        (($badge_name_position_x == '-1') ? ($w / 2 - $pdf->GetStringWidth($badge_name) / 2) : $badge_name_position_x),
                        //positionY
                        (($badge_name_position_y == '-1') ? ($h / 2 - $pdf->GetStringHeight($badge_name) / 2) : $badge_name_position_y)
                    );

                    // $pdf->Cell($badge_name_position_x, $badge_name_position_y, utf8_decode($badge_name), 0, '', "L");
                    $pdf->WriteHTML(utf8_decode($badge_name));
                }


                if ($badge_image_active) {
                    // Badge image
                    $pdf->SetXY($badge_image_position_x, $badge_image_position_y);
                    $pdf->Image($badge_image_url, $badge_image_position_x, $badge_image_position_y, -300, 0, '', $recipient_url);

                }

                if ($issue_date_active) {
                    // Issued date
                    // TODO Get Font Family, Type and Size
                    $pdf->SetFont('Helvetica', '', 12);
                    $pdf->SetXY(
                    //positionX
                        (($issue_date_position_x == '-1') ? ($w / 2 - $pdf->GetStringWidth($issue_date) / 2) : $issue_date_position_x),
                        //positionY
                        (($issue_date_position_y == '-1') ? ($h / 2 - $pdf->GetStringHeight($issue_date) / 2) : $issue_date_position_y)
                    );
                    $pdf->WriteHTML(($issue_date));
                }


                if ($metas_cert = get_field('badge_metas', $certificate_id)) {
                    $metas_badges = get_field('badge_metas', $badge_id);
                    foreach ($metas_cert as $k => $meta) {
                        //  var_dump($meta); exit;
                        $pdf->SetFont('Helvetica', '', 12);


                        $pdf->SetXY(
                        //positionX
                            (($meta["position_x"] == '-1') ? ($w / 2 - $pdf->GetStringWidth($metas_badges[$k]["texte"]) / 2) : $meta["position_x"]),
                            $meta["position_y"]

                        );
                        $pdf->WriteHTML(($metas_badges[$k]["texte"]));
                    }
                }

                if ($badge_description_active) {
                    // submission description
                    // TODO Get Font Family, Type and Size
                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY($badge_description_position_x, $badge_description_position_y);


                    if ($badge_description_largeur == -1) {
                        $badge_description_largeur = $w - ($badge_description_position_x * 2);
                    }
                    $pdf->MultiCell($badge_description_largeur, 5, utf8_decode($badge_description), 0, $badge_description_alignement);
                }


                //$pdf->Cell($issue_date_position_x, $issue_date_position_y, $issue_date, 0, '', "L");

                // Output badge name
                $pdf->Output('I', $badge_name . '.pdf');
                exit;
            }
        }
    }


    public function preview_certificate_template()
    {
        if (isset($_GET["preview_certificate"])) {
            self::display_certificate(true);
            exit;
        }

        if (isset($_GET["view_certificate"])) {

            self::display_certificate(true);
            exit;
        }

    }


    function badgefactor_certificate_meta_boxes()
    {
        if (get_field("certificate")) {
            add_meta_box('preview_certificate', 'Prévisualiser le certificat', array($this, 'badgefactor_certificate_preview_callback'), 'badges', 'side', 'high');
        }

        add_meta_box('view_certificate', 'Voir le certificat', array($this, 'badgefactor_certificate_view_callback'), 'submission', 'side', 'high');
        add_meta_box('view_certificate', 'Voir le certificat', array($this, 'badgefactor_certificate_view_callback'), 'nomination', 'side', 'high');

    }


    function badgefactor_certificate_preview_callback($post)
    {

        ?>
        <p style="text-align: right">
            <a class="button button-primary button-large" target="_blank"
               href="post.php?post=<?php echo $post->ID ?>&action=edit&preview_certificate=true">Prévisualiser le
                certificat</a>
        </p>
        <?php
    }


    function badgefactor_certificate_view_callback($post)
    {
        $p = get_post($post);
        if ($p->post_type == "nomination") {
            $user = get_user_by('id', get_field("_badgeos_nomination_user_id", $post));
        } else {
            $user = get_user_by('id', $p->post_author);
        }
        $username = $user->data->user_nicename;
        $badge = $GLOBALS['badgefactor']->get_badge_by_submission($p);

        $certificate = get_field('certificate', $badge->ID);
        if ($certificate) {
            ?>
            <p style="text-align: right">
                <a class="button button-primary button-large" target="_blank"
                   href="<?php echo home_url() ?>/members/<?php echo $username ?>/badges/<?php echo $badge->post_name ?>/certificate/">Voir
                    le
                    certificat</a>
            </p>
            <?php
        } else {
            ?>Aucun certificat assigné au Badge<?php
        }
    }

}


function load_badgefactor_cert()
{
    $GLOBALS['badgefactor']->cert = new BadgeFactorCertificates();
}

add_action('plugins_loaded', 'load_badgefactor_cert');

