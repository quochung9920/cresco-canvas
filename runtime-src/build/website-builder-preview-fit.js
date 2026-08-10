(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var scheduled=false,resizeObserver=null,observedStage=null;
function one(selector,scope){return(scope||document).querySelector(selector);}
function number(value){var parsed=parseFloat(value);return Number.isFinite(parsed)?parsed:0;}
function scaleFor(frame){var match=String(frame&&frame.style&&frame.style.transform||'').match(/scale\(([^)]+)\)/);var value=match?parseFloat(match[1]):1;return Number.isFinite(value)&&value>0?value:1;}
function setStyle(node,key,value){if(node&&node.style[key]!==value)node.style[key]=value;}
function clearStyle(node,key){if(node&&node.style[key])node.style[key]='';}
function resetPreview(stage,wrap,frame,canvas,empty){
 if(stage){stage.removeAttribute('data-cresco-fit-area');['display','justifyContent','alignItems','overflowX','overflowY'].forEach(function(key){clearStyle(stage,key);});}
 if(wrap){['position','display','width','height','minWidth','minHeight','justifyContent','alignItems'].forEach(function(key){clearStyle(wrap,key);});}
 if(frame){['position','top','left','transformOrigin'].forEach(function(key){clearStyle(frame,key);});}
 if(canvas)clearStyle(canvas,'minHeight');
 if(empty)clearStyle(empty,'minHeight');
 root.removeAttribute('data-cresco-preview-fit');
}
function enhanceFitLabel(select){var option=select&&one('option[value="fit"]',select);if(option&&option.textContent!=='Fit Area')option.textContent='Fit Area';}
function applyPreviewFit(){
 scheduled=false;
 var stage=one('.cc-builder-stage',root),wrap=one('.cc-builder-frame-wrap',root),frame=one('.cc-builder-frame',root),canvas=one('.cc-builder-canvas',root),select=one('.cc-builder-viewport-toolbar select[aria-label="Zoom"]',root),empty=canvas&&one('.cc-builder-canvas-empty',canvas);
 if(!stage||!wrap||!frame||!canvas||!select)return;
 enhanceFitLabel(select);
 if(select.value!=='fit'){resetPreview(stage,wrap,frame,canvas,empty);return;}
 var scale=scaleFor(frame),computed=window.getComputedStyle(stage),availableHeight=Math.max(1,stage.clientHeight-number(computed.paddingTop)-number(computed.paddingBottom));
 var visualWidth=Math.max(1,Math.ceil(frame.offsetWidth*scale));
 var canvasMinHeight=Math.max(680,Math.ceil(availableHeight/scale));
 setStyle(stage,'display','flex');
 setStyle(stage,'justifyContent','center');
 setStyle(stage,'alignItems','flex-start');
 setStyle(stage,'overflowX','hidden');
 setStyle(stage,'overflowY','auto');
 setStyle(wrap,'position','relative');
 setStyle(wrap,'display','block');
 setStyle(wrap,'minWidth','0px');
 setStyle(wrap,'minHeight','0px');
 setStyle(wrap,'width',visualWidth+'px');
 setStyle(frame,'position','absolute');
 setStyle(frame,'top','0px');
 setStyle(frame,'left','0px');
 setStyle(frame,'transformOrigin','top left');
 setStyle(canvas,'minHeight',canvasMinHeight+'px');
 if(empty)setStyle(empty,'minHeight',canvasMinHeight+'px');
 var visualHeight=Math.max(availableHeight,Math.ceil(canvas.scrollHeight*scale));
 setStyle(wrap,'height',visualHeight+'px');
 stage.setAttribute('data-cresco-fit-area','true');
 root.setAttribute('data-cresco-preview-fit','true');
}
function schedule(){if(scheduled)return;scheduled=true;window.requestAnimationFrame(applyPreviewFit);}
function observeStage(){var stage=one('.cc-builder-stage',root);if(stage===observedStage)return;if(resizeObserver&&observedStage)resizeObserver.unobserve(observedStage);observedStage=stage;if(!stage||!window.ResizeObserver)return;if(!resizeObserver)resizeObserver=new window.ResizeObserver(schedule);resizeObserver.observe(stage);}
function refresh(){observeStage();schedule();}
root.addEventListener('change',refresh,true);
root.addEventListener('click',refresh,true);
window.addEventListener('resize',refresh);
if(window.MutationObserver)new window.MutationObserver(refresh).observe(root,{childList:true,subtree:true,attributes:true,attributeFilter:['class','style']});
if(document.fonts&&document.fonts.ready)document.fonts.ready.then(refresh).catch(function(){});
refresh();
})(window,document);
