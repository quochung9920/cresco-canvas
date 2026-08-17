(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var cfg=window.crescoGlobalDesignProSettings||{},scheduled=false;
var fonts=(Array.isArray(cfg.systemFonts)?cfg.systemFonts:[]).concat(Array.isArray(cfg.fonts)?cfg.fonts:[]);
function text(node){return node?String(node.textContent||'').trim():''}
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]})}
function panel(){
 var panels=Array.prototype.slice.call(root.querySelectorAll('.cc-studio-left .cc-studio-panel'));
 return panels.find(function(item){var title=item.querySelector('.cc-studio-panel-head strong');return text(title)==='Global Design'})||null;
}
function firstFamily(stack){return String(stack||'').split(',')[0].trim().replace(/^['"]|['"]$/g,'')||'Custom font'}
function findFontByStack(stack){var family=firstFamily(stack).toLowerCase();return fonts.find(function(font){return String(font.family||'').toLowerCase()===family})||null}
function googleUrl(family){return 'https://fonts.googleapis.com/css2?family='+encodeURIComponent(String(family||'').trim()).replace(/%20/g,'+')+'&display=swap'}
function ensureFontInDocument(doc,font){
 if(!doc||!font||font.category==='system')return;
 var id='cc-gd-font-'+String(font.family||'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
 if(!id||doc.getElementById(id))return;
 var link=doc.createElement('link');link.id=id;link.rel='stylesheet';link.href=googleUrl(font.family);link.dataset.crescoGlobalFont='1';
 (doc.head||doc.documentElement).appendChild(link);
}
function loadFont(font){
 ensureFontInDocument(document,font);
 root.querySelectorAll('iframe').forEach(function(frame){try{ensureFontInDocument(frame.contentDocument,font)}catch(e){}});
}
function categoryLabel(category){return({system:'System',sans:'Sans',serif:'Serif',display:'Display',mono:'Mono',handwriting:'Handwriting'})[category]||'Other'}
function renderFontResults(picker){
 var query=String(picker.dataset.query||'').toLowerCase(),category=picker.dataset.category||'all';
 var list=fonts.filter(function(font){var matchesCategory=category==='all'||font.category===category;var hay=(font.family+' '+font.category).toLowerCase();return matchesCategory&&(!query||hay.indexOf(query)>=0)});
 var results=picker.querySelector('.cc-gd-font-results'),meta=picker.querySelector('.cc-gd-font-meta');
 if(meta)meta.textContent=list.length+' fonts';
 if(!results)return;
 results.innerHTML=list.length?list.slice(0,120).map(function(font){return '<button type="button" class="cc-gd-font-option" data-gd-font="'+esc(font.family)+'" data-gd-stack="'+esc(font.stack)+'" data-gd-category="'+esc(font.category)+'"><span><strong>'+esc(font.family)+'</strong><small>'+esc(categoryLabel(font.category))+'</small></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>'}).join(''):'<div class="cc-gd-font-empty">No fonts found. Try another search.</div>';
 if(list.length>120)results.insertAdjacentHTML('beforeend','<div class="cc-gd-font-more">Showing first 120 results — narrow your search to find more.</div>');
}
function closeFontPickers(except){root.querySelectorAll('.cc-gd-font-picker.is-open').forEach(function(picker){if(picker!==except){picker.classList.remove('is-open');var b=picker.querySelector('.cc-gd-font-trigger');if(b)b.setAttribute('aria-expanded','false')}})}
function enhanceFontPicker(host){
 var original=host.querySelector('input[data-bind="fontFamily"]');
 if(!original||original.dataset.gdFontPicker==='1'||!fonts.length)return;
 original.dataset.gdFontPicker='1';
 var field=original.closest('.cc-gd-field');if(!field)return;
 var current=findFontByStack(original.value),selected=current?current.family:firstFamily(original.value);
 var picker=document.createElement('div');picker.className='cc-gd-font-picker';picker.dataset.category='all';picker.dataset.query='';
 picker.innerHTML='<button type="button" class="cc-gd-font-trigger" aria-haspopup="listbox" aria-expanded="false"><span><small>Font family</small><strong>'+esc(selected)+'</strong></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button><div class="cc-gd-font-popover"><div class="cc-gd-font-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" placeholder="Search '+fonts.length+' fonts..." aria-label="Search fonts"></div><div class="cc-gd-font-categories"><button type="button" class="is-active" data-gd-font-category="all">All</button><button type="button" data-gd-font-category="sans">Sans</button><button type="button" data-gd-font-category="serif">Serif</button><button type="button" data-gd-font-category="display">Display</button><button type="button" data-gd-font-category="mono">Mono</button><button type="button" data-gd-font-category="handwriting">Script</button></div><div class="cc-gd-font-meta"></div><div class="cc-gd-font-results" role="listbox"></div></div>';
 field.insertBefore(picker,original);
 var details=document.createElement('details');details.className='cc-gd-font-custom';details.innerHTML='<summary>Custom font stack</summary>';
 original.parentNode.insertBefore(details,original);details.appendChild(original);
 renderFontResults(picker);if(current)loadFont(current);
 picker.querySelector('.cc-gd-font-trigger').addEventListener('click',function(){var open=!picker.classList.contains('is-open');closeFontPickers(picker);picker.classList.toggle('is-open',open);this.setAttribute('aria-expanded',open?'true':'false');if(open){var search=picker.querySelector('.cc-gd-font-search input');if(search)setTimeout(function(){search.focus()},0)}});
 picker.querySelector('.cc-gd-font-search input').addEventListener('input',function(){picker.dataset.query=this.value;renderFontResults(picker)});
 picker.querySelector('.cc-gd-font-categories').addEventListener('click',function(event){var button=event.target.closest('[data-gd-font-category]');if(!button)return;picker.dataset.category=button.dataset.gdFontCategory;this.querySelectorAll('button').forEach(function(item){item.classList.toggle('is-active',item===button)});renderFontResults(picker)});
 picker.querySelector('.cc-gd-font-results').addEventListener('click',function(event){var option=event.target.closest('[data-gd-font]');if(!option)return;var font=fonts.find(function(item){return item.family===option.dataset.gdFont})||{family:option.dataset.gdFont,stack:option.dataset.gdStack,category:option.dataset.gdCategory};original.value=font.stack;original.dispatchEvent(new Event('input',{bubbles:true}));original.dispatchEvent(new Event('change',{bubbles:true}));loadFont(font);picker.querySelector('.cc-gd-font-trigger strong').textContent=font.family;picker.classList.remove('is-open');picker.querySelector('.cc-gd-font-trigger').setAttribute('aria-expanded','false')});
 picker.addEventListener('keydown',function(event){if(event.key==='Escape'){picker.classList.remove('is-open');var trigger=picker.querySelector('.cc-gd-font-trigger');trigger.setAttribute('aria-expanded','false');trigger.focus()}});
}
function compactOverview(host){
 host.querySelectorAll('.cc-gd-swatches,.cc-gd-type-preview,.cc-gd-bars,.cc-gd-contrast.is-compact').forEach(function(node){node.remove()});
}
function compactTypography(host){
 host.querySelectorAll('.cc-gd-font-preview').forEach(function(node){node.remove()});
 enhanceFontPicker(host);
 host.querySelectorAll('.cc-gd-type-list article').forEach(function(article){
  if(article.dataset.gdCompact==='1')return;
  var head=article.querySelector(':scope > div'),input=article.querySelector('input[data-fluid]');
  if(!head||!input)return;
  var label=text(head.querySelector('small'))||String(input.dataset.fluid||'Type');
  input.remove();
  input.classList.add('cc-gd-compact-input');
  input.setAttribute('aria-label',label+' global size');
  input.setAttribute('title',input.value||'');
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
function compactColorActions(host){
 host.querySelectorAll('.cc-gd-color-card').forEach(function(card){
  var actions=card.querySelector('.cc-gdw-color-actions'),countNode=card.querySelector(':scope > div:first-child > b');
  if(!actions)return;
  var button=actions.querySelector('button');
  if(!button)return;
  var count=parseInt(text(countNode),10)||0;
  button.textContent=count+' use'+(count===1?'':'s');
  button.setAttribute('aria-label',count?'View '+count+' usages':'No usages in this document');
  button.title=count?'View usage':'No usages in this document';
  button.disabled=count===0;
 });
}
function compactShell(host){
 host.classList.add('cc-gd-compact-controls');
 compactOverview(host);
 compactTypography(host);
 compactColorActions(host);
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
document.addEventListener('click',function(event){if(!event.target.closest('.cc-gd-font-picker'))closeFontPickers(null)});
window.addEventListener('cresco:studio-ready',schedule);
schedule();
window.CrescoGlobalDesignCompactUI={version:'1.2.0',sync:sync,fontCount:fonts.length};
})(window,document);
