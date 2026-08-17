(function(wp,window,document){
'use strict';
if(!wp||!wp.apiFetch)return;
var root=document.getElementById('cresco-canvas-standalone-editor');
var studio=window.crescoWebsiteBuilderSettings||{};
if(!root||!studio.postId||!studio.sessionPath)return;
var base=wp.apiFetch;
function globalDesignDirty(){var save=root.querySelector('.cc-global-design-pro [data-save]');return !!(save&&!save.disabled)}
wp.apiFetch=function(options){var path=options&&options.path||'',method=String(options&&options.method||'GET').toUpperCase();if(path===studio.sessionPath&&method!=='GET'&&globalDesignDirty()){var error=new Error('Apply or discard Global Design changes before normalizing the page.');error.code='cresco_global_design_unsaved';return Promise.reject(error)}return base(options)};
function enhanceModal(modal){if(!modal||modal.dataset.gdwA11y==='1')return;modal.dataset.gdwA11y='1';modal.addEventListener('keydown',function(e){if(e.key==='Escape'){e.preventDefault();var close=modal.querySelector('[data-gdw-close]');if(close)close.click();return}if(e.key!=='Tab')return;var items=Array.prototype.slice.call(modal.querySelectorAll('button:not([disabled]),[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'));if(!items.length)return;var first=items[0],last=items[items.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}})}
var observer=new MutationObserver(function(){enhanceModal(document.querySelector('.cc-gdw-modal'))});observer.observe(document.body,{childList:true,subtree:true});
})(window.wp,window,document);
