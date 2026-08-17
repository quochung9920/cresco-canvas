(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');if(!root)return;
var scheduled=false;
var ALL_UNITS=['px','%','em','rem','vw','vh','vmin','vmax','ch'];
var FONT_UNITS=['px','em','rem','vw','vh','vmin','vmax'];
var RADIUS_UNITS=['px','%','em','rem'];
function text(n){return n?String(n.textContent||'').trim():''}
function panel(){return Array.prototype.slice.call(root.querySelectorAll('.cc-studio-left .cc-studio-panel')).find(function(p){return text(p.querySelector('.cc-studio-panel-head strong'))==='Global Design'})||null}
function emit(input){input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}))}
function parse(value){var raw=String(value||'').trim(),m=raw.match(/^(-?(?:\d+|\d*\.\d+))(px|%|em|rem|vw|vh|vmin|vmax|ch)$/i);return m?{number:m[1],unit:m[2].toLowerCase()}:null}
function option(value,label){var o=document.createElement('option');o.value=value;o.textContent=label||value;return o}
function unitsFor(input){var key=String(input.dataset.fluid||input.dataset.number||input.dataset.breakpoint||'').toLowerCase();if(input.hasAttribute('data-number')||input.hasAttribute('data-breakpoint'))return['px'];if(/^h[1-6]$|^font/.test(key))return FONT_UNITS;if(/radius/.test(key))return RADIUS_UNITS;return ALL_UNITS}
function color(host){var value=host.querySelector('.cc-gd-add input[data-color-value]');if(!value||value.dataset.gdColorPicker)return;value.dataset.gdColorPicker='1';var picker=document.createElement('input');picker.type='color';picker.className='cc-gd-custom-color-picker';picker.value=/^#[0-9a-f]{6}$/i.test(value.value)?value.value:'#5b5cf6';picker.setAttribute('aria-label','Choose custom color');value.parentNode.insertBefore(picker,value);picker.addEventListener('input',function(){value.value=picker.value;emit(value)});value.addEventListener('input',function(){if(/^#[0-9a-f]{6}$/i.test(value.value))picker.value=value.value})}
function enhanceDimension(input){
 if(!input||input.dataset.gdUnitControl==='1')return;
 input.dataset.gdUnitControl='1';
 var numericOnly=input.hasAttribute('data-number')||input.hasAttribute('data-breakpoint'),units=unitsFor(input),raw=String(input.value||'').trim(),parsed=numericOnly?{number:raw,unit:'px'}:parse(raw),mode=parsed&&units.indexOf(parsed.unit)!==-1?parsed.unit:'custom';
 var wrap=document.createElement('div');wrap.className='cc-gd-unit-control';
 var proxy=document.createElement('input');proxy.className='cc-gd-unit-value';proxy.step='any';
 var unit=document.createElement('select');unit.className='cc-gd-unit-select';unit.setAttribute('aria-label','Unit');
 units.forEach(function(u){unit.appendChild(option(u))});if(!numericOnly)unit.appendChild(option('custom','Custom'));
 function paint(nextMode,current){
  var p=parse(current),custom=nextMode==='custom';unit.value=nextMode;proxy.type=custom?'text':'number';proxy.placeholder=custom?'clamp(), calc(), min(), max()':'0';
  if(custom)proxy.value=String(current||'');else if(p)proxy.value=p.number;else if(numericOnly)proxy.value=String(current||'');else proxy.value='';
  wrap.classList.toggle('is-custom',custom);
 }
 paint(mode,raw);
 input.classList.add('cc-gd-unit-source');input.hidden=true;
 input.parentNode.insertBefore(wrap,input);wrap.appendChild(proxy);wrap.appendChild(unit);wrap.appendChild(input);
 function apply(){var value=unit.value==='custom'?proxy.value:String(proxy.value||'')+(numericOnly?'':unit.value);if(numericOnly)value=String(proxy.value||'');input.value=value;input.setAttribute('title',value);emit(input)}
 unit.addEventListener('change',function(){var before=String(input.value||'');paint(unit.value,before);if(unit.value!=='custom'&&proxy.value!=='')apply();else if(unit.value==='custom')window.setTimeout(function(){proxy.focus();proxy.select()},0)});
 proxy.addEventListener('input',apply);proxy.addEventListener('change',apply);
}
function enhance(host){
 color(host);
 host.querySelectorAll('input[data-fluid],input[data-number],input[data-breakpoint]').forEach(enhanceDimension);
}
function sync(){scheduled=false;var p=panel(),host=p&&p.querySelector('.cc-global-design-pro');if(host)enhance(host)}
function schedule(){if(scheduled)return;scheduled=true;window.requestAnimationFrame(sync)}
new MutationObserver(schedule).observe(root,{childList:true,subtree:true});window.addEventListener('cresco:studio-ready',schedule);schedule();
window.CrescoGlobalDesignSharedControls={version:'2.0.0',sync:sync,units:ALL_UNITS.slice()};
})(window,document);
