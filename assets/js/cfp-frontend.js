(function($){
  // Utilities
  function clamp(v, a, b){ return Math.max(a, Math.min(b, v)); }
  function dataURLToBlob(dataUrl){
    var arr = dataUrl.split(','), mime = arr[0].match(/:(.*?);/)[1];
    var bstr = atob(arr[1]), n = bstr.length, u8 = new Uint8Array(n);
    while(n--){ u8[n] = bstr.charCodeAt(n); }
    return new Blob([u8], {type: mime});
  }

  // Session helpers and pre-submit sync
  $(function(){
    function cfpSetHidden(name, val){
      var $f = $('form.cart:first');
      var $in = $f.find('input[name="'+name+'"], #'+name+', .'+name.replace(/_/g,'-'));
      if(!$in.length){ $in = $('<input type="hidden" name="'+name+'"/>').appendTo($f); }
      $in.val(val).trigger('change');
    }
    window.cfpUploadSuccess = function(previewUrl, originalUrl){
      try {
        if (previewUrl){ sessionStorage.setItem('cfp_last_url', previewUrl); cfpSetHidden('cfp_image_url', previewUrl); }
        if (originalUrl){ sessionStorage.setItem('cfp_original_url', originalUrl); cfpSetHidden('cfp_original_url', originalUrl); }
      } catch(e){}
    };
    $(document).on('click', 'form.cart button.single_add_to_cart_button', function(){
      try{
        var p = sessionStorage.getItem('cfp_last_url') || '';
        var o = sessionStorage.getItem('cfp_original_url') || '';
        if (p) cfpSetHidden('cfp_image_url', p);
        if (o) cfpSetHidden('cfp_original_url', o);
        // extra: stash dataURL in case URL not ready
        if (window.cfpLastDataUrl) cfpSetHidden('cfp_image_data', window.cfpLastDataUrl);
      }catch(e){}
    });
  });

  // State
  var state = {
    frameIdx: 0,
    frames: Array.isArray(CFP && CFP.frames) ? CFP.frames : [],
    frameImg: null,
    userImg: null,
    overlayPx: null, // {x,y,w,h,rotation,ratio}
    rot: 0,
    flipH: false,
    flipV: false,
    drag: {on:false, sx:0, sy:0, ox:0, oy:0},
    userPos: {x:0, y:0} // relative within overlay
  };

  // Build UI thumbnails
  function buildFrames(){
    var $c = $('.cfp-frames').empty();
    if (!state.frames || !state.frames.length){
      $c.append('<div class="cfp-frame-thumb"><em>No frames configured</em></div>');
      return;
    }
    state.frames.forEach(function(f, i){
      var $t = $('<div class="cfp-frame-thumb" role="button" tabindex="0" aria-label="'+(f.label||('Frame '+(i+1)))+'"><img alt="frame"/><div class="cfp-label" style="font-size:11px;text-align:center;padding:4px">'+(f.label||('Frame '+(i+1)))+'</div></div>');
      $t.find('img').attr('src', f.thumb || f.url);
      if (i===state.frameIdx) $t.addClass('active');
      $t.on('click keypress', function(){ selectFrame(i); $('.cfp-frame-thumb').removeClass('active'); $t.addClass('active'); });
      $c.append($t);
    });
  }

  function selectFrame(i){
    state.frameIdx = i;
    var f = state.frames[i];
    if (!state.frameImg){ state.frameImg = new Image(); }
    state.frameImg.crossOrigin = 'anonymous';
    state.frameImg.onload = function(){
      var FW = state.frameImg.naturalWidth, FH = state.frameImg.naturalHeight;
      var ov = $.extend({}, f.overlay || {});
      // 1 cm margins default (≈ 38 px @ 96dpi)
      if (!ov.w || !ov.h || typeof ov.x === 'undefined' || typeof ov.y === 'undefined'){
        var margin = Math.round(96 * (1/2.54));
        ov.x = margin; ov.y = margin;
        ov.w = Math.max(16, FW - margin * 2);
        ov.h = Math.max(16, FH - margin * 2);
        ov.rotation = 0;
        ov.ratio = ov.h / ov.w;
      } else {
        ov.ratio = parseFloat(ov.ratio || 1) || 1;
      }
      state.overlayPx = ov;
      $('.cfp-editor-frame').attr('src', f.url);
      layoutOverlay();
      // set label
      $('#cfp_frame_label').val(f.label || ('Frame ' + (i + 1)));
    };
    state.frameImg.src = f.url;
  }

  function resetUserTransform(){
    state.rot = 0; state.flipH = false; state.flipV = false;
    state.userPos = {x:0, y:0};
    var $u = $('.cfp-editor-user');
    var $ov = $('.cfp-editor-overlay');
    var ow = $ov.width(), oh = $ov.height();
    $u.css({transform:'', left:0, top:0, width:ow+'px', height:oh+'px'});
  }

  function layoutOverlay(){
    var $frame = $('.cfp-editor-frame');
    var $ov = $('.cfp-editor-overlay');
    if (!$frame.length || !$ov.length || !state.frameImg || !state.overlayPx){
      return;
    }
    $frame.css({left: '50%', top: '50%', transform: 'translate(-50%,-50%)'});
    setTimeout(function(){
      var w = $frame[0].clientWidth, h = $frame[0].clientHeight;
      var sx = w / state.frameImg.naturalWidth;
      var sy = h / state.frameImg.naturalHeight;
      var dx = state.overlayPx.x * sx, dy = state.overlayPx.y * sy;
      var dw = state.overlayPx.w * sx, dh = state.overlayPx.h * sy;
      $ov.css({width: dw + 'px', height: dh + 'px', left: '50%', top: '50%', transform: 'translate(' + (dx - w / 2) + 'px,' + (dy - h / 2) + 'px)'});
      if(!$ov.find('.cfp-resize-handle').length){
        ['nw','ne','sw','se'].forEach(function(p){ $ov.append('<div class="cfp-resize-handle cfp-rh-'+p+'"></div>'); });
        bindResizing();
      }
      resetUserTransform();
    }, 50);
  }

  // Dragging inside overlay
  function bindDragging(){
    var $u = $('.cfp-editor-user'), $ov = $('.cfp-editor-overlay');
    $u.on('mousedown touchstart', function(e){
      e.preventDefault();
      var pt = e.touches && e.touches[0] ? e.touches[0] : e;
      state.drag.on = true;
      state.drag.sx = pt.clientX; state.drag.sy = pt.clientY;
      state.drag.ox = state.userPos.x; state.drag.oy = state.userPos.y;
      $u.css('cursor','grabbing');
    });
    $(document).on('mousemove touchmove', function(e){
      if (!state.drag.on) return;
      var pt = e.touches && e.touches[0] ? e.touches[0] : e;
      var dx = pt.clientX - state.drag.sx;
      var dy = pt.clientY - state.drag.sy;
      state.userPos.x = state.drag.ox + dx;
      state.userPos.y = state.drag.oy + dy;
      $u.css({left: state.userPos.x+'px', top: state.userPos.y+'px'});
    });
    $(document).on('mouseup touchend touchcancel', function(){
      if (!state.drag.on) return;
      state.drag.on = false;
      $u.css('cursor','grab');
    });
  }

  function bindResizing(){
    var $ov = $('.cfp-editor-overlay');
    var $u = $('.cfp-editor-user');
    var resize = {on:false, cx:0, cy:0, dist:0, w:0, h:0};
    $ov.on('mousedown touchstart', '.cfp-resize-handle', function(e){
      e.preventDefault(); e.stopPropagation();
      var pt = e.touches && e.touches[0] ? e.touches[0] : e;
      var rect = $u[0].getBoundingClientRect();
      resize.on = true;
      resize.w = rect.width; resize.h = rect.height;
      resize.cx = rect.left + rect.width/2;
      resize.cy = rect.top + rect.height/2;
      resize.dist = Math.hypot(pt.clientX - resize.cx, pt.clientY - resize.cy);
    });
    $(document).on('mousemove touchmove', function(e){
      if(!resize.on) return;
      var pt = e.touches && e.touches[0] ? e.touches[0] : e;
      var dist = Math.hypot(pt.clientX - resize.cx, pt.clientY - resize.cy);
      var scale = dist / resize.dist;
      var newW = resize.w * scale;
      var newH = newW * state.overlayPx.ratio;
      var ovRect = $ov[0].getBoundingClientRect();
      var centerX = resize.cx - ovRect.left;
      var centerY = resize.cy - ovRect.top;
      state.userPos.x = centerX - newW/2;
      state.userPos.y = centerY - newH/2;
      $u.css({width:newW+'px', height:newH+'px', left:state.userPos.x+'px', top:state.userPos.y+'px'});
    });
    $(document).on('mouseup touchend touchcancel', function(){
      if(resize.on) resize.on = false;
    });
  }

  // Modal controls
  function openModal(){
    $('#cfp-modal-bg, #cfp-modal').show();
    $('body').addClass('modal-open');
    selectFrame(state.frameIdx || 0);
    layoutOverlay();
  }
  function closeModal(){
    $('#cfp-modal-bg, #cfp-modal').hide();
    $('body').removeClass('modal-open');
  }

  // Compose mockup to data URL + upload
  function compose(maxPx){
    if (!state.frameImg || !state.userImg || !state.overlayPx) return null;
    var FW = state.frameImg.naturalWidth, FH = state.frameImg.naturalHeight;
    var ov = state.overlayPx;
    // canvas at frame size
    var can = document.createElement('canvas');
    can.width = FW; can.height = FH;
    var ctx = can.getContext('2d');
    ctx.clearRect(0,0,FW,FH);
    // draw frame bottom
    ctx.drawImage(state.frameImg, 0, 0, FW, FH);
    // draw user clipped to overlay rect
    ctx.save();
    ctx.beginPath();
    ctx.rect(ov.x, ov.y, ov.w, ov.h);
    ctx.clip();

    // Compute user draw transform:
    // Determine displayed overlay scale: user image base scale so that shorter side fits overlay
    var uw = state.userImg.naturalWidth, uh = state.userImg.naturalHeight;
    var $u = $('.cfp-editor-user');
    var dispW = $u.width(), dispH = $u.height();
    var scaleX = dispW/uw, scaleY = dispH/uh;
    ctx.translate(ov.x + state.userPos.x + dispW/2, ov.y + state.userPos.y + dispH/2);
    var rad = (state.rot || 0) * Math.PI/180;
    ctx.rotate(rad);
    ctx.scale(state.flipH ? -1 : 1, state.flipV ? -1 : 1);
    ctx.drawImage(state.userImg, -uw*scaleX/2, -uh*scaleY/2, uw*scaleX, uh*scaleY);
    ctx.restore();

    // draw frame top (optional, if you had a cutout; here frame covers edges already)
    // export
    window.cfpLastDataUrl = can.toDataURL('image/jpeg', (CFP && CFP.jpegQ) ? CFP.jpegQ : 0.9);
    return {canvas: can, dataUrl: window.cfpLastDataUrl};
  }

  function composeOriginal(){
    if (!state.userImg || !state.overlayPx) return null;
    var ov = state.overlayPx;
    var can = document.createElement('canvas');
    can.width = ov.w; can.height = ov.h;
    var ctx = can.getContext('2d');
    ctx.clearRect(0, 0, ov.w, ov.h);

    var uw = state.userImg.naturalWidth, uh = state.userImg.naturalHeight;
    var $u = $('.cfp-editor-user');
    var dispW = $u.width(), dispH = $u.height();
    var scaleX = dispW/uw, scaleY = dispH/uh;
    ctx.save();
    ctx.translate(state.userPos.x + dispW/2, state.userPos.y + dispH/2);
    var rad = (state.rot || 0) * Math.PI/180;
    ctx.rotate(rad);
    ctx.scale(state.flipH ? -1 : 1, state.flipV ? -1 : 1);
    ctx.drawImage(state.userImg, -uw*scaleX/2, -uh*scaleY/2, uw*scaleX, uh*scaleY);
    ctx.restore();

    return {canvas: can, dataUrl: can.toDataURL('image/jpeg', (CFP && CFP.jpegQ) ? CFP.jpegQ : 0.9)};
  }

  // Upload helper
  function uploadBlob(blob, cb){
    var fd = new FormData();
    fd.append('action','cfp_upload_mockup');
    fd.append('nonce', (CFP && CFP.nonce)?CFP.nonce:'');
    fd.append('product_id', (CFP && CFP.productId)?CFP.productId:'');
    fd.append('file', blob, 'mockup.jpg');
    $.ajax({url:(CFP && CFP.ajaxUrl)?CFP.ajaxUrl:'/wp-admin/admin-ajax.php', method:'POST', data:fd, processData:false, contentType:false})
      .done(function(res){
        try{ if(typeof res==='string'){ res = JSON.parse(res); } }catch(e){}
        var url = (res && res.data && res.data.url) ? res.data.url : (res && res.url ? res.url : null);
        if (url){ cb && cb(null, url); } else { cb && cb('bad response'); }
      })
      .fail(function(){ cb && cb('ajax fail'); });
  }

  // Bind frontend UI behaviors
  $(function(){
    // Build frame thumbs
    buildFrames();

    var $drop = $('.cfp-drop'), $file = $('.cfp-file'), $meta = $('.cfp-file-meta');
    var $bg = $('#cfp-modal-bg'), $modal = $('#cfp-modal');
    var $mApply = $('.cfp-m-apply'), $mCancel = $('.cfp-m-cancel');
    var $rotL = $('.cfp-m-rot-left'), $rotR = $('.cfp-m-rot-right'), $fH = $('.cfp-m-flip-h'), $fV = $('.cfp-m-flip-v');
    var $user = $('.cfp-editor-user');

    function loadUser(file){
      if (!file) return;
      if (!/image\/(jpeg|jpg|png)$/i.test(file.type)){ alert(CFP.i18n.invalid); return; }
      if (file.size > ((CFP.maxMB||15)*1024*1024)){ alert(CFP.i18n.tooBig); return; }
      $meta.text(file.name + ' (' + Math.round(file.size/1024) + ' KB)');
      var reader = new FileReader();
      reader.onload = function(e){
        state.userImg = new Image();
        state.userImg.onload = function(){ openModal(); resetUserTransform(); bindDragging(); $user.attr('src', e.target.result); };
        state.userImg.src = e.target.result;
      };
      reader.readAsDataURL(file);
    }

    $file.on('change', function(e){ var f = e.target.files && e.target.files[0]; loadUser(f); });
    $drop.on('dragover', function(e){ e.preventDefault(); e.originalEvent.dataTransfer.dropEffect='copy'; $drop.addClass('dragover'); });
    $drop.on('dragleave', function(){ $drop.removeClass('dragover'); });
    $drop.on('drop', function(e){ e.preventDefault(); $drop.removeClass('dragover'); var f = e.originalEvent.dataTransfer.files && e.originalEvent.dataTransfer.files[0]; loadUser(f); });

    $('.cfp-close, .cfp-m-cancel').on('click', function(){ closeModal(); });

    // Controls
    $rotL.on('click', function(){ state.rot -= 90; });
    $rotR.on('click', function(){ state.rot += 90; });
    $fH.on('click', function(){ state.flipH = !state.flipH; });
    $fV.on('click', function(){ state.flipV = !state.flipV; });

    // Apply: compose + upload
    $mApply.on('click', function(){
      var c = compose(1500);
      var o = composeOriginal();
      if (!c || !c.dataUrl){ alert('Compose failed'); return; }
      if (!o || !o.dataUrl){ alert('Compose failed'); return; }
      try { window.cfpLastDataUrl = c.dataUrl; $('input[name="cfp_image_data"]').val(c.dataUrl); } catch(e){}
      var previewBlob = dataURLToBlob(c.dataUrl);
      var originalBlob = dataURLToBlob(o.dataUrl);
      uploadBlob(previewBlob, function(err, previewUrl){
        if (!err && previewUrl){
          try{
            var $img = $('.woocommerce-product-gallery .wp-post-image, .woocommerce-product-gallery__image img, img.woocommerce-main-image').first();
            if ($img.length){ $img.attr('src', previewUrl).attr('srcset',''); }
          }catch(e){}
          try{ var $btn=$('form.cart').find('button.single_add_to_cart_button'); $btn.prop('disabled', false).removeClass('disabled'); }catch(e){}
          uploadBlob(originalBlob, function(err2, originalUrl){
            try { window.cfpUploadSuccess(previewUrl, (!err2 && originalUrl) ? originalUrl : null); }catch(e){}
          });
        }
      });
      closeModal();
    });
  });
})(jQuery);
