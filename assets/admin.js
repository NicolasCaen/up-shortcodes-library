jQuery(document).ready(function($){
  function initEditor(selector, settings, overrideMode){
    var $el = $(selector);
    if(!$el.length) return null;
    if(typeof wp === 'undefined' || !wp.codeEditor) return null;
    try{
      var cfg = settings ? JSON.parse(JSON.stringify(settings)) : {};
      cfg.codemirror = cfg.codemirror || {};
      if(overrideMode){ cfg.codemirror.mode = overrideMode; }
      var ed = wp.codeEditor.initialize($el, cfg);
      if(ed && ed.codemirror){
        ed.codemirror.on('change', function(instance){ instance.save(); });
      }
      return ed;
    } catch(e){
      return null;
    }
  }

  var s = window.upSLCodeMirrorSettings || {};
  initEditor('#up_sl_php_code', s.php || { codemirror: { mode: 'text/x-php' } }, 'text/x-php');
  initEditor('#up_sl_js_code', s.js || { codemirror: { mode: 'javascript' } }, 'javascript');
  initEditor('#up_sl_scss_code', s.scss || { codemirror: { mode: 'text/scss' } }, 'text/scss');

  // Aide UX: préfixer automatiquement le nom par "shortcode-" si manquant
  $(document).on('blur', '#up_sl_file_name', function(){
    var val = ($(this).val() || '').trim();
    if(!val) return;
    // Enlever une extension si l'utilisateur en a mis une
    val = val.replace(/\.[a-zA-Z0-9]+$/, '');
    // Remplacer espaces
    val = val.replace(/\s+/g, '-');
    if(val.indexOf('shortcode-') !== 0){
      val = 'shortcode-' + val.replace(/^[-]+/, '');
    }
    $(this).val(val);
  });
});
