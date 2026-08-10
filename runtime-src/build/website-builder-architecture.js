(function(window,document,wp){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
var cfg=window.crescoBuilderArchitectureSettings||{};
if(!root||!cfg.postId||!wp||!wp.apiFetch)return;
var api=wp.apiFetch,state={arch:null,commands:[],bridge:null,context:null,candidate:null,palette:null,ai:null,docs:null,render:null};
function q(s,r){return(r||root).querySelector(s);}
function qa(s,r){return Array.prototype.slice.call((r||root).querySelectorAll(s));}
function text(el){return String(el&&el.textContent||'').replace(/\s+/g,' ').trim();}
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
function toast(m,t){var n=document.createElement('div');n.className='cc-arch-toast is-'+(t||'info');n.textContent=m;n.setAttribute('role','status');document.body.appendChild(n);requestAnimationFrame(function(){n.classList.add('is-visible');});setTimeout(function(){n.remove();},3000);}
function ids(){return qa('.cc-builder-node.is-selected[data-cresco-id]').map(function(n){return n.getAttribute('data-cresco-id');}).filter(Boolean);}
function target(scope){var x=ids();if(scope==='document')return{};return scope==='selection'?{nodeIds:x}:{nodeId:x[0]||''};}
function button(name){return qa('button,a').find(function(el){return text(el).toLowerCase()===String(name).toLowerCase();})||null;}
function rail(name){return qa('.cc-builder-rail button').find(function(el){return text(el).toLowerCase()===String(name).toLowerCase();})||null;}
function wait(fn,ms){return new Promise(function(resolve,reject){var start=Date.now();(function poll(){var v=fn();if(v)return resolve(v);if(Date.now()-start>(ms||2500))return reject(new Error('Timed out waiting for Cresco editor UI.'));setTimeout(poll,60);})();});}
function setValue(el,v){var p=el instanceof HTMLTextAreaElement?HTMLTextAreaElement.prototype:HTMLInputElement.prototype,d=Object.getOwnPropertyDescriptor(p,'value');if(d&&d.set)d.set.call(el,v);else el.value=v;el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}));}
function openAI(){var b=rail('AI');if(b)b.click();return !!b;}
function captureSession(){
 if(state.bridge&&typeof state.bridge.getSession==='function')return Promise.resolve(state.bridge.getSession());
 return new Promise(function(resolve){
  if(!openAI())return resolve(null);
  wait(function(){return button('Copy Session');},1000).then(function(copy){
   if(!navigator.clipboard||!navigator.clipboard.writeText)return resolve(null);
   var old=navigator.clipboard.writeText,done=false;
   navigator.clipboard.writeText=function(v){if(done)return Promise.resolve();done=true;navigator.clipboard.writeText=old;try{resolve(JSON.parse(v));}catch(e){resolve(null);}return Promise.resolve();};
   setTimeout(function(){if(!done){done=true;navigator.clipboard.writeText=old;resolve(null);}},800);copy.click();
  }).catch(function(){resolve(null);});
 });
}
function applyCandidate(session){
 if(state.bridge&&typeof state.bridge.applySession==='function')return Promise.resolve(state.bridge.applySession(session));
 openAI();return wait(function(){return qa('textarea').find(function(a){return String(a.placeholder||'').indexOf('cresco-session/v1')!==-1;});},1200).then(function(area){
  setValue(area,JSON.stringify(session,null,2));var validate=button('Validate');if(!validate)throw new Error('Validate control not found.');validate.click();return wait(function(){return button('Apply to editor');},4000);
 }).then(function(apply){apply.click();toast('Candidate applied. Review before Update.','success');});
}
function scopeOK(scope){var n=ids().length;return scope==='document'||(scope==='selection'?n>0:n===1);}
function context(scope,purpose,prompt){
 if(!scopeOK(scope))return Promise.reject(new Error(scope==='selection'?'Select one or more widgets first.':'Select one widget or section first.'));
 return captureSession().then(function(session){return api({path:cfg.scopedContextPath,method:'POST',data:{scope:scope,target:target(scope),purpose:purpose||'edit',mode:'auto',currentSession:session||undefined}});}).then(function(r){var c=r.context||r;if(prompt)c.request={prompt:prompt,preserve:{brandTokens:true,responsive:true,content:true}};state.context=c;return c;});
}
function previewPatch(patch){
 if(!state.context)return Promise.reject(new Error('Generate scoped context first.'));
 return captureSession().then(function(session){return api({path:cfg.commandPreviewPath,method:'POST',data:{currentSession:session||undefined,command:{schema:'cresco-command/v1',command:'patch.apply',source:'ai',transactionId:'ai-'+Date.now(),target:state.context.scopePackage.target,payload:{patch:patch}}}});}).then(function(r){state.candidate=r;return r;});
}
function copyJSON(v){var s=typeof v==='string'?v:JSON.stringify(v,null,2);return navigator.clipboard&&navigator.clipboard.writeText?navigator.clipboard.writeText(s):Promise.reject(new Error('Clipboard API unavailable.'));}
function addCommand(id,label,group,run,keywords){state.commands.push({id:id,label:label,group:group,run:run,keywords:keywords||''});}
function seedCommands(){
 addCommand('ai.widget','AI: Edit selected widget','AI',function(){showAI('widget');},'widget');
 addCommand('ai.section','AI: Redesign selected section','AI',function(){showAI('subtree');},'section subtree');
 addCommand('ai.selection','AI: Edit current selection','AI',function(){showAI('selection');},'multi select');
 addCommand('ai.document','AI: Redesign current document','AI',function(){showAI('document');},'page document');
 ['widget','subtree','selection','document'].forEach(function(s){addCommand('export.'+s,'Copy '+s+' AI context','Export',function(){context(s,s==='document'?'redesign':'edit','').then(copyJSON).then(function(){toast('Scoped context copied.','success');});},'json scope');});
 addCommand('site.documents','Open Cresco Documents','Site',showDocuments,'pages theme templates');
 addCommand('view.renderer','Open authoritative Renderer Preview','View',showRender,'frontend parity iframe');
 addCommand('view.pixel','Canvas: Pixel 100%','View',function(){var z=q('.cc-builder-viewport-toolbar select[aria-label="Zoom"]');if(!z)return toast('Zoom control not found.','error');var o=qa('option',z).find(function(x){return text(x)==='100%';});if(!o)return toast('100% option not found.','error');z.value=o.value;z.dispatchEvent(new Event('change',{bubbles:true}));},'zoom 100');
 addCommand('diagnostics.a11y','Scan Canvas accessibility','Diagnostics',scanA11y,'a11y');
}
function shell(){
 root.setAttribute('data-cresco-architecture','v1');
 [['.cc-builder-header','topbar'],['.cc-builder-rail','activity-rail'],['.cc-builder-panel','context-panel'],['.cc-builder-canvas','canvas'],['.cc-builder-inspector','inspector']].forEach(function(x){var el=q(x[0]);if(el)el.setAttribute('data-cresco-zone',x[1]);});
 var structure=rail('Structure');if(structure){structure.setAttribute('data-cresco-activity','navigator');var n=Array.prototype.slice.call(structure.childNodes).find(function(c){return c.nodeType===3&&c.nodeValue.trim();});if(n)n.nodeValue=' Navigator';}
 var bar=q('.cc-arch-statusbar');if(!bar){bar=document.createElement('div');bar.className='cc-arch-statusbar';bar.innerHTML='<span data-crumb>Document</span><span data-meta></span><button type="button" data-palette>⌘K</button>';root.appendChild(bar);q('[data-palette]',bar).onclick=showPalette;}
 var sel=ids(),crumb=(cfg.documentType||'Document')+(sel.length?' > '+(sel.length===1?sel[0]:'Selection ('+sel.length+')'):'');q('[data-crumb]',bar).textContent=crumb;q('[data-meta]',bar).textContent=sel.length+' selected · '+(cfg.documentType||'page');
}
function overlay(cls,html){var o=document.createElement('div');o.className='cc-arch-overlay '+cls;o.innerHTML=html;document.body.appendChild(o);o.addEventListener('click',function(e){if(e.target===o||e.target.closest('[data-close]'))o.classList.remove('is-open');});return o;}
function showPalette(){
 if(!state.palette){state.palette=overlay('cc-arch-palette','<section class="cc-arch-dialog"><header><strong>Cresco Command Palette</strong><button data-close>×</button></header><input data-query type="search" placeholder="Search commands…"><div data-list class="cc-arch-command-list"></div></section>');q('[data-query]',state.palette).oninput=renderPalette;q('[data-list]',state.palette).onclick=function(e){var b=e.target.closest('[data-command]');if(!b)return;state.palette.classList.remove('is-open');var c=state.commands.find(function(x){return x.id===b.dataset.command;});if(c)Promise.resolve(c.run()).catch(function(err){toast(err.message,'error');});};}
 state.palette.classList.add('is-open');q('[data-query]',state.palette).value='';renderPalette();setTimeout(function(){q('[data-query]',state.palette).focus();},20);
}
function renderPalette(){var s=q('[data-query]',state.palette).value.toLowerCase(),list=q('[data-list]',state.palette),m=state.commands.filter(function(c){return !s||[c.id,c.label,c.group,c.keywords].join(' ').toLowerCase().indexOf(s)!==-1;});list.innerHTML=m.map(function(c){return '<button data-command="'+esc(c.id)+'"><span>'+esc(c.label)+'</span><small>'+esc(c.group)+'</small></button>';}).join('')||'<div class="cc-arch-empty">No command found.</div>';}
function showAI(scope){
 if(!state.ai){state.ai=overlay('cc-arch-ai','<section class="cc-arch-dialog cc-arch-ai-dialog"><header><div><strong>Scoped AI</strong><small>Server-enforced Widget / Section / Selection / Document boundary</small></div><button data-close>×</button></header><label>Scope<select data-scope><option value="widget">Selected Widget</option><option value="subtree">Selected Section</option><option value="selection">Current Selection</option><option value="document">Entire Document</option></select></label><label>Goal<textarea data-prompt></textarea></label><div class="cc-arch-actions"><button data-generate>Generate Context</button><button data-copy>Copy Context</button></div><textarea data-context readonly placeholder="AI context"></textarea><label>AI cresco-patch/v1<textarea data-patch></textarea></label><pre data-diff>No patch preview yet.</pre><div class="cc-arch-actions"><button data-preview>Preview Diff</button><button data-apply disabled>Apply Candidate</button></div></section>');
  q('[data-generate]',state.ai).onclick=function(){var s=q('[data-scope]',state.ai).value,p=q('[data-prompt]',state.ai).value.trim();context(s,s==='document'?'redesign':'edit',p).then(function(c){q('[data-context]',state.ai).value=JSON.stringify(c,null,2);toast('Scoped AI context generated.','success');}).catch(function(e){toast(e.message,'error');});};
  q('[data-copy]',state.ai).onclick=function(){copyJSON(q('[data-context]',state.ai).value).then(function(){toast('AI context copied.','success');});};
  q('[data-preview]',state.ai).onclick=function(){var p;try{p=JSON.parse(q('[data-patch]',state.ai).value);}catch(e){return toast('Invalid patch JSON.','error');}previewPatch(p).then(function(r){q('[data-diff]',state.ai).textContent=JSON.stringify(r.diff||r,null,2);q('[data-apply]',state.ai).disabled=!r.session;toast('Scoped patch validated.','success');}).catch(function(e){q('[data-apply]',state.ai).disabled=true;toast(e.message,'error');});};
  q('[data-apply]',state.ai).onclick=function(){if(state.candidate&&state.candidate.session)applyCandidate(state.candidate.session).then(function(){state.ai.classList.remove('is-open');});};
 }
 q('[data-scope]',state.ai).value=scope||'widget';q('[data-context]',state.ai).value='';q('[data-patch]',state.ai).value='';q('[data-diff]',state.ai).textContent='No patch preview yet.';q('[data-apply]',state.ai).disabled=true;state.context=null;state.candidate=null;state.ai.classList.add('is-open');
}
function showDocuments(){if(!state.docs)state.docs=overlay('cc-arch-documents','<section class="cc-arch-dialog"><header><strong>Cresco Documents</strong><button data-close>×</button></header><div data-docs></div></section>');var items=state.arch&&state.arch.documents||[];q('[data-docs]',state.docs).innerHTML=items.map(function(d){return '<a href="'+esc(d.editUrl||'#')+'"><strong>'+esc(d.title||'Untitled')+'</strong><small>'+esc(d.group)+' · '+esc(d.type)+' · '+esc(d.status)+'</small></a>';}).join('')||'<div class="cc-arch-empty">No documents.</div>';state.docs.classList.add('is-open');}
function showRender(){captureSession().then(function(s){return api({path:cfg.renderPath,method:'POST',data:{currentSession:s||undefined}});}).then(function(r){var x=r.render||r;if(!state.render)state.render=overlay('cc-arch-render','<section class="cc-arch-dialog cc-arch-render-dialog"><header><strong>Authoritative Renderer Preview</strong><button data-close>×</button></header><iframe title="Cresco renderer preview" sandbox="allow-forms allow-popups allow-same-origin"></iframe></section>');q('iframe',state.render).srcdoc='<!doctype html><style>html,body{margin:0}'+String(x.css||'')+'</style>'+String(x.html||'');state.render.classList.add('is-open');}).catch(function(e){toast(e.message,'error');});}
function scanA11y(){var c=q('.cc-builder-canvas'),n=0;if(!c)return toast('Canvas not found.','error');qa('img',c).forEach(function(i){if(!i.getAttribute('alt'))n++;});qa('a',c).forEach(function(a){if(!a.getAttribute('href')||a.getAttribute('href')==='#')n++;});toast(n?n+' obvious accessibility issue(s).':'No obvious accessibility issue detected.',n?'warning':'success');}
function contextMenu(){var m=q('.cc-builder-pro-context-menu');if(!m||m.dataset.arch)return;m.dataset.arch='1';[['AI · Edit Widget',function(){showAI('widget');}],['AI · Edit Section',function(){showAI('subtree');}],['Export · Selection',function(){context(ids().length>1?'selection':'widget','edit','').then(copyJSON);}]].forEach(function(x){var b=document.createElement('button');b.type='button';b.textContent=x[0];b.className='cc-arch-context-action';b.onclick=x[1];m.appendChild(b);});}
function boot(){api({path:cfg.architecturePath}).then(function(a){state.arch=a;seedCommands();shell();new MutationObserver(function(){shell();contextMenu();}).observe(root,{childList:true,subtree:true,attributes:true,attributeFilter:['class']});document.addEventListener('keydown',function(e){if((e.metaKey||e.ctrlKey)&&String(e.key).toLowerCase()==='k'){e.preventDefault();e.stopImmediatePropagation();showPalette();}},true);window.crescoBuilderArchitecture={version:'architecture-v1',commands:{list:function(){return state.commands.slice();},run:function(id){var c=state.commands.find(function(x){return x.id===id;});return c&&c.run();}},scope:{selectedIds:ids,context:context},bridge:{set:function(x){state.bridge=x||null;}},ui:{palette:showPalette,ai:showAI},render:{preview:showRender}};window.dispatchEvent(new CustomEvent('cresco:architecture-ready',{detail:window.crescoBuilderArchitecture}));}).catch(function(e){toast('Architecture bootstrap failed: '+e.message,'error');});}
boot();
})(window,document,window.wp);
