(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var scheduled=false,CUSTOM='__custom__';
var fluidPresets=[
 ['Fluid XS - 12-14px','clamp(0.75rem, 0.70rem + 0.20vw, 0.875rem)'],
 ['Fluid S - 14-16px','clamp(0.875rem, 0.82rem + 0.20vw, 1rem)'],
 ['Fluid Base - 16-18px','clamp(1rem, 0.95rem + 0.25vw, 1.125rem)'],
 ['Fluid M - 18-22px','clamp(1.125rem, 1rem + 0.50vw, 1.375rem)'],
 ['Fluid L - 20-26px','clamp(1.25rem, 1.05rem + 0.80vw, 1.625rem)'],
 ['Fluid XL - 24-32px','clamp(1.5rem, 1.20rem + 1.10vw, 2rem)'],
 ['Fluid 2XL - 28-40px','clamp(1.75rem, 1.30rem + 1.80vw, 2.5rem)'],
 ['Fluid 3XL - 32-48px','clamp(2rem, 1.40rem + 2.50vw, 3rem)'],
 ['Fluid 4XL - 36-76px','clamp(2.25rem, 1.45rem + 3.10vw, 4.75rem)'],
 ['Fluid 5XL - 44-88px','clamp(2.75rem, 1.60rem + 4.20vw, 5.5rem)'],
 ['Fluid 6XL - 52-104px','clamp(3.25rem, 1.80rem + 5vw, 6.5rem)']
];
var fixedSizes=[10,11,12,13,14,15,16,18,20,22,24,26,28,30,32,36,40,44,48,52,56,60,64,72,80,88,96,104,112,120,128];
function text(node){return node?String(node.textContent||'').trim():''}
function panel(){
 var panels=Array.prototype.slice.call(root.querySelectorAll('.cc-studio-left .cc-studio-panel'));
 return panels.find(function(item){var title=item.querySelector('.cc-studio-panel-head strong');return text(title)==='Global Design'})||null;
}
function option(label,value){var o=document.createElement('option');o.value=value;o.textContent=label;return o}
function populate(select){
 var responsive=document.createElement('optgroup');responsive.label='Responsive';
 fluidPresets.forEach(function(item){responsive.appendChild(option(item[0],item[1]))});
 select.appendChild(responsive);
 var fixed=document.createElement('optgroup');fixed.label='Fixed pixels';
 fixedSizes.forEach(function(size){fixed.appendChild(option(size+' px',size+'px'))});
 select.appendChild(fixed);
 select.appendChild(option('Custom... ',CUSTOM));
}
function matchingOption(select,value){
 var items=select.querySelectorAll('option');
 for(var i=0;i<items.length;i++){if(items[i].value===value)return items[i]}
 return null;
}
function currentLabel(value){
 if(/^(?:clamp|min|max|calc)\(/i.test(value))return 'Current - Responsive';
 return value?'Current - '+value:'Current value';
}
function syncControl(control,input){
 if(control.dataset.customActive==='1')return;
 var select=control.querySelector('.cc-gd-size-select'),value=String(input.value||'').trim();
 if(!select)return;
 var match=matchingOption(select,value),current=select.querySelector('option[data-current]');
 if(match&&match.value!==CUSTOM){
  if(current)current.remove();
  select.value=value;
  control.classList.remove('is-custom');
  return;
 }
 if(!current){
  current=option(currentLabel(value),value);
  current.dataset.current='1';
  select.insertBefore(current,select.firstChild);
 }else{
  current.value=value;
  current.textContent=currentLabel(value);
 }
 select.value=value;
 control.classList.remove('is-custom');
}
function setCustom(control,input,enabled){
 control.dataset.customActive=enabled?'1':'0';
 control.classList.toggle('is-custom',enabled);
 if(enabled){
  var select=control.querySelector('.cc-gd-size-select');
  if(select)select.value=CUSTOM;
  window.setTimeout(function(){input.focus();input.select()},0);
 }
}
function enhanceRow(row){
 if(!row||row.dataset.gdSizePicker==='1')return;
 var input=row.querySelector('input[data-fluid]'),copy=row.querySelector('.cc-gd-control-label');
 if(!input)return;
 var label=copy?text(copy.querySelector('strong')):String(input.dataset.fluid||'Type');
 var replacement=document.createElement('div');
 replacement.className=row.className+' cc-gd-size-row';
 replacement.dataset.gdSizePicker='1';
 replacement.setAttribute('role','group');
 replacement.setAttribute('aria-label',label+' font size');
 if(copy)replacement.appendChild(copy);
 var control=document.createElement('div');control.className='cc-gd-size-control';control.dataset.customActive='0';
 var select=document.createElement('select');select.className='cc-gd-size-select';select.setAttribute('aria-label',label+' size preset');populate(select);
 var custom=document.createElement('div');custom.className='cc-gd-size-custom';
 input.classList.add('cc-gd-size-custom-input');
 input.setAttribute('placeholder','e.g. 48px, 3rem or clamp(...)');
 input.setAttribute('aria-label',label+' custom size');
 custom.appendChild(input);
 control.appendChild(select);control.appendChild(custom);replacement.appendChild(control);
 row.parentNode.replaceChild(replacement,row);
 syncControl(control,input);
 select.addEventListener('change',function(){
  if(this.value===CUSTOM){setCustom(control,input,true);return}
  setCustom(control,input,false);
  input.value=this.value;
  input.setAttribute('title',input.value);
  input.dispatchEvent(new Event('input',{bubbles:true}));
  input.dispatchEvent(new Event('change',{bubbles:true}));
 });
 input.addEventListener('input',function(event){
  input.setAttribute('title',input.value||'');
  if(control.dataset.customActive==='1'&&event.isTrusted)return;
  control.dataset.customActive='0';syncControl(control,input);
 });
 input.addEventListener('change',function(event){
  if(control.dataset.customActive==='1'&&event.isTrusted)return;
  control.dataset.customActive='0';syncControl(control,input);
 });
}
function enhance(host){
 if(!host)return;
 host.querySelectorAll('.cc-gd-control-row--type').forEach(enhanceRow);
 if(host.dataset.gdSizePresetHook!=='1'){
  host.dataset.gdSizePresetHook='1';
  host.addEventListener('click',function(event){
   if(!event.target.closest('.cc-gd-presets'))return;
   window.requestAnimationFrame(function(){host.querySelectorAll('.cc-gd-size-control').forEach(function(control){control.dataset.customActive='0';var input=control.querySelector('input[data-fluid]');if(input)syncControl(control,input)})});
  });
 }
}
function sync(){scheduled=false;var current=panel(),host=current&&current.querySelector('.cc-global-design-pro');if(host)enhance(host)}
function schedule(){if(scheduled)return;scheduled=true;window.requestAnimationFrame(sync)}
var observer=new MutationObserver(schedule);observer.observe(root,{childList:true,subtree:true});
window.addEventListener('cresco:studio-ready',schedule);schedule();
window.CrescoGlobalDesignTypeSizePicker={version:'1.0.0',sync:sync,fluidPresetCount:fluidPresets.length,fixedSizeCount:fixedSizes.length};
})(window,document);
