(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var stats=window.crescoStudioStructureRowDrag={version:'1.0.0',mode:'row-anywhere-proxy',ready:true,proxied:0,ignored:0};
var EXCLUDED='.cc-studio-tree-actions,.cc-studio-tree-toggle,.cc-studio-context-menu,input,textarea,select,option,a,[contenteditable="true"],[role="menuitem"]';
function ensureHandle(row){
  var handle=row.querySelector(':scope > .cc-studio-tree-drag-handle');
  if(handle)return handle;
  handle=document.createElement('span');
  handle.className='cc-studio-tree-drag-handle dashicons dashicons-move';
  handle.setAttribute('data-cresco-pointer-drag','1');
  handle.setAttribute('aria-hidden','true');
  handle.draggable=false;
  var select=row.querySelector(':scope > .cc-studio-tree-select');
  row.insertBefore(handle,select||row.firstChild);
  return handle;
}
function canProxy(event,row){
  if(event.crescoRowProxy)return false;
  if(event.button!==0&&event.pointerType!=='touch')return false;
  if(event.target&&event.target.closest&&event.target.closest('.cc-studio-tree-drag-handle'))return false;
  if(event.target&&event.target.closest&&event.target.closest(EXCLUDED))return false;
  var button=event.target&&event.target.closest?event.target.closest('button'):null;
  if(button&&!button.classList.contains('cc-studio-tree-select'))return false;
  if(row.querySelector(':scope > .cc-studio-tree-drag-handle.is-disabled'))return false;
  return true;
}
function proxyPointerDown(event,row){
  var handle=ensureHandle(row),proxy;
  try{
    proxy=new window.PointerEvent('pointerdown',{
      bubbles:true,cancelable:true,composed:true,
      pointerId:event.pointerId||1,pointerType:event.pointerType||'mouse',isPrimary:event.isPrimary!==false,
      clientX:event.clientX,clientY:event.clientY,screenX:event.screenX,screenY:event.screenY,
      button:0,buttons:event.buttons||1,ctrlKey:event.ctrlKey,shiftKey:event.shiftKey,altKey:event.altKey,metaKey:event.metaKey
    });
  }catch(error){
    proxy=new window.MouseEvent('pointerdown',{bubbles:true,cancelable:true,clientX:event.clientX,clientY:event.clientY,button:0,buttons:event.buttons||1});
  }
  try{Object.defineProperty(proxy,'crescoRowProxy',{value:true});}catch(error){proxy.crescoRowProxy=true;}
  handle.dispatchEvent(proxy);
  stats.proxied++;
}
root.addEventListener('pointerdown',function(event){
  var row=event.target&&event.target.closest?event.target.closest('.cc-studio-tree-row[data-cresco-node-id]'):null;
  if(!row||!root.contains(row))return;
  if(!canProxy(event,row)){stats.ignored++;return;}
  proxyPointerDown(event,row);
},true);
})(window,document);
