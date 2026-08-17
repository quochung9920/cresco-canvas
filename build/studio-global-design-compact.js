(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var scheduled=false;
function text(node){return node?String(node.textContent||'').trim():''}
function panel(){
 var panels=Array.prototype.slice.call(root.querySelectorAll('.cc-studio-left .cc-studio-panel'));
 return panels.find(function(item){var title=item.querySelector('.cc-studio-panel-head strong');return text(title)==='Global Design'})||null;
}
function compactOverview(host){
 host.querySelectorAll('.cc-gd-swatches,.cc-gd-type-preview,.cc-gd-bars,.cc-gd-contrast.is-compact').forEach(function(node){node.remove()});
}
function compactTypography(host){
 host.querySelectorAll('.cc-gd-font-preview').forEach(function(node){node.remove()});
 host.querySelectorAll('.cc-gd-type-list article').forEach(function(article){
  if(article.dataset.gdCompact==='1')return;
  var head=article.querySelector(':scope > div'),input=article.querySelector('input[data-fluid]');
  if(!head||!input)return;
  var label=text(head.querySelector('small'))||String(input.dataset.fluid||'Type');
  input.remove();
  input.classList.add('cc-gd-compact-input');
  input.setAttribute('aria-label',label+' global size');
  var row=document.createElement('label');
  row.className='cc-gd-control-row cc-gd-control-row--type';
  var copy=document.createElement('span');
  copy.className='cc-gd-control-label';
  var strong=document.createElement('strong');
  strong.textContent=label;
  var small=document.createElement('small');
  small.textContent='Global size';
  copy.appendChild(strong);copy.appendChild(small);
  row.appendChild(copy);row.appendChild(input);
  article.textContent='';
  article.appendChild(row);
  article.dataset.gdCompact='1';
 });
}
function compactShell(host){
 host.classList.add('cc-gd-compact-controls');
 compactOverview(host);
 compactTypography(host);
}
function sync(){
 scheduled=false;
 var current=panel(),host=current&&current.querySelector('.cc-global-design-pro'),app=root.querySelector('.cc-studio-app');
 if(app)app.classList.toggle('cc-gd-compact-active',!!host);
 if(host)compactShell(host);
}
function schedule(){if(scheduled)return;scheduled=true;window.requestAnimationFrame(sync)}
var observer=new MutationObserver(schedule);
observer.observe(root,{childList:true,subtree:true});
window.addEventListener('cresco:studio-ready',schedule);
schedule();
window.CrescoGlobalDesignCompactUI={version:'1.0.0',sync:sync};
})(window,document);
