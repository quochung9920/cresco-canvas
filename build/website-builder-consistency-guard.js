(function(window,document){
'use strict';
var wp=window.wp;
if(!wp||!wp.apiFetch||typeof wp.apiFetch.use!=='function')return;
if(window.crescoStudioConsistencyGuard&&window.crescoStudioConsistencyGuard.installed)return;
var state=window.crescoStudioConsistencyGuard={version:'2.0.0',installed:true,checksum:'',revision:0,latestSession:null,conflicts:0,superseded:0,lastError:'',auxDirty:{pageSettings:false,globalSettings:false},safePreview:false};
function settings(){return window.crescoWebsiteBuilderSettings||{};}
function pathOf(options){return String(options&&options.path||'');}
function methodOf(options){return String(options&&options.method||'GET').toUpperCase();}
function sessionPath(){return String(settings().sessionPath||'');}
function recoveryKey(){return 'cresco-studio-recovery:'+String(settings().postId||'');}
function isSession(options){var path=pathOf(options),session=sessionPath();return!!session&&path===session;}
function remember(result){if(!result||typeof result!=='object')return result;if(result.checksum)state.checksum=String(result.checksum);if(result.session&&result.session.schema==='cresco-session/v1')state.latestSession=result.session;return result;}
function stringify(value){try{return JSON.stringify(value);}catch(error){return'';}}
function readRecovery(){try{var raw=window.localStorage&&window.localStorage.getItem(recoveryKey());return raw?JSON.parse(raw):null;}catch(error){return null;}}
function notify(){try{window.dispatchEvent(new window.CustomEvent('cresco:studio-consistency-change',{detail:{revision:state.revision,auxDirty:Object.assign({},state.auxDirty)}}));}catch(error){}}
function syncFromRecovery(increment){var recovery=readRecovery();if(!recovery)return;if(recovery.session&&recovery.session.schema==='cresco-session/v1')state.latestSession=recovery.session;if(increment)state.revision++;notify();}
function localChangedSince(data){var recovery=readRecovery();if(!recovery)return false;if(data&&data.session&&recovery.session&&stringify(data.session)!==stringify(recovery.session))return true;if(data&&Object.prototype.hasOwnProperty.call(data,'postTitle')&&typeof recovery.title==='string'&&String(data.postTitle)!==String(recovery.title))return true;return false;}
function markAux(kind,value){if(!Object.prototype.hasOwnProperty.call(state.auxDirty,kind))return;state.auxDirty[kind]=!!value;notify();}
function auxKindForPath(path){var cfg=settings();if(path&&path===String(cfg.pageSettingsPath||''))return'pageSettings';if(path&&path===String(cfg.settingsPath||''))return'globalSettings';return'';}
function sanitizePreviewHtml(value){var template=document.createElement('template');template.innerHTML=String(value==null?'':value);Array.prototype.slice.call(template.content.querySelectorAll('script,style,iframe,object,embed,link,meta,base')).forEach(function(node){node.remove();});Array.prototype.slice.call(template.content.querySelectorAll('*')).forEach(function(node){Array.prototype.slice.call(node.attributes||[]).forEach(function(attribute){var name=String(attribute.name||'').toLowerCase(),raw=String(attribute.value||'').trim();if(/^on/.test(name)||name==='srcdoc'||name==='style'||((name==='href'||name==='src'||name==='xlink:href'||name==='formaction')&&/^(?:javascript:|data:text\/html)/i.test(raw)))node.removeAttribute(attribute.name);});});return template.innerHTML;}
function installSafePreviewCreateElement(){if(!wp.element||typeof wp.element.createElement!=='function')return;var original=wp.element.createElement;if(original.__crescoSafePreview)return;function guarded(){var args=Array.prototype.slice.call(arguments),props=args[1];if(props&&props.dangerouslySetInnerHTML&&Object.prototype.hasOwnProperty.call(props.dangerouslySetInnerHTML,'__html')){var next=Object.assign({},props),danger=Object.assign({},props.dangerouslySetInnerHTML);danger.__html=sanitizePreviewHtml(danger.__html);next.dangerouslySetInnerHTML=danger;args[1]=next;}return original.apply(this,args);}guarded.__crescoSafePreview=true;guarded.__crescoOriginal=original;wp.element.createElement=guarded;state.safePreview=true;window.crescoSanitizeStudioPreviewHtml=sanitizePreviewHtml;window.addEventListener('cresco:studio-ready',function restore(){if(wp.element&&wp.element.createElement===guarded)wp.element.createElement=original;window.removeEventListener('cresco:studio-ready',restore);},{once:false});}
installSafePreviewCreateElement();
wp.apiFetch.use(function(options,next){
 var path=pathOf(options),method=methodOf(options),aux=auxKindForPath(path);
 if(aux&&method!=='GET')return Promise.resolve(next(options)).then(function(result){markAux(aux,false);return result;});
 if(!isSession(options))return next(options);
 if(method==='GET')return Promise.resolve(next(options)).then(remember);
 if(method!=='POST'&&method!=='PUT'&&method!=='PATCH')return next(options);
 var startRevision=state.revision;
 var data=Object.assign({},options&&options.data||{});
 if(state.checksum&&!Object.prototype.hasOwnProperty.call(data,'baseChecksum'))data.baseChecksum=state.checksum;
 var requestOptions=Object.assign({},options,{data:data});
 return Promise.resolve(next(requestOptions)).then(function(result){
  remember(result);
  if(state.revision!==startRevision||localChangedSince(data)){
   state.superseded++;
   var error=new Error('Document changed while save was in flight. Newer edits were kept locally; save again.');
   error.code='cresco_save_superseded';
   error.serverResult=result;
   state.lastError=error.message;
   throw error;
  }
  return result;
 },function(error){
  if(error&&Number(error.status||error.data&&error.data.status)===409)state.conflicts++;
  state.lastError=String(error&&error.message||error||'Session request failed');
  throw error;
 });
});
window.addEventListener('cresco:studio-session-change',function(event){if(!event||!event.detail||!event.detail.session)return;state.revision++;state.latestSession=event.detail.session;notify();});
document.addEventListener('input',function(event){var target=event.target;if(!target||!target.closest)return;if(target.closest('.cc-studio-title')){state.revision++;window.setTimeout(function(){syncFromRecovery(false);},0);return;}var panel=target.closest('.cc-studio-panel');if(!panel)return;var heading=panel.querySelector('.cc-studio-panel-head strong'),name=String(heading&&heading.textContent||'');if(name.indexOf('Page Settings')===0)markAux('pageSettings',true);if(name.indexOf('Global Design')===0)markAux('globalSettings',true);},true);
document.addEventListener('change',function(event){var target=event.target;if(!target||!target.closest)return;var panel=target.closest('.cc-studio-panel');if(!panel)return;var heading=panel.querySelector('.cc-studio-panel-head strong'),name=String(heading&&heading.textContent||'');if(name.indexOf('Page Settings')===0)markAux('pageSettings',true);if(name.indexOf('Global Design')===0)markAux('globalSettings',true);},true);
window.addEventListener('keydown',function(event){if((event.ctrlKey||event.metaKey)&&String(event.key||'').toLowerCase()==='z')window.setTimeout(function(){syncFromRecovery(true);},0);},true);
document.addEventListener('click',function(event){var button=event.target&&event.target.closest?event.target.closest('button'):null;if(!button)return;var label=String(button.getAttribute('title')||button.getAttribute('aria-label')||button.textContent||'').trim().toLowerCase();if(label.indexOf('undo')===0||label.indexOf('redo')===0)window.setTimeout(function(){syncFromRecovery(true);},0);},true);
window.addEventListener('beforeunload',function(event){if(state.auxDirty.pageSettings||state.auxDirty.globalSettings){event.preventDefault();event.returnValue='';}});
})(window,document);
