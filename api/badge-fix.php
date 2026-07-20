<?php
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
(function() {
  var _orig = typeof badge === 'function' ? badge : function(s) { return s; };
  badge = function(status) {
    if (status === 'no-show') {
      return '<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:9999px;font-family:Poppins,sans-serif;font-size:0.72rem;font-weight:500;white-space:nowrap;background:#EDE9FE;color:#6D28D9">No-show</span>';
    }
    return _orig(status);
  };
})();
