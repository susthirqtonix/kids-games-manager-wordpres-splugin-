<?php
/**
 * Plugin Name: Kids Games Manager
 * Description: Add games, select active game, and display via shortcode anywhere.
 * Version: 1.1
 * Author: Susthir/Qtonix
 */

if (!defined('ABSPATH')) exit;

class Kids_Games_Manager {

    const CPT = 'kids_game';
    const META_EMBED = '_kgm_embed';
    const OPTION_ACTIVE = 'kgm_active_game';

    public function __construct() {

        // Enqueue frontend CSS
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Game image metabox
        add_action('add_meta_boxes', [$this, 'add_game_image_metabox']);
        add_action('save_post_' . self::CPT, [$this, 'save_game_image']);

        // Instructions page
        add_action('admin_menu', [$this, 'add_instructions_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // CPT & admin UI
        add_action('init', [$this, 'register_cpt']);
        add_action('add_meta_boxes', [$this, 'add_embed_metabox']);
        add_action('save_post_' . self::CPT, [$this, 'save_embed_code']);

        // Settings page
        add_action('admin_menu', [$this, 'settings_page']);
        add_action('admin_init', [$this, 'register_option']);

        // Shortcode
        add_shortcode('kids_game_display', [$this, 'shortcode_output']);
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'kgm-styles',
            plugin_dir_url(__FILE__) . 'assets/style.css',
            [],
            '1.1'
        );
    }

    /* =======================
       GAME IMAGE META
    ======================== */

    public function add_game_image_metabox() {
        add_meta_box(
            'kgm_game_image',
            'Game Preview Image',
            [$this, 'render_game_image_metabox'],
            self::CPT,
            'side',
            'low'
        );
    }

    public function render_game_image_metabox($post) {

        wp_nonce_field('kgm_save_game_image', 'kgm_game_image_nonce');

        $image_id = get_post_meta($post->ID, '_kgm_game_image', true);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
        ?>
        <div id="kgm-game-image-wrapper">
            <?php if ($image_url): ?>
                <img src="<?php echo esc_url($image_url); ?>" style="width:100%;border-radius:6px;margin-bottom:10px;" />
            <?php endif; ?>
        </div>

        <input type="hidden" id="kgm-game-image" name="kgm_game_image" value="<?php echo esc_attr($image_id); ?>" />

        <button type="button" class="button" id="kgm-upload-btn">Upload / Select Image</button>
        <button type="button" class="button" id="kgm-remove-btn" style="margin-top:6px;">Remove</button>

        <script>
        jQuery(document).ready(function($){
            var mediaUploader;

            $('#kgm-upload-btn').click(function(e){
                e.preventDefault();

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: 'Select Game Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });

                mediaUploader.on('select', function(){
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#kgm-game-image').val(attachment.id);
                    $('#kgm-game-image-wrapper').html('<img src="'+attachment.url+'" style="width:100%;border-radius:6px;margin-bottom:10px;" />');
                });

                mediaUploader.open();
            });

            $('#kgm-remove-btn').click(function(){
                $('#kgm-game-image').val('');
                $('#kgm-game-image-wrapper').html('');
            });
        });
        </script>

        <?php
    }

    public function save_game_image($post_id) {

        if (!isset($_POST['kgm_game_image_nonce']) || !wp_verify_nonce($_POST['kgm_game_image_nonce'], 'kgm_save_game_image')) {
            return;
        }

        if (isset($_POST['kgm_game_image'])) {
            update_post_meta($post_id, '_kgm_game_image', intval($_POST['kgm_game_image']));
        }
    }

    /* =======================
       INSTRUCTIONS PAGE
    ======================== */

    public function add_instructions_page() {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            'Instructions',
            'Instructions',
            'manage_options',
            'kgm-instructions',
            [$this, 'render_instructions_page']
        );
    }

    public function render_instructions_page() {
        ?>
        <div class="wrap">
            <h1>How to Use Kids Games Plugin</h1>

            <h2>1. Adding a Game</h2>
            <ol>
                <li>Go to <strong>Games → Add Game</strong>.</li>
                <li>Enter a game title.</li>
                <li>Paste the embed code into the field.</li>
                <li>Upload/select a preview image.</li>
                <li>Publish.</li>
            </ol>

            <h2>2. Selecting Active Game</h2>
            <ol>
                <li>Go to <strong>Settings → Kids Games</strong>.</li>
                <li>Select the game visually.</li>
                <li>Save changes.</li>
            </ol>

            <h2>3. Display on Website</h2>
            <pre>[kids_game_display]</pre>

            <p>Use inside Elementor, Gutenberg, or widgets.</p>
        </div>
        <?php
    }

    /* =======================
       CPT
    ======================== */

    public function register_cpt() {
        $labels = [
            'name'               => 'Games',
            'singular_name'      => 'Game',
            'menu_name'          => 'Games',
            'name_admin_bar'     => 'Game',
            'add_new'            => 'Add Game',
            'add_new_item'       => 'Add New Game',
            'edit_item'          => 'Edit Game',
            'new_item'           => 'New Game',
            'view_item'          => 'View Game',
            'search_items'       => 'Search Games',
            'all_items'          => 'All Games',
        ];

        register_post_type(self::CPT, [
            'labels'     => $labels,
            'public'     => false,
            'show_ui'    => true,
            'menu_icon'  => 'dashicons-games',
            'supports'   => ['title', 'thumbnail']
        ]);
    }

    /* =======================
       EMBED FIELD
    ======================== */

    public function add_embed_metabox() {
        add_meta_box(
            'kgm_embed_box',
            'Game Embed Code',
            [$this, 'render_embed_metabox'],
            self::CPT,
            'normal',
            'high'
        );
    }

    public function render_embed_metabox($post) {
        wp_nonce_field('kgm_save_embed', 'kgm_embed_nonce');
        $value = get_post_meta($post->ID, self::META_EMBED, true);
        echo '<textarea name="kgm_embed" style="width:100%;min-height:160px;">' . esc_textarea($value) . '</textarea>';
    }

    public function save_embed_code($post_id) {
        if (!isset($_POST['kgm_embed_nonce']) || !wp_verify_nonce($_POST['kgm_embed_nonce'], 'kgm_save_embed')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['kgm_embed'])) {
            update_post_meta($post_id, self::META_EMBED, $_POST['kgm_embed']);
        }
    }

    /* =======================
       SETTINGS PAGE (GRID UI)
    ======================== */

    public function settings_page() {
        add_options_page(
            'Kids Games Settings',
            'Kids Games',
            'manage_options',
            'kids-games-settings',
            [$this, 'settings_page_html']
        );
    }

    public function register_option() {
        register_setting('kgm_settings_group', self::OPTION_ACTIVE);
    }

    public function settings_page_html() {
        $active = get_option(self::OPTION_ACTIVE);
        $games = get_posts([
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'post_status'    => 'publish'
        ]);
        ?>
        <div class="wrap">
            <h1>Select Active Game</h1>

            <form method="post" action="options.php">
                <?php settings_fields('kgm_settings_group'); ?>

                <div class="kgm-game-selector">
                    <?php foreach ($games as $game):
                        $image_id = get_post_meta($game->ID, '_kgm_game_image', true);
                        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
                        ?>

                        <label class="kgm-game-card <?php echo ($active == $game->ID ? 'active' : ''); ?>">

                            <?php if ($image_url): ?>
                                <img src="<?php echo esc_url($image_url); ?>" class="kgm-thumb" />
                            <?php else: ?>
                                <div class="kgm-no-image">No Image</div>
                            <?php endif; ?>

                            <span class="kgm-title"><?php echo esc_html($game->post_title); ?></span>

                        

                            <div class="kgm-radio-holder">
                                <input type="radio" name="<?php echo self::OPTION_ACTIVE; ?>" value="<?php echo $game->ID; ?>" <?php checked($active, $game->ID); ?> />
                            </div>

                        </label>


                    <?php endforeach; ?>
                </div>

                <?php submit_button(); ?>
            </form>
                <div id="kgm-preview-modal" style="display:none;">
                <div id="kgm-preview-content">
                    <button id="kgm-close-preview" class="button">Close</button>
                    <div id="kgm-preview-frame"></div>
                </div>
            </div>

            <script>
            jQuery(function($){
                $(".kgm-preview-btn").on("click", function(){
                    var embed = $(this).data("embed");
                    $("#kgm-preview-frame").html(embed);
                    $("#kgm-preview-modal").fadeIn();
                });

                $("#kgm-close-preview").on("click", function(){
                    $("#kgm-preview-frame").html("");
                    $("#kgm-preview-modal").fadeOut();
                });
            });
            </script>

        </div>
        <?php
    }

    /* =======================
       SHORTCODE OUTPUT
    ======================== */

    public function shortcode_output() {
        $active = get_option(self::OPTION_ACTIVE);
        if (!$active) return;

        $embed = get_post_meta($active, self::META_EMBED, true);
        if (!$embed) return;

        return '<div class="kids-active-game">' . $embed . '</div>';
    }
}

new Kids_Games_Manager();
