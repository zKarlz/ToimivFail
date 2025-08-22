<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Read frames meta for a product.
 * Format: each frame: [ 'label'=>string, 'attachment_id'=>int, 'overlay'=>[x,y,w,h,rotation,ratio] ]
 */
function cfp_get_frames_meta($product_id){
    $frames = get_post_meta($product_id, '_cfp_frames', true);
    if (!is_array($frames)) { $frames = []; }
    $out = [];
    foreach ($frames as $f){
        $ov = isset($f['overlay']) && is_array($f['overlay']) ? $f['overlay'] : [];
        $out[] = [
            'label' => isset($f['label']) ? sanitize_text_field($f['label']) : '',
            'attachment_id' => isset($f['attachment_id']) ? intval($f['attachment_id']) : 0,
            'overlay' => [
                'x' => isset($ov['x']) ? floatval($ov['x']) : null,
                'y' => isset($ov['y']) ? floatval($ov['y']) : null,
                'w' => isset($ov['w']) ? floatval($ov['w']) : null,
                'h' => isset($ov['h']) ? floatval($ov['h']) : null,
                'rotation' => isset($ov['rotation']) ? floatval($ov['rotation']) : 0,
                'ratio' => isset($ov['ratio']) ? floatval($ov['ratio']) : 1,
            ],
        ];
    }
    return $out;
}
