(function(wp,window,document){
'use strict';
if(!wp||!wp.apiFetch)return;
var root=document.getElementById('cresco-canvas-standalone-editor'),settings=window.crescoWebsiteBuilderSettings||{};
if(!root||!settings.postId)return;
var ORDER=['normal','hover','focus','active'],LABELS={normal:'Normal',hover:'Hover',focus:'Focus',active:'Active'};
var catalog=(settings.widgetCatalog&&typeof settings.widgetCatalog==='object')?settings.widgetCatalog:{},session=null,scheduled=false,loaded=false;
function arr(v){return Array.isArray(v)?v:[]}
function obj(v){return v&&typeof v==='object'&&!Array.isArray(v)?v:{}}
function walk(nodes,fn){arr(nodes).forEach(function(node){fn(node);walk(node.children,fn)})}
function findNode(id){var found=null;if(!session)return null;walk(obj(session).nodes,function(node){if(!found&&String(node.id)===String(id))found=node});return found}
function declaredStates(node){
 var def=obj(catalog[node&&node.type]),declared=Array.isArray(def.states)?def.states:null;
 if(!declared)return ORDER.slice();
 var allowed={normal:true};declared.forEach(function(state){state=String(state||'').toLowerCase();if(ORDER.indexOf(state)!==-1)allowed[state]=true});
 return ORDER.filter(function(state){return !!allowed[state]});
}
function selectedIds(){
 var ids=[];
 root.querySelectorAll('.cc-studio-canvas-node.is-selected[data-cresco-id]').forEach(function(node){if(ids.indexOf(node.dataset.crescoId)===-1)ids.push(node.dataset.crescoId)});
 if(!ids.length)root.querySelectorAll('.cc-studio-tree-row.is-selected[data-cresco-node-id]').forEach(function(node){if(ids.indexOf(node.dataset.crescoNodeId)===-1)ids.push(node.dataset.crescoNodeId)});
 return ids;
}
function commonStates(){
 var nodes=selectedIds().map(findNode).filter(Boolean);
 if(!nodes.length)return ORDER.slice();
 var common=declaredStates(nodes[0]);
 nodes.slice(1).forEach(function(node){var supported=declaredStates(node);common=common.filter(function(state){return supported.indexOf(state)!==-1})});
 return common.length?common:['normal'];
}
function inspectorTab(){var active=root.querySelector('.cc-studio-left .cc-studio-inspector-tabs button.is-active');return active?String(active.textContent||'').trim().toLowerCase():''}
function visibleButtons(tabs){return Array.prototype.slice.call(tabs.querySelectorAll('button[data-cresco-widget-state]')).filter(function(button){return !button.hidden})}
function addKeyboard(tabs){
 if(tabs.dataset.crescoKeyboard==='1')return;tabs.dataset.crescoKeyboard='1';
 tabs.addEventListener('keydown',function(event){
  if(['ArrowLeft','ArrowRight','Home','End'].indexOf(event.key)===-1)return;
  var buttons=visibleButtons(tabs);if(!buttons.length)return;
  var current=Math.max(0,buttons.indexOf(document.activeElement)),next=current;
  if(event.key==='ArrowRight')next=(current+1)%buttons.length;
  else if(event.key==='ArrowLeft')next=(current-1+buttons.length)%buttons.length;
  else if(event.key==='Home')next=0;
  else if(event.key==='End')next=buttons.length-1;
  event.preventDefault();buttons[next].focus();buttons[next].click();
 });
}
function sync(){
 scheduled=false;
 var tabs=root.querySelector('.cc-studio-left .cc-studio-state-tabs');if(!tabs)return;
 var context=tabs.closest('.cc-studio-context-row'),allowed=commonStates(),activeTab=inspectorTab(),activeButton=null,normalButton=null;
 tabs.dataset.crescoWidgetStateTabs='1';tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','Widget state');tabs.style.setProperty('--cc-widget-state-count',String(allowed.length));
 if(context)context.classList.add('is-cresco-widget-state-tabs');
 Array.prototype.slice.call(tabs.querySelectorAll('button')).forEach(function(button){
  var state=String(button.dataset.crescoWidgetState||button.textContent||'').trim().toLowerCase();
  if(ORDER.indexOf(state)===-1)return;
  button.dataset.crescoWidgetState=state;button.textContent=LABELS[state];button.setAttribute('role','tab');button.setAttribute('title','Edit '+LABELS[state]+' state');
  var supported=allowed.indexOf(state)!==-1;button.hidden=!supported;
  var active=button.classList.contains('is-active');button.setAttribute('aria-selected',active&&supported?'true':'false');button.tabIndex=active&&supported?0:-1;
  if(active)activeButton=button;if(state==='normal')normalButton=button;
 });
 if(activeButton&&allowed.indexOf(activeButton.dataset.crescoWidgetState)===-1&&normalButton&&!normalButton.hidden){normalButton.click();return}
 var show=activeTab!=='content'&&allowed.length>1;tabs.hidden=!show;
 addKeyboard(tabs);
}
function schedule(){if(scheduled)return;scheduled=true;window.requestAnimationFrame(sync)}
function loadContracts(){
 if(loaded)return;loaded=true;var jobs=[];
 if(settings.sessionPath)jobs.push(wp.apiFetch({path:settings.sessionPath}).then(function(result){session=result&&result.session?result.session:result;},function(){}));
 if(settings.contextPath)jobs.push(wp.apiFetch({path:settings.contextPath}).then(function(result){if(result&&result.widgets)catalog=result.widgets;},function(){}));
 Promise.allSettled(jobs).then(schedule);
}
new MutationObserver(schedule).observe(root,{childList:true,subtree:true,attributes:true,attributeFilter:['class']});
window.addEventListener('cresco:studio-session-change',function(event){if(event.detail&&event.detail.session)session=event.detail.session;schedule()});
window.addEventListener('cresco:studio-ready',function(){loadContracts();schedule()});
loadContracts();schedule();
window.CrescoStudioWidgetStateTabs={version:'1.0.0',states:ORDER.slice(),sync:sync};
})(window.wp,window,document);
