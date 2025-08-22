(function($){
  function newRow(index){
    return $(
      '<div class="cfp-row">        <div class="cfp-col">          <label>Label</label>          <input type="text" name="cfp_frames['+index+'][label]" value="" class="widefat" />        </div>        <div class="cfp-col">          <label>Frame image</label>          <div class="cfp-media">            <input type="hidden" name="cfp_frames['+index+'][attachment_id]" value="0" class="cfp-attach-id" />            <button class="button cfp-pick">Select image</button>            <div class="cfp-thumb"></div>          </div>        </div>        <div class="cfp-col cfp-overlay">          <label>Overlay (x,y,w,h,rotation,ratio)</label>          <div class="cfp-grid">            <input placeholder="x" name="cfp_frames['+index+'][overlay][x]" type="number" step="0.01" />            <input placeholder="y" name="cfp_frames['+index+'][overlay][y]" type="number" step="0.01" />            <input placeholder="w" name="cfp_frames['+index+'][overlay][w]" type="number" step="0.01" />            <input placeholder="h" name="cfp_frames['+index+'][overlay][h]" type="number" step="0.01" />            <input placeholder="rotation" name="cfp_frames['+index+'][overlay][rotation]" type="number" step="0.01" />            <input placeholder="ratio" name="cfp_frames['+index+'][overlay][ratio]" type="number" step="0.0001" />          </div>        </div>        <a class="button-link delete cfp-remove">Remove</a>      </div>'
    ); 
  }

  $(document).on('click', '#cfp-add-row', function(e){
    e.preventDefault();
    var $wrap = $('#cfp-frames-repeater .cfp-rows');
    var idx = $wrap.children('.cfp-row').length;
    $wrap.append(newRow(idx));
  });

  $(document).on('click', '.cfp-remove', function(e){
    e.preventDefault();
    $(this).closest('.cfp-row').remove();
  });

  $(document).on('click', '.cfp-pick', function(e){
    e.preventDefault();
    var $btn = $(this);
    var $row = $btn.closest('.cfp-row');
    var frame = wp.media({title:'Select image', multiple:false, library:{type:'image'}});
    frame.on('select', function(){
      var sel = frame.state().get('selection').first().toJSON();
      $row.find('.cfp-attach-id').val(sel.id);
      var html = '<img src="'+(sel.sizes && sel.sizes.thumbnail ? sel.sizes.thumbnail.url : sel.url)+'" style="max-width:80px;height:auto;border:1px solid #ddd;border-radius:4px;" />';
      $row.find('.cfp-thumb').html(html);
    });
    frame.open();
  });
})(jQuery);
