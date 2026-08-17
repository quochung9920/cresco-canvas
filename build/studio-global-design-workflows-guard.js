(function(wp,window,document){
'use strict';
if(!wp||!wp.apiFetch)return;
var root=document.getElementById('cresco-canvas-standalone-editor');
var studio=window.crescoWebsiteBuilderSettings||{};
if(!root||!studio.postId||!studio.sessionPath)return;

var base=wp.apiFetch;
var nativeJsonParse=window.JSON.parse;
var LOAD_TIMEOUT=12000;
var STALL_TIMEOUT=14000;

function isObject(value){return !!value&&typeof value==='object'&&!Array.isArray(value)}
function looksLikeGlobalSettings(value){
 return isObject(value)&&(
  isObject(value.fluidTokens)||isObject(value.breakpoints)||
  Object.prototype.hasOwnProperty.call(value,'primary')||
  Object.prototype.hasOwnProperty.call(value,'fontFamily')||
  Object.prototype.hasOwnProperty.call(value,'radius')||
  Object.prototype.hasOwnProperty.call(value,'containerMax')||
  Object.prototype.hasOwnProperty.call(value,'contentMax')
 );
}
function customColorMap(value){
 if(isObject(value))return value;
 if(Array.isArray(value)){
  var mapped={};
  value.forEach(function(item,index){
   if(!isObject(item))return;
   var key=String(item.key||item.slug||item.name||('color-'+index)).toLowerCase().replace(/[^a-z0-9_-]+/g,'-').replace(/^-+|-+$/g,'');
   var color=item.value||item.color;
   if(key&&color!=null)mapped[key]=color;
  });
  return mapped;
 }
 return {};
}
function decorateCustomColors(settings){
 if(!looksLikeGlobalSettings(settings))return settings;
 var target=settings,colors=customColorMap(settings.customColors);
 if(colors!==settings.customColors){
  try{target.customColors=colors}catch(error){target=Object.assign({},settings,{customColors:colors})}
  if(!isObject(target.customColors))target=Object.assign({},target,{customColors:colors});
 }
 colors=target.customColors;
 try{
  if(!Object.prototype.hasOwnProperty.call(colors,'length')){
   Object.defineProperty(colors,'length',{configurable:true,enumerable:false,get:function(){return Object.keys(this).length}});
  }
  if(!Object.prototype.hasOwnProperty.call(colors,'slice')){
   Object.defineProperty(colors,'slice',{configurable:true,enumerable:false,writable:false,value:function(start,end){
    var self=this;
    return Object.keys(self).slice(start,end).map(function(key){return{key:key,label:key,value:self[key]}});
   }});
  }
 }catch(error){}
 return target;
}
function decorateParsed(value){
 if(looksLikeGlobalSettings(value))value=decorateCustomColors(value);
 if(isObject(value)&&looksLikeGlobalSettings(value.settings)){
  var nested=decorateCustomColors(value.settings);
  if(nested!==value.settings){
   try{value.settings=nested}catch(error){value=Object.assign({},value,{settings:nested})}
  }
 }
 return value;
}

/*
 * Global Design v2 still has one overview consumer that calls array helpers on
 * customColors while the canonical schema stores a keyed object map. Keep the
 * persistence contract object-shaped and expose non-enumerable compatibility
 * helpers, including when customColors is omitted because the map is empty.
 */
window.JSON.parse=function(text,reviver){return decorateParsed(nativeJsonParse.call(window.JSON,text,reviver))};

function globalDesignDirty(){var save=root.querySelector('.cc-global-design-pro [data-save]');return !!(save&&!save.disabled)}
function withTimeout(promise,label){
 return new Promise(function(resolve,reject){
  var settled=false,timer=window.setTimeout(function(){if(settled)return;settled=true;reject(new Error(label+' timed out. Retry Global Design.'))},LOAD_TIMEOUT);
  Promise.resolve(promise).then(function(value){if(settled)return;settled=true;window.clearTimeout(timer);resolve(value)},function(error){if(settled)return;settled=true;window.clearTimeout(timer);reject(error)});
 });
}
wp.apiFetch=function(options){
 var path=options&&options.path||'',method=String(options&&options.method||'GET').toUpperCase();
 if(path===studio.sessionPath&&method!=='GET'&&globalDesignDirty()){
  var error=new Error('Apply or discard Global Design changes before normalizing the page.');
  error.code='cresco_global_design_unsaved';
  return Promise.reject(error);
 }
 var request=base(options);
 if(method==='GET'&&(path===studio.sessionPath||/\/cresco-canvas\/v1\/settings(?:\?|$)/.test(path))){
  request=withTimeout(request,path===studio.sessionPath?'Global Design session request':'Global Design settings request');
 }
 return Promise.resolve(request).then(function(result){
  if(method==='GET'&&/\/cresco-canvas\/v1\/settings(?:\?|$)/.test(path))return decorateParsed(result);
  return result;
 });
};

function enhanceModal(modal){
 if(!modal||modal.dataset.gdwA11y==='1')return;
 modal.dataset.gdwA11y='1';
 modal.addEventListener('keydown',function(e){
  if(e.key==='Escape'){
   e.preventDefault();
   var close=modal.querySelector('[data-gdw-close]');
   if(close)close.click();
   return;
  }
  if(e.key!=='Tab')return;
  var items=Array.prototype.slice.call(modal.querySelectorAll('button:not([disabled]),[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'));
  if(!items.length)return;
  var first=items[0],last=items[items.length-1];
  if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}
  else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}
 });
}

function panelByTitle(){
 var panels=Array.prototype.slice.call(root.querySelectorAll('.cc-studio-left .cc-studio-panel'));
 return panels.find(function(panel){var strong=panel.querySelector('.cc-studio-panel-head strong');return strong&&String(strong.textContent||'').trim()==='Global Design'})||null;
}
function cleanupGlobalPanel(){
 var panel=panelByTitle();
 if(!panel)return null;
 var hosts=Array.prototype.slice.call(panel.children).filter(function(child){return child.classList&&child.classList.contains('cc-global-design-pro')});
 if(hosts.length>1){
  var keep=hosts[hosts.length-1];
  hosts.forEach(function(host){if(host!==keep)host.remove()});
 }
 panel.querySelectorAll('.cc-studio-ux-library-filters').forEach(function(filters){filters.remove()});
 return panel;
}
function loaderHost(){
 var panel=cleanupGlobalPanel();
 if(!panel)return null;
 var host=panel.querySelector('.cc-global-design-pro');
 if(!host||host.querySelector('.cc-gd-shell'))return null;
 return host.querySelector('.cc-gd-loading')?host:null;
}
function retryGlobalDesign(){
 var panel=panelByTitle(),host=panel&&panel.querySelector('.cc-global-design-pro');
 if(host)host.remove();
 try{window.dispatchEvent(new CustomEvent('cresco:studio-ready',{detail:{postId:studio.postId,reason:'global-design-retry'}}))}catch(error){window.dispatchEvent(new Event('cresco:studio-ready'))}
}
function showLoadError(message){
 var host=loaderHost();
 if(!host)return false;
 host.innerHTML='<div class="cc-gd-error cc-gdw-load-error" role="alert"><strong>Global Design could not load</strong><p></p><button type="button" class="cc-studio-button" data-gdw-retry-design>Retry Global Design</button></div>';
 var text=host.querySelector('p');
 if(text)text.textContent=String(message||'The Design System UI stopped while rendering.');
 var retry=host.querySelector('[data-gdw-retry-design]');
 if(retry)retry.addEventListener('click',retryGlobalDesign);
 return true;
}
function watchLoading(){
 var host=loaderHost();
 if(!host)return;
 var now=Date.now(),started=Number(host.dataset.gdwLoadingSince||0);
 if(!started){host.dataset.gdwLoadingSince=String(now);return}
 if(now-started>=STALL_TIMEOUT)showLoadError('The Design System took too long to initialize. The page session is safe; retry the panel.')
}
function relatedRuntimeError(value){
 var text=String(value&&value.stack||value&&value.message||value||'');
 return /studio-global-design-(?:pro|workflows)|customColors|slice is not a function/i.test(text);
}

var observer=new MutationObserver(function(){
 cleanupGlobalPanel();
 enhanceModal(document.querySelector('.cc-gdw-modal'));
 watchLoading();
});
observer.observe(document.body,{childList:true,subtree:true});
window.setInterval(watchLoading,1000);
window.addEventListener('error',function(event){if(loaderHost()&&relatedRuntimeError(event.error||event.message))showLoadError(event.message||'Global Design stopped while rendering.')});
window.addEventListener('unhandledrejection',function(event){if(loaderHost()&&relatedRuntimeError(event.reason))showLoadError(event.reason&&event.reason.message||'Global Design stopped while rendering.')});

window.CrescoGlobalDesignLoadGuard={
 version:'1.2.0',
 decorateSettings:decorateCustomColors,
 cleanupPanel:cleanupGlobalPanel,
 showLoadError:showLoadError,
 retry:retryGlobalDesign
};
cleanupGlobalPanel();
})(window.wp,window,document);
