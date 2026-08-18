(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var STYLE_ID='cresco-studio-typography-popup-style',OVERLAY_ID='cresco-studio-typography-popup',scheduled=false;
var stats=window.crescoStudioTypographyPopup={version:'1.1.0',mode:'retired-use-native-accordion',open:false,cleanups:0,lastRun:0};
function all(selector,scope){return Array.prototype.slice.call((scope||root).querySelectorAll(selector));}
function cleanup(){
 scheduled=false;
 stats.lastRun=Date.now();
 var overlay=document.getElementById(OVERLAY_ID);
 if(overlay)overlay.remove();
 var style=document.getElementById(STYLE_ID);
 if(style)style.remove();
 all('[data-cresco-typography-popup-hidden="1"]',root).forEach(function(field){
  field.style.removeProperty('display');
  field.removeAttribute('aria-hidden');
  delete field.dataset.crescoTypographyPopupHidden;
 });
 all('.cc-studio-accordion-heading[data-cresco-group="typography"]',root).forEach(function(header){
  delete header.dataset.crescoTypographyPopup;
  delete header.dataset.crescoTypographyPopupBound;
  if(header.getAttribute('aria-haspopup')==='dialog')header.removeAttribute('aria-haspopup');
  var chevron=header.querySelector('.cc-studio-accordion-heading__chevron, .dashicons-edit');
  if(chevron&&chevron.classList.contains('dashicons-edit'))chevron.className='dashicons dashicons-arrow-down-alt2 cc-studio-accordion-heading__chevron';
 });
 stats.open=false;
 stats.cleanups++;
}
function schedule(){
 if(scheduled)return;
 scheduled=true;
 window.requestAnimationFrame(function(){window.setTimeout(cleanup,0);});
}
window.addEventListener('cresco:studio-ready',schedule);
window.addEventListener('cresco:studio-session-change',schedule);
cleanup();
})(window,document);
