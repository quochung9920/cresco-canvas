(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var serial=0;
function all(selector,scope){return Array.prototype.slice.call((scope||root).querySelectorAll(selector));}
function nativeValue(input,value){if(!input||!input.isConnected)return;var descriptor=Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype,'value');if(descriptor&&descriptor.set)descriptor.set.call(input,String(value==null?'':value));else input.value=String(value==null?'':value);input.dispatchEvent(new window.Event('input',{bubbles:true}));}
function serialize(value,unit){value=String(value==null?'':value).trim();if(!value)return'';return unit==='custom'?value:value+unit;}
function sourceInputs(section){return all(':scope > .cc-studio-spacing__grid > label',section).map(function(label){return all('input',label).find(function(input){return!input.closest('[data-cresco-dimension-ui]');});});}
function syncSection(section){if(!section||!section.isConnected)return;var link=section.querySelector('.cc-studio-spacing-tools .cc-studio-dimension-link');if(!link||link.getAttribute('aria-pressed')!=='true')return;var unit=section.querySelector('.cc-studio-spacing-tools .cc-studio-dimension-unit');var proxies=all(':scope > .cc-studio-spacing__grid .cc-studio-spacing-proxy',section),sources=sourceInputs(section);if(!unit||proxies.length!==4||sources.length!==4||sources.some(function(input){return!input;}))return;var value=serialize(proxies[0].value,unit.value),token=++serial,index=0;function step(){if(token!==serial||index>=sources.length)return;var source=sources[index++];nativeValue(source,value);if(index<sources.length)window.setTimeout(step,0);}step();}
function schedule(section){window.setTimeout(function(){syncSection(section);},0);}
root.addEventListener('input',function(event){var proxy=event.target.closest&&event.target.closest('.cc-studio-spacing-proxy');if(proxy)schedule(proxy.closest('.cc-studio-spacing'));},true);
root.addEventListener('change',function(event){var unit=event.target.closest&&event.target.closest('.cc-studio-spacing-tools .cc-studio-dimension-unit');if(unit)schedule(unit.closest('.cc-studio-spacing'));},true);
root.addEventListener('click',function(event){var link=event.target.closest&&event.target.closest('.cc-studio-spacing-tools .cc-studio-dimension-link');if(link)schedule(link.closest('.cc-studio-spacing'));},false);
})(window,document);
