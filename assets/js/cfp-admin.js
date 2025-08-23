(function($){
    function newRow(index){
      return $(
        '<div class="cfp-row">        <div class="cfp-col">          <label>Label</label>          <input type="text" name="cfp_frames['+index+'][label]" value="" class="widefat" />        </div>        <div class="cfp-col">          <label>Frame image</label>          <div class="cfp-media">            <input type="hidden" name="cfp_frames['+index+'][attachment_id]" value="0" class="cfp-attach-id" />            <button class="button cfp-pick">Select image</button>            <div class="cfp-thumb" data-full=""></div>          </div>        </div>        <div class="cfp-col cfp-overlay">          <label>Overlay (x,y,w,h,rotation,ratio)</label>          <div class="cfp-grid">            <input placeholder="x" name="cfp_frames['+index+'][overlay][x]" type="number" step="0.01" />            <input placeholder="y" name="cfp_frames['+index+'][overlay][y]" type="number" step="0.01" />            <input placeholder="w" name="cfp_frames['+index+'][overlay][w]" type="number" step="0.01" />            <input placeholder="h" name="cfp_frames['+index+'][overlay][h]" type="number" step="0.01" />            <input placeholder="rotation" name="cfp_frames['+index+'][overlay][rotation]" type="number" step="0.01" />            <input placeholder="ratio" name="cfp_frames['+index+'][overlay][ratio]" type="number" step="0.0001" />          </div>          <p><button type="button" class="button cfp-set-overlay">Select area</button></p>        </div>        <a class="button-link delete cfp-remove">Remove</a>      </div>'
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
        var thumbUrl = (sel.sizes && sel.sizes.thumbnail ? sel.sizes.thumbnail.url : sel.url);
        var html = '<img src="'+thumbUrl+'" style="max-width:80px;height:auto;border:1px solid #ddd;border-radius:4px;" />';
        $row.find('.cfp-thumb').html(html).attr('data-full', sel.url);
      });
      frame.open();
    });

    $(document).on('click', '.cfp-set-overlay', function(e){
      e.preventDefault();
      var $btn = $(this);
      var $row = $btn.closest('.cfp-row');
      var imgUrl = $row.find('.cfp-thumb').data('full');
      if(!imgUrl){ alert('Select frame image first'); return; }
      var $m = $('<div class="cfp-ov-picker"><div class="cfp-ov-box"><img src="'+imgUrl+'" alt=""/><div class="cfp-ov-select"></div><p class="cfp-ov-actions"><button class="button button-primary cfp-ov-apply">Apply</button> <button class="button cfp-ov-cancel">Cancel</button></p></div></div>');
      $('body').append($m);
      var $img = $m.find('img');
      var $sel = $m.find('.cfp-ov-select').draggable({containment:'parent'}).resizable({containment:'parent'});
      var natW=0, natH=0; var tmp = new Image();
      tmp.onload = function(){ natW = tmp.width; natH = tmp.height; };
      tmp.src = imgUrl;
      $m.on('click', '.cfp-ov-cancel', function(){ $m.remove(); });
      $m.on('click', '.cfp-ov-apply', function(){
        var scale = natW / $img.width();
        var pos = $sel.position();
        var x = pos.left * scale;
        var y = pos.top * scale;
        var w = $sel.width() * scale;
        var h = $sel.height() * scale;
        $row.find('input[name$="[overlay][x]"]').val(x.toFixed(2));
        $row.find('input[name$="[overlay][y]"]').val(y.toFixed(2));
        $row.find('input[name$="[overlay][w]"]').val(w.toFixed(2));
        $row.find('input[name$="[overlay][h]"]').val(h.toFixed(2));
        $row.find('input[name$="[overlay][ratio]"]').val((h/w).toFixed(4));
        $m.remove();
      });
    });
})(jQuery);
