<?php
if (!defined('ABSPATH')) { exit; }

final class CFP_Admin {

    public static function init(){
        add_action('add_meta_boxes', [__CLASS__, 'add_box']);
        add_action('save_post_product', [__CLASS__, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function add_box(){
        add_meta_box(
            'cfp_frames_box',
            __('Custom Frames', 'wc-cfp'),
            [__CLASS__, 'render_box'],
            'product',
            'normal',
            'default'
        );
    }

    public static function assets($hook){
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'product') return;
        wp_enqueue_media();
        wp_enqueue_style('cfp-admin', CFP_URL.'assets/css/cfp-admin.css', [], '1.6.0');
        wp_enqueue_script('cfp-admin', CFP_URL.'assets/js/cfp-admin.js', ['jquery', 'jquery-ui-draggable', 'jquery-ui-resizable'], '1.6.0', true);
    }

    public static function render_box($post){
        wp_nonce_field('cfp_frames_nonce', 'cfp_frames_nonce');
        $frames = get_post_meta($post->ID, '_cfp_frames', true);
        if (!is_array($frames)) $frames = [];
        $use_featured = get_post_meta($post->ID, '_cfp_use_featured_as_frame', true);
        ?>
        <p><label><input type="checkbox" name="cfp_use_featured_as_frame" value="1" <?php checked($use_featured, '1'); ?> />
            <?php esc_html_e('Use featured image as default frame (auto 1 cm margin if overlay missing)', 'wc-cfp'); ?></label></p>

        <div id="cfp-frames-repeater">
            <div class="cfp-rows">
                <?php if (empty($frames)) : ?>
                    <?php self::row_tpl(0, [], true); ?>
                <?php else: ?>
                    <?php foreach($frames as $idx=>$f){ self::row_tpl($idx, $f, false); } ?>
                <?php endif; ?>
            </div>
            <p><a class="button button-secondary" id="cfp-add-row"><?php esc_html_e('Add frame','wc-cfp'); ?></a></p>
        </div>
        <?php
    }

    private static function row_tpl($idx, $f, $empty){
        $aid = isset($f['attachment_id']) ? intval($f['attachment_id']) : 0;
        $thumb = $aid ? wp_get_attachment_image($aid, [80,80]) : '';
        $full = $aid ? wp_get_attachment_url($aid) : '';
        $o = isset($f['overlay']) ? $f['overlay'] : [];
        ?>
        <div class="cfp-row">
            <div class="cfp-col">
                <label><?php esc_html_e('Label', 'wc-cfp'); ?></label>
                <input type="text" name="cfp_frames[<?php echo esc_attr($idx); ?>][label]" value="<?php echo esc_attr($f['label'] ?? ''); ?>" class="widefat" />
            </div>
            <div class="cfp-col">
                <label><?php esc_html_e('Frame image', 'wc-cfp'); ?></label>
                <div class="cfp-media">
                    <input type="hidden" name="cfp_frames[<?php echo esc_attr($idx); ?>][attachment_id]" value="<?php echo esc_attr($aid); ?>" class="cfp-attach-id" />
                    <button class="button cfp-pick"><?php esc_html_e('Select image','wc-cfp'); ?></button>
                    <div class="cfp-thumb" data-full="<?php echo esc_url($full); ?>"><?php echo $thumb; ?></div>
                </div>
            </div>
            <div class="cfp-col cfp-overlay">
                <label><?php esc_html_e('Overlay (x,y,w,h,rotation,ratio)', 'wc-cfp'); ?></label>
                <div class="cfp-grid">
                    <input placeholder="x" name="cfp_frames[<?php echo esc_attr($idx); ?>][overlay][x]" type="number" step="0.01" value="<?php echo esc_attr($o['x'] ?? ''); ?>" />
                    <input placeholder="y" name="cfp_frames[<?php echo esc_attr($idx); ?>][overlay][y]" type="number" step="0.01" value="<?php echo esc_attr($o['y'] ?? ''); ?>" />
                    <input placeholder="w" name="cfp_frames[<?php echo esc_attr($idx); ?>][overlay][w]" type="number" step="0.01" value="<?php echo esc_attr($o['w'] ?? ''); ?>" />
                    <input placeholder="h" name="cfp_frames[<?php echo esc_attr($idx); ?>][overlay][h]" type="number" step="0.01" value="<?php echo esc_attr($o['h'] ?? ''); ?>" />
                    <input placeholder="rotation" name="cfp_frames[<?php echo esc_attr($idx); ?>][overlay][rotation]" type="number" step="0.01" value="<?php echo esc_attr($o['rotation'] ?? ''); ?>" />
                    <input placeholder="ratio" name="cfp_frames[<?php echo esc_attr($idx); ?>][overlay][ratio]" type="number" step="0.0001" value="<?php echo esc_attr($o['ratio'] ?? ''); ?>" />
                </div>
                <p><button type="button" class="button cfp-set-overlay"><?php esc_html_e('Select area','wc-cfp'); ?></button></p>
            </div>
            <a class="button-link delete cfp-remove"><?php esc_html_e('Remove','wc-cfp'); ?></a>
        </div>
        <?php
    }

    public static function save($post_id, $post){
        if (!isset($_POST['cfp_frames_nonce']) || !wp_verify_nonce($_POST['cfp_frames_nonce'], 'cfp_frames_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'product') return;

        $frames = isset($_POST['cfp_frames']) && is_array($_POST['cfp_frames']) ? $_POST['cfp_frames'] : [];
        $clean = [];
        foreach ($frames as $f){
            $o = isset($f['overlay']) ? $f['overlay'] : [];
            $clean[] = [
                'label' => isset($f['label']) ? sanitize_text_field($f['label']) : '',
                'attachment_id' => isset($f['attachment_id']) ? intval($f['attachment_id']) : 0,
                'overlay' => [
                    'x' => isset($o['x']) && $o['x'] !== '' ? floatval($o['x']) : null,
                    'y' => isset($o['y']) && $o['y'] !== '' ? floatval($o['y']) : null,
                    'w' => isset($o['w']) && $o['w'] !== '' ? floatval($o['w']) : null,
                    'h' => isset($o['h']) && $o['h'] !== '' ? floatval($o['h']) : null,
                    'rotation' => isset($o['rotation']) && $o['rotation'] !== '' ? floatval($o['rotation']) : 0,
                    'ratio' => isset($o['ratio']) && $o['ratio'] !== '' ? floatval($o['ratio']) : 1,
                ]
            ];
        }
        update_post_meta($post_id, '_cfp_frames', $clean);
        update_post_meta($post_id, '_cfp_use_featured_as_frame', isset($_POST['cfp_use_featured_as_frame']) ? '1' : '0');
    }
}
