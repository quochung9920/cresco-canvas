(function(wp,window,document){
'use strict';
if(!wp||!wp.apiFetch)return;
var root=document.getElementById('cresco-canvas-standalone-editor');
var cfg=window.crescoGlobalDesignProSettings||{};
if(!root||!cfg.fontLibraryPath)return;
var apiFetch=wp.apiFetch;
var RECENT_KEY='cresco-global-design-recent-fonts-v1';
var FAVORITES_KEY='cresco-global-design-favorite-fonts-v1';
var SELECTED_LINK_ID='cresco-gd-selected-font-css';
var PREVIEW_LINK_ID='cresco-gd-font-preview-css';
var CATEGORIES=[
 ['all','All'],['recent','Recent'],['favorites','Favorites'],['sans-serif','Sans'],['serif','Serif'],['display','Display'],['handwriting','Handwriting'],['monospace','Mono'],['system','System']
];
var state={fonts:[],loaded:false,loading:false,error:'',promise:null,query:'',category:'all',open:false,host:null,input:null,selectedFamily:'',catalogMeta:null,scheduled:false,previewTimer:null};

function arr(v){return Array.isArray(v)?v:[]}
function text(v){return String(v==null?'':v)}
function esc(v){return text(v).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]})}
function readList(key){try{var value=JSON.parse(window.localStorage.getItem(key)||'[]');return Array.isArray(value)?value.filter(function(x){return typeof x==='string'}).slice(0,30):[]}catch(e){return[]}}
function writeList(key,value){try{window.localStorage.setItem(key,JSON.stringify(value.slice(0,30)))}catch(e){}}
function firstFamily(stack){var first=text(stack).split(',')[0].trim();return first.replace(/^["']|["']$/g,'')}
function normalize(value){return text(value).toLowerCase().trim()}
function titleCategory(value){var map={'sans-serif':'Sans Serif','serif':'Serif','display':'Display','handwriting':'Handwriting','monospace':'Monospace','system':'System'};return map[value]||'Sans Serif'}
function genericFor(category){return category==='serif'?'serif':category==='monospace'?'monospace':'sans-serif'}
function stackFor(font){if(font.stack)return text(font.stack);return '"'+text(font.family).replace(/"/g,'')+'", '+genericFor(font.category)}
function encodeFamily(name){return encodeURIComponent(text(name)).replace(/%20/g,'+')}
function selectedCssUrl(font){
 if(!font||font.source!=='google')return'';
 var spec=encodeFamily(font.family),min=Number(font.weightMin),max=Number(font.weightMax),weights=arr(font.weights).map(Number).filter(function(x){return isFinite(x)&&x>=100&&x<=900});
 weights=weights.filter(function(x,i,a){return a.indexOf(x)===i}).sort(function(a,b){return a-b});
 if(font.variable&&isFinite(min)&&isFinite(max)&&min<max)spec+=':wght@'+Math.round(min)+'..'+Math.round(max);
 else if(weights.length)spec+=':wght@'+weights.join(';');
 return 'https://fonts.googleapis.com/css2?family='+spec+'&display=swap';
}
function previewCssUrl(fonts){
 var names=arr(fonts).filter(function(font){return font&&font.source==='google'}).map(function(font){return text(font.family)}).filter(Boolean).filter(function(name,i,a){return a.indexOf(name)===i}).sort().slice(0,12);
 if(!names.length)return'';
 return 'https://fonts.googleapis.com/css2?'+names.map(function(name){return'family='+encodeFamily(name)}).join('&')+'&display=swap';
}
function setLink(doc,id,href){
 if(!doc||!doc.head)return;
 var old=doc.getElementById(id);
 if(!href){if(old)old.remove();return}
 if(old&&old.getAttribute('href')===href)return;
 var link=old||doc.createElement('link');link.id=id;link.rel='stylesheet';link.href=href;if(!old)doc.head.appendChild(link);
}
function canonicalDocument(){var frame=root.querySelector('.cc-studio-canonical-preview');try{return frame&&frame.contentDocument||null}catch(e){return null}}
function installSelectedFont(font){
 var href=selectedCssUrl(font);setLink(document,SELECTED_LINK_ID,href);setLink(canonicalDocument(),SELECTED_LINK_ID,href);
 var frame=root.querySelector('.cc-studio-canonical-preview');
 if(frame&&!frame.dataset.gdFontBound){frame.dataset.gdFontBound='1';frame.addEventListener('load',function(){setLink(canonicalDocument(),SELECTED_LINK_ID,selectedCssUrl(findFont(state.selectedFamily)))})}
}
function schedulePreviewFonts(fonts){clearTimeout(state.previewTimer);state.previewTimer=setTimeout(function(){setLink(document,PREVIEW_LINK_ID,previewCssUrl(fonts))},80)}
function findFont(family){var key=normalize(family);return state.fonts.find(function(font){return normalize(font.family)===key})||null}
function remember(font){if(!font)return;var recent=readList(RECENT_KEY).filter(function(name){return normalize(name)!==normalize(font.family)});recent.unshift(font.family);writeList(RECENT_KEY,recent)}
function toggleFavorite(font){if(!font)return;var list=readList(FAVORITES_KEY),key=normalize(font.family),has=list.some(function(name){return normalize(name)===key});list=has?list.filter(function(name){return normalize(name)!==key}):[font.family].concat(list);writeList(FAVORITES_KEY,list);renderPopup()}
function isFavorite(font){var key=normalize(font.family);return readList(FAVORITES_KEY).some(function(name){return normalize(name)===key})}

function loadCatalog(){
 if(state.loaded)return Promise.resolve(state.fonts);
 if(state.promise)return state.promise;
 state.loading=true;state.error='';renderPopup();
 state.promise=apiFetch({path:cfg.fontLibraryPath}).then(function(result){
  state.fonts=arr(result&&result.fonts).filter(function(font){return font&&font.family&&font.stack&&font.source});
  state.catalogMeta=result||{};state.loaded=true;state.loading=false;state.error='';
  syncCurrentFont();renderPopup();return state.fonts;
 },function(error){state.loading=false;state.error=text(error&&error.message||'Could not load font library.');renderPopup();return[]}).finally(function(){state.promise=null});
 return state.promise;
}
function ranking(font){var recent=readList(RECENT_KEY),favorites=readList(FAVORITES_KEY),name=normalize(font.family),fav=favorites.findIndex(function(x){return normalize(x)===name}),rec=recent.findIndex(function(x){return normalize(x)===name});return[fav<0?1:0,fav<0?999:fav,rec<0?1:0,rec<0?999:rec,Number(font.popularity)||999999,name]}
function compareRank(a,b){var x=ranking(a),y=ranking(b);for(var i=0;i<x.length;i++){if(x[i]<y[i])return-1;if(x[i]>y[i])return 1}return 0}
function filteredFonts(){
 var q=normalize(state.query),recent=readList(RECENT_KEY).map(normalize),favorites=readList(FAVORITES_KEY).map(normalize),category=state.category;
 var fonts=state.fonts.filter(function(font){
  var family=normalize(font.family),meta=family+' '+normalize(font.category)+' '+normalize(font.source);
  if(q&&meta.indexOf(q)<0)return false;
  if(category==='recent')return recent.indexOf(family)>=0;
  if(category==='favorites')return favorites.indexOf(family)>=0;
  if(category==='system')return font.source==='system';
  if(category!=='all')return font.category===category;
  return true;
 });
 if(category==='all'&&!q)fonts.sort(compareRank);else fonts.sort(function(a,b){return text(a.family).localeCompare(text(b.family))});
 return fonts;
}
function resultRow(font,index){
 var selected=normalize(font.family)===normalize(state.selectedFamily),favorite=isFavorite(font),meta=(font.source==='system'?'System':titleCategory(font.category))+(font.variable?' · Variable':'');
 return '<div class="cc-gd-font-option'+(selected?' is-selected':'')+'" role="option" aria-selected="'+(selected?'true':'false')+'">'+
  '<button type="button" class="cc-gd-font-option-main" data-gd-font-index="'+index+'" style="font-family:'+esc(stackFor(font))+'">'+
   '<span><strong>'+esc(font.family)+'</strong><small>'+esc(meta)+'</small></span>'+(selected?'<i class="dashicons dashicons-yes-alt" aria-hidden="true"></i>':'')+'</button>'+
  '<button type="button" class="cc-gd-font-favorite'+(favorite?' is-active':'')+'" data-gd-font-favorite="'+index+'" aria-label="'+(favorite?'Remove from favorites':'Add to favorites')+'" title="'+(favorite?'Remove favorite':'Add favorite')+'"><span class="dashicons dashicons-star-'+(favorite?'filled':'empty')+'" aria-hidden="true"></span></button></div>';
}
function renderPopup(){
 var picker=state.host;if(!picker)return;var popup=picker.querySelector('.cc-gd-font-popup');if(!popup)return;
 var body=popup.querySelector('.cc-gd-font-results'),status=popup.querySelector('.cc-gd-font-status');if(!body||!status)return;
 if(state.loading){body.innerHTML='<div class="cc-gd-font-message"><span class="spinner is-active"></span><strong>Loading font library…</strong></div>';status.textContent='Loading the complete font catalog…';return}
 if(state.error){body.innerHTML='<div class="cc-gd-font-message is-error"><strong>Font library unavailable</strong><small>'+esc(state.error)+'</small><button type="button" data-gd-font-retry>Retry</button></div>';status.textContent='Your current font stack remains available.';bindPopup();return}
 var fonts=filteredFonts(),shown=fonts.slice(0,120);body.innerHTML=shown.length?shown.map(resultRow).join(''):'<div class="cc-gd-font-message"><strong>No fonts found</strong><small>Try another search or category.</small></div>';
 var total=Number(state.catalogMeta&&state.catalogMeta.totalCount)||state.fonts.length,google=Number(state.catalogMeta&&state.catalogMeta.googleCount)||state.fonts.filter(function(f){return f.source==='google'}).length;
 status.textContent=(state.query||state.category!=='all'?fonts.length+' matching fonts · ':'')+google.toLocaleString()+' Google Fonts · '+total.toLocaleString()+' total'+(fonts.length>shown.length?' · showing first 120':'');
 schedulePreviewFonts(shown);bindPopup();
}
function bindPopup(){
 if(!state.host)return;var popup=state.host.querySelector('.cc-gd-font-popup');if(!popup)return;
 popup.querySelectorAll('[data-gd-font-index]').forEach(function(button){button.onclick=function(){var font=filteredFonts()[Number(this.dataset.gdFontIndex)];chooseFont(font)}});
 popup.querySelectorAll('[data-gd-font-favorite]').forEach(function(button){button.onclick=function(event){event.stopPropagation();var font=filteredFonts()[Number(this.dataset.gdFontFavorite)];toggleFavorite(font)}});
 var retry=popup.querySelector('[data-gd-font-retry]');if(retry)retry.onclick=function(){state.loaded=false;state.error='';loadCatalog()};
 var options=Array.prototype.slice.call(popup.querySelectorAll('[data-gd-font-index]'));
 options.forEach(function(button,index){button.onkeydown=function(event){if(event.key==='ArrowDown'){event.preventDefault();(options[index+1]||options[0]||button).focus()}else if(event.key==='ArrowUp'){event.preventDefault();(options[index-1]||options[options.length-1]||button).focus()}else if(event.key==='Escape'){event.preventDefault();closePicker()}}});
}
function chooseFont(font){
 if(!font||!state.input)return;var stack=stackFor(font);state.selectedFamily=font.family;state.input.value=stack;state.input.dispatchEvent(new Event('input',{bubbles:true}));remember(font);installSelectedFont(font);syncTrigger();closePicker();
}
function currentSource(){var font=findFont(state.selectedFamily);return font?(font.source==='google'?'Google Fonts':font.source==='system'?'System font':titleCategory(font.category)):'Custom stack'}
function syncTrigger(){if(!state.host)return;var family=state.host.querySelector('[data-gd-font-current]'),meta=state.host.querySelector('[data-gd-font-current-meta]');if(family)family.textContent=state.selectedFamily||'Choose a font';if(meta)meta.textContent=currentSource()}
function syncCurrentFont(){if(!state.input)return;state.selectedFamily=firstFamily(state.input.value);var font=findFont(state.selectedFamily);installSelectedFont(font);syncTrigger()}
function openPicker(){if(!state.host)return;state.open=true;state.host.classList.add('is-open');var trigger=state.host.querySelector('[data-gd-font-trigger]');if(trigger)trigger.setAttribute('aria-expanded','true');var popup=state.host.querySelector('.cc-gd-font-popup');if(popup)popup.hidden=false;loadCatalog().then(function(){renderPopup()});var search=state.host.querySelector('[data-gd-font-search]');if(search)setTimeout(function(){search.focus()},0)}
function closePicker(){if(!state.host)return;state.open=false;state.host.classList.remove('is-open');var trigger=state.host.querySelector('[data-gd-font-trigger]');if(trigger){trigger.setAttribute('aria-expanded','false');trigger.focus()}var popup=state.host.querySelector('.cc-gd-font-popup');if(popup)popup.hidden=true}

function makePicker(input){
 var field=input.closest('.cc-gd-field');if(!field||field.dataset.gdFontEnhanced==='1')return;field.dataset.gdFontEnhanced='1';
 var section=field.closest('.cc-gd-section');if(!section)return;
 var picker=document.createElement('div');picker.className='cc-gd-font-picker';picker.innerHTML=
  '<span class="cc-gd-font-picker-label">Family</span><button type="button" class="cc-gd-font-trigger" data-gd-font-trigger aria-haspopup="listbox" aria-expanded="false"><span><strong data-gd-font-current>Choose a font</strong><small data-gd-font-current-meta>Font family</small></span><i class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></i></button>'+
  '<div class="cc-gd-font-popup" hidden><div class="cc-gd-font-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" data-gd-font-search autocomplete="off" placeholder="Search fonts…" aria-label="Search font families"></div><div class="cc-gd-font-categories" role="tablist">'+CATEGORIES.map(function(cat){return'<button type="button" data-gd-font-category="'+cat[0]+'" class="'+(state.category===cat[0]?'is-active':'')+'">'+cat[1]+'</button>'}).join('')+'</div><div class="cc-gd-font-results" role="listbox" aria-label="Font families"></div><div class="cc-gd-font-status" aria-live="polite"></div></div>';
 field.parentNode.insertBefore(picker,field);var details=document.createElement('details');details.className='cc-gd-font-custom';var summary=document.createElement('summary');summary.textContent='Custom font stack';field.parentNode.insertBefore(details,field);details.appendChild(summary);details.appendChild(field);var label=field.querySelector(':scope > span');if(label)label.textContent='CSS font-family';
 state.host=picker;state.input=input;state.selectedFamily=firstFamily(input.value);syncTrigger();
 picker.querySelector('[data-gd-font-trigger]').onclick=function(){state.open?closePicker():openPicker()};
 var search=picker.querySelector('[data-gd-font-search]');search.oninput=function(){state.query=this.value;renderPopup()};search.onkeydown=function(event){if(event.key==='Escape'){event.preventDefault();closePicker()}else if(event.key==='ArrowDown'){event.preventDefault();var first=picker.querySelector('[data-gd-font-index]');if(first)first.focus()}};
 picker.querySelectorAll('[data-gd-font-category]').forEach(function(button){button.onclick=function(){state.category=this.dataset.gdFontCategory;picker.querySelectorAll('[data-gd-font-category]').forEach(function(x){x.classList.toggle('is-active',x===button)});renderPopup()}});
 input.addEventListener('input',function(){state.selectedFamily=firstFamily(input.value);syncTrigger();if(state.loaded)installSelectedFont(findFont(state.selectedFamily))});
 loadCatalog();
}
function enhance(){
 state.scheduled=false;var input=root.querySelector('.cc-global-design-pro input[data-bind="fontFamily"]');if(!input){state.host=null;state.input=null;return}if(input.dataset.gdFontPicker==='1')return;input.dataset.gdFontPicker='1';makePicker(input);
}
function schedule(){if(state.scheduled)return;state.scheduled=true;window.requestAnimationFrame(enhance)}
document.addEventListener('click',function(event){if(state.open&&state.host&&!state.host.contains(event.target))closePicker()});
document.addEventListener('keydown',function(event){if(state.open&&event.key==='Escape')closePicker()});
var observer=new MutationObserver(schedule);observer.observe(root,{childList:true,subtree:true});
window.addEventListener('cresco:studio-ready',schedule);schedule();
window.CrescoGlobalDesignFontLibrary={version:'1.0.0',reload:function(){state.loaded=false;state.fonts=[];state.error='';return loadCatalog()},sync:schedule};
})(window.wp,window,document);
