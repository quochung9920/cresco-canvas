(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var state={queued:false,timer:0};
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
function status(target,mode,message){
 if(!target)return;
 var node=target.querySelector('.cc-global-design-authority-status');
 if(mode==='clear'){
  if(node)node.remove();
  return;
 }
 if(!node){
  node=document.createElement('div');
  node.className='cc-global-design-authority-status';
  node.setAttribute('role','status');
  var head=target.querySelector('.cc-studio-panel-head');
  head&&head.nextSibling?target.insertBefore(node,head.nextSibling):target.appendChild(node);
 }
 node.className='cc-global-design-authority-status is-'+mode;
 node.innerHTML='<span class="spinner '+(mode==='loading'?'is-active':'')+'"></span><span></span>';
 node.lastElementChild.textContent=message;
}
function sync(){
 var target=panel();
 if(!target)return;
 removeLegacyUx(target);
 var pro=target.querySelector('.cc-global-design-pro');
 if(pro){
  status(target,'clear','');
  removeLegacySurface(target);
  return;
 }
 if(window.crescoGlobalDesignPro&&window.crescoGlobalDesignPro.ready){
  status(target,'loading','Loading Global Design Pro…');
  window.clearTimeout(state.timer);
  state.timer=window.setTimeout(function(){
   var current=panel();
   if(current&&!current.querySelector('.cc-global-design-pro')){
    status(current,'error','Global Design Pro did not mount. Reload Studio or use Retry.');
    var box=current.querySelector('.cc-global-design-authority-status');
    if(box&&!box.querySelector('button')){
     var retry=document.createElement('button');
     retry.type='button';retry.textContent='Retry Global Design';
     retry.addEventListener('click',function(){
      status(current,'loading','Retrying Global Design Pro…');
      try{window.dispatchEvent(new CustomEvent('cresco:studio-ready',{detail:{retryGlobalDesign:true}}));}catch(e){}
      queue();
     });
     box.appendChild(retry);
    }
   }
  },2500);
 }
}
function queue(){
 if(state.queued)return;
 state.queued=true;
 window.requestAnimationFrame(function(){state.queued=false;sync();});
}
new MutationObserver(queue).observe(root,{childList:true,subtree:true});
window.addEventListener('cresco:studio-ready',queue);
window.crescoGlobalDesignAuthority={version:'1.0.0',mode:'pro',sync:sync};
queue();
})(window,document);
