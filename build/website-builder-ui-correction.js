(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var NS='http://www.w3.org/2000/svg';
var order=['wide','desktop','laptop','tablet','mobile'];
var meta={
 wide:{label:'Wide',width:1920},
 desktop:{label:'Desktop',width:1440},
 laptop:{label:'Laptop',width:1366},
 tablet:{label:'Tablet',width:768},
 mobile:{label:'Mobile',width:390}
};
var settings=window.crescoWebsiteBuilderSettings||{};
var widths=Object.assign({},Object.keys(meta).reduce(function(out,key){out[key]=meta[key].width;return out;},{}),settings.previewWidths||{});
var scheduled=false;

function all(selector,scope){return Array.prototype.slice.call((scope||root).querySelectorAll(selector));}
function text(node){return node?String(node.textContent||'').trim():'';}
function svgNode(name,attrs){var node=document.createElementNS(NS,name);Object.keys(attrs||{}).forEach(function(key){node.setAttribute(key,String(attrs[key]));});return node;}
function line(svg,x1,y1,x2,y2){svg.appendChild(svgNode('line',{x1:x1,y1:y1,x2:x2,y2:y2}));}
function rect(svg,x,y,width,height,rx){svg.appendChild(svgNode('rect',{x:x,y:y,width:width,height:height,rx:rx||0}));}
function path(svg,d){svg.appendChild(svgNode('path',{d:d}));}
function circle(svg,cx,cy,r){svg.appendChild(svgNode('circle',{cx:cx,cy:cy,r:r}));}
function deviceSvg(id){
 var svg=svgNode('svg',{viewBox:'0 0 24 18','aria-hidden':'true','focusable':'false','data-cresco-device-icon':id});
 svg.classList.add('cc-cresco-device-icon');
 if(id==='wide'){
  rect(svg,1,2,22,11,1.5);line(svg,7,15,17,15);line(svg,10,13.5,10,15);line(svg,14,13.5,14,15);line(svg,5,17,19,17);
 }else if(id==='desktop'){
  rect(svg,3,2,18,11,1.5);line(svg,12,13,12,16);line(svg,8,16,16,16);
 }else if(id==='laptop'){
  rect(svg,4,2,16,10,1.4);path(svg,'M2.5 14.2h19l-1.8 2H4.3z');line(svg,10,15.2,14,15.2);
 }else if(id==='tablet'){
  rect(svg,6.5,1,11,16,1.8);line(svg,10,14.5,14,14.5);
 }else{
  rect(svg,8,1,8,16,2);circle(svg,12,14.5,.55);
 }
 return svg;
}
function deviceId(button,index){
 var explicit=button&&button.dataset?button.dataset.device||button.dataset.crescoDevice:'';
 if(explicit&&meta[explicit])return explicit;
 var name=String(button&&button.getAttribute&&(button.getAttribute('aria-label')||button.getAttribute('title'))||'').toLowerCase();
 for(var i=0;i<order.length;i++){
  var id=order[i];
  if(name===id||name===meta[id].label.toLowerCase()||name.indexOf(id)===0)return id;
 }
 return order[index]||'desktop';
}
function decorateButton(button,id){
 if(!button||!meta[id])return;
 button.dataset.crescoDevice=id;
 all('.dashicons',button).forEach(function(icon){icon.remove();});
 var current=button.querySelector('svg[data-cresco-device-icon]');
 if(!current||current.getAttribute('data-cresco-device-icon')!==id){
  if(current)current.remove();
  button.insertBefore(deviceSvg(id),button.firstChild||null);
 }
 button.title=meta[id].label+' · '+String(widths[id]||meta[id].width)+'px';
}
function activeToolbarDevice(){
 var buttons=all('.cc-studio-device-toolbar button');
 for(var i=0;i<buttons.length;i++)if(buttons[i].classList.contains('is-active'))return deviceId(buttons[i],i);
 return buttons.length?deviceId(buttons[0],0):'wide';
}
function decorateResponsive(){
 all('.cc-studio-device-toolbar button').forEach(function(button,index){decorateButton(button,deviceId(button,index));});
 all('.cc-studio-property-device').forEach(function(button,index){decorateButton(button,deviceId(button,index));});
 var active=activeToolbarDevice(),widthNode=root.querySelector('.cc-studio-width');
 if(widthNode){
  var value=meta[active].label+' · '+String(widths[active]||meta[active].width)+'px';
  if(text(widthNode)!==value)widthNode.textContent=value;
  widthNode.dataset.crescoBreakpointLabel='1';
 }
}
function decorateStructure(){
 all('.cc-studio-tree-label').forEach(function(label){
  var value=text(label);
  if(value&&label.title!==value)label.title=value;
 });
 all('.cc-studio-tree-row').forEach(function(row){
  var label=row.querySelector('.cc-studio-tree-label'),more=row.querySelector('.cc-studio-tree-actions button:last-child');
  if(label&&more){
   var name=text(label);
   if(name)more.setAttribute('aria-label','More actions for '+name);
  }
 });
}
function apply(){scheduled=false;decorateResponsive();decorateStructure();window.crescoStudioUiCorrection={version:'1.0.0',structure:'tree-grid-v2',responsiveIcons:'cresco-device-v1',breakpointLabel:true,appliedAt:Date.now()};}
function schedule(){if(scheduled)return;scheduled=true;window.requestAnimationFrame(apply);}
apply();
if(window.MutationObserver)new window.MutationObserver(schedule).observe(root,{childList:true,subtree:true,attributes:true,attributeFilter:['class']});
window.addEventListener('cresco:studio-session-change',schedule);
})(window,document);
