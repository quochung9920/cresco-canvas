(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var state={queued:false,timer:0,target:null};
function panel(){
 return Array.prototype.slice.call(root.querySelectorAll('.cc-studio-left .cc-studio-panel')).find(function(item){
  var heading=item.querySelector('.cc-studio-panel-head strong');
  return heading&&String(heading.textContent||'').trim()==='Global Design';
 })||null;
}
function removeLegacyUx(target){
 if(!target)return;
 target.dataset.uxEnhanced='1';
 target.classList.remove('cc-studio-ux-global');
 Array.prototype.slice.call(target.querySelectorAll('.cc-studio-ux-token-search')).forEach(function(node){node.remove();});
}
function removeLegacySurface(target){
 if(!target||!target.querySelector('.cc-global-design-pro'))return;
 target.setAttribute('data-global-design-authority','pro');
 Array.prototype.slice.call(target.children).forEach(function(node){
  if(node.classList&&(
   node.classList.contains('cc-studio-token-list')||
   node.classList.contains('cc-studio-settings-section')||
   node.classList.contains('cc-studio-ux-token-search')
  ))node.remove();
 });
}
function clearTimer(){
 if(!state.timer)return;
 window.clearTimeout(state.timer);
 state.timer=0;
}
function status(target,mode,message){
 if(!target)return null;
 var node=target.querySelector('.cc-global-design-authority-status');
 if(mode==='clear'){
  if(node)node.remove();
  return null;
 }
 if(node&&node.dataset.mode===mode&&node.dataset.message===message)return node;
 if(!node){
  node=document.createElement('div');
  node.className='cc-global-design-authority-status';
  node.setAttribute('role','status');
  var head=target.querySelector('.cc-studio-panel-head');
  head&&head.nextSibling?target.insertBefore(node,head.nextSibling):target.appendChild(node);
 }
 node.dataset.mode=mode;
 node.dataset.message=message;
 node.className='cc-global-design-authority-status is-'+mode;
 node.textContent='';
 var spinner=document.createElement('span');
 spinner.className='spinner '+(mode==='loading'?'is-active':'');
 var text=document.createElement('span');
 text.textContent=message;
 node.appendChild(spinner);
 node.appendChild(text);
 return node;
}
function retryButton(box,current){
 if(!box||box.querySelector('button'))return;
 var retry=document.createElement('button');
 retry.type='button';
 retry.textContent='Retry Global Design';
 retry.addEventListener('click',function(){
  clearTimer();
  status(current,'loading','Retrying Global Design Pro…');
  try{window.dispatchEvent(new CustomEvent('cresco:studio-ready',{detail:{retryGlobalDesign:true}}));}catch(e){}
  queue();
 });
 box.appendChild(retry);
}
function sync(){
 var target=panel();
 if(!target){clearTimer();state.target=null;return;}
 if(state.target!==target){clearTimer();state.target=target;}
 removeLegacyUx(target);
 var pro=target.querySelector('.cc-global-design-pro');
 if(pro){
  clearTimer();
  status(target,'clear','');
  removeLegacySurface(target);
  return;
 }
 if(window.crescoGlobalDesignPro&&window.crescoGlobalDesignPro.ready){
  status(target,'loading','Loading Global Design Pro…');
  if(!state.timer){
   state.timer=window.setTimeout(function(){
    state.timer=0;
    var current=panel();
    if(current&&!current.querySelector('.cc-global-design-pro')){
     var box=status(current,'error','Global Design Pro did not mount. Reload Studio or use Retry.');
     retryButton(box,current);
    }
   },2500);
  }
 }
}
function queue(){
 if(state.queued)return;
 state.queued=true;
 window.requestAnimationFrame(function(){state.queued=false;sync();});
}
new MutationObserver(queue).observe(root,{childList:true,subtree:true});
window.addEventListener('cresco:studio-ready',queue);
window.crescoGlobalDesignAuthority={version:'1.0.1',mode:'pro',sync:sync};
queue();
})(window,document);
