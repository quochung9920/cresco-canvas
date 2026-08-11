(function(window){
'use strict';
var wp=window.wp;
if(!wp||!wp.apiFetch||typeof wp.apiFetch.use!=='function')return;
if(window.crescoStudioConsistencyGuard&&window.crescoStudioConsistencyGuard.installed)return;
var state=window.crescoStudioConsistencyGuard={version:'1.0.0',installed:true,checksum:'',revision:0,latestSession:null,conflicts:0,superseded:0,lastError:''};
function settings(){return window.crescoWebsiteBuilderSettings||{};}
function pathOf(options){return String(options&&options.path||'');}
function methodOf(options){return String(options&&options.method||'GET').toUpperCase();}
function sessionPath(){return String(settings().sessionPath||'');}
function isSession(options){var path=pathOf(options),session=sessionPath();return !!session&&path===session;}
function remember(result){if(!result||typeof result!=='object')return result;if(result.checksum)state.checksum=String(result.checksum);if(result.session&&result.session.schema==='cresco-session/v1')state.latestSession=result.session;return result;}
wp.apiFetch.use(function(options,next){
 if(!isSession(options))return next(options);
 var method=methodOf(options);
 if(method==='GET')return Promise.resolve(next(options)).then(remember);
 if(method!=='POST'&&method!=='PUT'&&method!=='PATCH')return next(options);
 var startRevision=state.revision;
 var data=Object.assign({},options&&options.data||{});
 if(state.checksum&&!Object.prototype.hasOwnProperty.call(data,'baseChecksum'))data.baseChecksum=state.checksum;
 var requestOptions=Object.assign({},options,{data:data});
 return Promise.resolve(next(requestOptions)).then(function(result){
  remember(result);
  if(state.revision!==startRevision){
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
window.addEventListener('cresco:studio-session-change',function(event){
 if(!event||!event.detail||!event.detail.session)return;
 state.revision++;
 state.latestSession=event.detail.session;
});
})(window);
