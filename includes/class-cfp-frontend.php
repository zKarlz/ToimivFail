<?php
if (!defined('ABSPATH')) { exit; }

final class CFP_Frontend {

    public static function init(){
        // AJAX endpoints
        add_action('wp_ajax_cfp_upload_mockup', [__CLASS__, 'ajax_upload']);
        add_action('wp_ajax_nopriv_cfp_upload_mockup', [__CLASS__, 'ajax_upload']);
        // Frontend boot
        add_action('init', [__CLASS__, 'bootstrap']);
    }

    public static function bootstrap(){
        // Assets and UI block
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('woocommerce_before_add_to_cart_button', [__CLASS__, 'render_controls'], 5);
        add_action('woocommerce_after_add_to_cart_button', [__CLASS__, 'render_hidden_fallback'], 99);

        // Cart/session/order plumbing
        add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', [__CLASS__, 'from_session'], 10, 2);
        add_filter('woocommerce_get_item_data', [__CLASS__, 'cart_line_preview'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'attach_order_meta'], 10, 4);
        add_action('woocommerce_order_item_meta_end', [__CLASS__, 'frontend_order_preview'], 10, 3);
        add_action('woocommerce_after_order_itemmeta', [__CLASS__, 'admin_order_preview'], 10, 3);
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_before_add'], 9, 3);
    }

    /** Enqueue styles/scripts and localize frame data */
    public static function assets(){
        // Styles
        wp_register_style('cfp-frontend', CFP_URL.'assets/css/cfp-frontend.css', [], '1.6.0');
        wp_enqueue_style('cfp-frontend');

        // Scripts
        wp_register_script('cfp-frontend', CFP_URL.'assets/js/cfp-frontend.js', ['jquery'], '1.6.0', true);

        $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
        $frames  = [];
        $use_featured = $product ? get_post_meta($product->get_id(), '_cfp_use_featured_as_frame', true) : '0';

        if ($product && function_exists('cfp_get_frames_meta')) {
            $meta = cfp_get_frames_meta($product->get_id());
            if (is_array($meta)) {
                foreach ($meta as $f) {
                    $aid   = isset($f['attachment_id']) ? intval($f['attachment_id']) : 0;
                    $url   = $aid ? wp_get_attachment_image_url($aid, 'full') : '';
                    $thumb = $aid ? wp_get_attachment_image_url($aid, 'woocommerce_thumbnail') : '';
                    if ($url) {
                        $frames[] = [
                            'label'   => $f['label'],
                            'url'     => $url,
                            'thumb'   => $thumb ?: $url,
                            'overlay' => $f['overlay'],
                        ];
                    }
                }
            }
        }

        // Default to featured image when allowed and no frames given
        if (empty($frames) && $product && $use_featured === '1') {
            $thumb_id = get_post_thumbnail_id($product->get_id());
            if ($thumb_id) {
                $full  = wp_get_attachment_image_url($thumb_id, 'full');
                $thumb = wp_get_attachment_image_url($thumb_id, 'woocommerce_thumbnail');
                if ($full) {
                    $frames[] = [
                        'label'   => 'Frame 1',
                        'url'     => $full,
                        'thumb'   => $thumb ?: $full,
                        'overlay' => [],
                    ];
                }
            }
        }

        wp_localize_script('cfp-frontend', 'CFP', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('cfp_ajax'),
            'productId' => $product ? $product->get_id() : 0,
            'frames'    => $frames,
            'jpegQ'     => (float) apply_filters('cfp/jpeg_quality', 0.9),
            'maxMB'     => (int) apply_filters('cfp/max_upload_mb', 15),
            'i18n'      => [
                'select' => __('Select your frame', 'wc-cfp'),
                'upload' => __('Upload your image', 'wc-cfp'),
                'dragOr' => __('Drag a file here or', 'wc-cfp'),
                'browse' => __('browse', 'wc-cfp'),
                'editorTitle' => __('Edit your photo', 'wc-cfp'),
                'apply'  => __('Apply', 'wc-cfp'),
                'cancel' => __('Cancel', 'wc-cfp'),
                'invalid'=> __('Invalid file type. Please upload JPG or PNG.', 'wc-cfp'),
                'tooBig' => __('File is too big.', 'wc-cfp'),
            ],
        ]);
        wp_enqueue_script('cfp-frontend');
    }

    /** Render the controls on the product page */
    public static function render_controls(){
        wp_nonce_field('cfp_nonce', 'cfp_nonce');
        ?>
        <div class="cfp-controls">
            <h4 class="cfp-title-frames"><?php esc_html_e('Select your frame', 'wc-cfp'); ?></h4>
            <div class="cfp-frames" role="list"></div>

            <h4 class="cfp-title-upload"><?php esc_html_e('Upload your image', 'wc-cfp'); ?></h4>
            <div class="cfp-drop">
                <div class="cfp-drop-msg">
                    <span class="cfp-drag"><?php esc_html_e('Drag a file here or', 'wc-cfp'); ?></span>
                    <label class="cfp-browse"><input type="file" accept="image/jpeg,image/png" hidden class="cfp-file"/><span><?php esc_html_e('browse','wc-cfp'); ?></span></label>
                </div>
                <div class="cfp-file-meta"></div>
            </div>

            <input type="hidden" name="cfp_image_data" id="cfp_image_data" />
            <input type="hidden" name="cfp_image_url" id="cfp_image_url" class="cfp-image-url" />
            <input type="hidden" name="cfp_original_url" id="cfp_original_url" class="cfp-original-url" />
            <input type="hidden" name="cfp_frame_label" id="cfp_frame_label" class="cfp-frame-label" />
            <p class="cfp-note"><em><?php esc_html_e('Add to cart will be enabled after you apply your image in the editor.', 'wc-cfp'); ?></em></p>
        </div>

        <div class="cfp-modal-backdrop" id="cfp-modal-bg" style="display:none"></div>
        <div class="cfp-modal" id="cfp-modal" role="dialog" aria-modal="true" aria-labelledby="cfp-modal-title" style="display:none">
          <div class="cfp-modal-panel">
            <div class="cfp-modal-header">
              <div id="cfp-modal-title" class="cfp-modal-title"><?php esc_html_e('Edit your photo', 'wc-cfp'); ?></div>
              <button type="button" class="cfp-close" aria-label="<?php esc_attr_e('Close','wc-cfp');?>">×</button>
            </div>
            <div class="cfp-modal-body">
              <div class="cfp-editor-wrap">
                <img class="cfp-editor-frame" alt="frame"/>
                <div class="cfp-editor-overlay">
                  <img class="cfp-editor-user" alt=""/>
                </div>
              </div>
            </div>
            <div class="cfp-modal-toolbar">
              <button type="button" class="button cfp-m-rot-left">⟲</button>
              <button type="button" class="button cfp-m-rot-right">⟳</button>
              <button type="button" class="button cfp-m-flip-h">⇋</button>
              <button type="button" class="button cfp-m-flip-v">⇅</button>
              <button type="button" class="button button-primary cfp-m-apply"><?php esc_html_e('Apply','wc-cfp');?></button>
              <button type="button" class="button cfp-m-cancel"><?php esc_html_e('Cancel','wc-cfp');?></button>
            </div>
          </div>
        </div>
        <?php
    }

    public static function render_hidden_fallback(){
        echo '<input type="hidden" name="cfp_image_url" class="cfp-image-url" value="" />';
        echo '<input type="hidden" name="cfp_image_data" id="cfp_image_data_fallback" value="" />';
        echo '<input type="hidden" name="cfp_original_url" class="cfp-original-url" value="" />';
        echo '<input type="hidden" name="cfp_frame_label" class="cfp-frame-label" value="" />';
    }

    /** Validate presence of preview before add-to-cart */
    public static function validate_before_add($passed, $product_id, $qty){
        if (!isset($_POST['cfp_nonce'])) return $passed;

        $has_url  = !empty($_POST['cfp_image_url']) && filter_var($_POST['cfp_image_url'], FILTER_VALIDATE_URL);
        $has_data = !empty($_POST['cfp_image_data']) && is_string($_POST['cfp_image_data']) && strpos($_POST['cfp_image_data'], 'data:image/') === 0;

        // Try WC session (AJAX upload stored it)
        if (!$has_url && function_exists('WC') && WC()->session) {
            $sess = WC()->session->get('cfp_last_url');
            if ($sess && filter_var($sess, FILTER_VALIDATE_URL)) {
                $_POST['cfp_image_url'] = esc_url_raw($sess);
                $has_url = true;
            }
        }

        // If still no URL, but we have data URL, let it pass (we will persist in add_cart_item_data)
        if (!$has_url && !$has_data) {
            wc_add_notice(__('Please apply your photo before adding to cart.', 'wc-cfp'), 'error');
            return false;
        }
        return true;
    }

    /** Save cart item data and ensure preview URL exists */
    public static function add_cart_item_data($cart_item_data, $product_id, $variation_id){
        if (isset($_POST['cfp_nonce']) && !wp_verify_nonce($_POST['cfp_nonce'], 'cfp_nonce')) {
            return $cart_item_data;
        }
        $preview  = !empty($_POST['cfp_image_url']) && filter_var($_POST['cfp_image_url'], FILTER_VALIDATE_URL) ? esc_url_raw($_POST['cfp_image_url']) : '';
        $original = !empty($_POST['cfp_original_url']) && filter_var($_POST['cfp_original_url'], FILTER_VALIDATE_URL) ? esc_url_raw($_POST['cfp_original_url']) : '';
        $label    = isset($_POST['cfp_frame_label']) ? sanitize_text_field($_POST['cfp_frame_label']) : '';

        // Try session
        if (!$preview && function_exists('WC') && WC()->session) {
            $sess = WC()->session->get('cfp_last_url');
            if ($sess && filter_var($sess, FILTER_VALIDATE_URL)) {
                $preview = esc_url_raw($sess);
            }
        }

        // Decode data URL fallback into file
        if (!$preview && !empty($_POST['cfp_image_data']) && is_string($_POST['cfp_image_data']) && strpos($_POST['cfp_image_data'], 'data:image/') === 0) {
            if (preg_match('/^data:image\/(png|jpe?g);base64,(.+)$/', $_POST['cfp_image_data'], $mm)) {
                $ext = ($mm[1]==='png') ? 'png' : 'jpg';
                $bin = base64_decode($mm[2]);
                if ($bin) {
                    $up = wp_upload_dir();
                    $dir = trailingslashit($up['basedir']).'wc-cfp';
                    $urlb= trailingslashit($up['baseurl']).'wc-cfp';
                    if (!is_dir($dir)) { wp_mkdir_p($dir); }
                    $name = 'cfp-'.time().'-'.wp_generate_password(6,false,false).'.'.$ext;
                    $path = trailingslashit($dir).$name;
                    if (file_put_contents($path, $bin) !== false) {
                        $preview = trailingslashit($urlb).$name;
                    }
                }
            }
        }

        if ($preview || $original || $label) {
            $cart_item_data['cfp_image'] = [
                'url'          => $preview,
                'original_url' => $original,
                'frame_label'  => $label,
                'when'         => time(),
            ];
            $cart_item_data['unique_key'] = md5( maybe_serialize($cart_item_data['cfp_image']) ) . wp_generate_password(4, false, false);
        }

        // clear session key
        if (function_exists('WC') && WC()->session) { WC()->session->__unset('cfp_last_url'); }

        return $cart_item_data;
    }

    public static function from_session($cart_item, $values){
        if (isset($values['cfp_image'])) { $cart_item['cfp_image'] = $values['cfp_image']; }
        return $cart_item;
    }

    /** Show preview as line item data in cart/checkout */
    public static function cart_line_preview($item_data, $cart_item){
        $url = isset($cart_item['cfp_image']['url']) ? $cart_item['cfp_image']['url'] : '';
        if ($url) {
            $item_data[] = [
                'key'   => __('Preview', 'wc-cfp'),
                'value' => '<img src="'.esc_url($url).'" style="max-width:120px;height:auto;border:1px solid #ddd;border-radius:4px;" />',
            ];
        }
        return $item_data;
    }

    /** Attach metadata to order item */
    public static function attach_order_meta($item, $cart_item_key, $values, $order){
        if (!empty($values['cfp_image']['url'])) {
            $item->add_meta_data('Custom Photo URL', esc_url_raw($values['cfp_image']['url']), true);
        }
        if (!empty($values['cfp_image']['original_url'])) {
            $item->add_meta_data('Original Photo URL', esc_url_raw($values['cfp_image']['original_url']), true);
        }
        if (!empty($values['cfp_image']['frame_label'])) {
            $item->add_meta_data('Frame', sanitize_text_field($values['cfp_image']['frame_label']), true);
        }
    }

    /** My Account order view preview */
    public static function frontend_order_preview($item_id, $item, $order){
        $prev = $item->get_meta('Custom Photo URL', true);
        if ($prev) {
            echo '<div class="cfp-order-preview" style="margin-top:6px"><img src="'.esc_url($prev).'" style="max-width:150px;height:auto;border:1px solid #ddd;border-radius:4px;" /></div>';
        }
    }

    /** Admin order view preview + original with buttons */
    public static function admin_order_preview($item_id, $item, $product){
        if (!is_object($item)) return;
        $orig = $item->get_meta('Original Photo URL', true);
        if ($orig){
            echo '<div class="cfp-admin-preview" style="margin-top:6px;">';
            echo '<div><div style="font-size:11px;opacity:.7;margin-bottom:4px">'.esc_html__('Original','wc-cfp').'</div>';
            echo '<img src="'.esc_url($orig).'" style="max-width:120px;height:auto;border:1px solid #ddd;border-radius:4px;display:block;margin-bottom:6px" />';
            echo '<a class="button" target="_blank" href="'.esc_url($orig).'">'.esc_html__('Open','wc-cfp').'</a> ';
            echo '<a class="button" download href="'.esc_url($orig).'">'.esc_html__('Download','wc-cfp').'</a></div>';
            echo '</div>';
        }
    }

    /** AJAX endpoint: receives file under $_FILES['file'] */
    public static function ajax_upload(){
        check_ajax_referer('cfp_ajax', 'nonce');
        if (empty($_FILES['file']['tmp_name'])) {
            wp_send_json_error(['message'=>'No file']);
        }
        $f = $_FILES['file'];
        $mime = wp_check_filetype($f['name']);
        $type = $mime['type'];
        if (!in_array($type, ['image/jpeg','image/jpg','image/pjpeg','image/png'])) {
            wp_send_json_error(['message'=>'Invalid type']);
        }
        $max_bytes = (int) apply_filters('cfp/max_upload_mb', 15) * 1024 * 1024;
        if ((int)$f['size'] > $max_bytes) { wp_send_json_error(['message'=>'Too big']); }

        $up = wp_upload_dir();
        $sub = 'wc-cfp';
        $dir = trailingslashit($up['basedir']).$sub;
        $url = trailingslashit($up['baseurl']).$sub;
        if (!is_dir($dir)) { wp_mkdir_p($dir); }

        $ext = ($type==='image/png') ? 'png' : 'jpg';
        $name = 'cfp-'.time().'-'.wp_generate_password(6,false,false).'.'.$ext;
        $path = trailingslashit($dir).$name;
        if (!@move_uploaded_file($f['tmp_name'], $path)) { wp_send_json_error(['message'=>'Move failed']); }

        // Store in WC session for validation fallback
        if (function_exists('WC') && WC()->session) {
            WC()->session->set('cfp_last_url', trailingslashit($url).$name);
        }

        wp_send_json_success(['url'=> trailingslashit($url).$name ]);
    }
}
